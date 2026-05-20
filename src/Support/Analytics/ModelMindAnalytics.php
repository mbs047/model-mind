<?php

namespace Mbs\ModelMind\Support\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Mbs\ModelMind\Events\ModelMindAnalyticsRecorded;
use Mbs\ModelMind\Models\ModelMindEvent;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Mbs\ModelMind\Support\Database\TableNames;
use Throwable;

class ModelMindAnalytics
{
    public function recordChatCompleted(
        ModelMindSession $session,
        ModelMindMessage $message,
        array $providerMetadata,
        int $latencyMs,
        array $context = [],
    ): ?ModelMindEvent {
        return $this->record([
            'model_mind_session_id' => $session->getKey(),
            'model_mind_message_id' => $message->getKey(),
            'type' => ModelMindEvent::TYPE_CHAT_COMPLETED,
            'provider' => $this->provider($providerMetadata),
            'provider_model' => $this->providerModel($providerMetadata),
            'latency_ms' => max(0, $latencyMs),
            ...$this->usage($providerMetadata),
            'metadata' => [
                'provider_metadata' => $providerMetadata,
                ...$context,
            ],
        ]);
    }

    public function recordChatFailed(
        ModelMindSession $session,
        ModelMindMessage $message,
        Throwable $exception,
        int $latencyMs,
    ): ?ModelMindEvent {
        return $this->record([
            'model_mind_session_id' => $session->getKey(),
            'model_mind_message_id' => $message->getKey(),
            'type' => ModelMindEvent::TYPE_CHAT_FAILED,
            'provider' => $this->configuredProvider(),
            'provider_model' => $this->configuredProviderModel(),
            'latency_ms' => max(0, $latencyMs),
            'metadata' => [
                'error_class' => $exception::class,
                'error_message' => str($exception->getMessage())->squish()->limit(500, '')->toString(),
            ],
        ]);
    }

    public function recordFeedback(ModelMindMessage $message, ?Request $request = null): ?ModelMindEvent
    {
        return $this->record([
            'model_mind_session_id' => $message->session?->getKey(),
            'model_mind_message_id' => $message->getKey(),
            'type' => ModelMindEvent::TYPE_FEEDBACK_SUBMITTED,
            'metadata' => [
                'feedback' => $message->feedback,
                'note_present' => filled($message->feedback_note),
                ...$this->requestMetadata($request),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordActionClick(
        ?ModelMindSession $session,
        ?ModelMindMessage $message,
        array $payload,
        ?Request $request = null,
    ): ?ModelMindEvent {
        return $this->record([
            'model_mind_session_id' => $session?->getKey() ?? $message?->session?->getKey(),
            'model_mind_message_id' => $message?->getKey(),
            'type' => ModelMindEvent::TYPE_ACTION_CLICKED,
            'metadata' => [
                'label' => $this->cleanScalar($payload['label'] ?? null, 160),
                'url' => $this->cleanScalar($payload['url'] ?? null, 2048),
                'kind' => $this->cleanScalar($payload['kind'] ?? null, 80),
                'source' => $this->cleanScalar($payload['source'] ?? null, 40),
                'index' => is_numeric($payload['index'] ?? null) ? (int) $payload['index'] : null,
                ...$this->requestMetadata($request),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $days = 7): array
    {
        if (! $this->tableReady()) {
            return [
                'enabled' => $this->enabled(),
                'table_ready' => false,
                'days' => max(1, $days),
                'totals' => [],
                'providers' => [],
            ];
        }

        $since = now()->subDays(max(1, $days));
        $events = ModelMindEvent::query()
            ->where('occurred_at', '>=', $since)
            ->get();

        $completed = $events->where('type', ModelMindEvent::TYPE_CHAT_COMPLETED);
        $failed = $events->where('type', ModelMindEvent::TYPE_CHAT_FAILED);
        $feedback = $events->where('type', ModelMindEvent::TYPE_FEEDBACK_SUBMITTED);
        $clicks = $events->where('type', ModelMindEvent::TYPE_ACTION_CLICKED);

        return [
            'enabled' => $this->enabled(),
            'table_ready' => true,
            'days' => max(1, $days),
            'since' => $since->toJSON(),
            'until' => now()->toJSON(),
            'totals' => [
                'events' => $events->count(),
                'completed' => $completed->count(),
                'failed' => $failed->count(),
                'feedback' => $feedback->count(),
                'feedback_rate' => $this->rate($feedback->count(), $completed->count()),
                'action_clicks' => $clicks->count(),
                'avg_latency_ms' => $this->averageLatency($completed),
                'input_tokens' => $completed->sum(fn (ModelMindEvent $event): int => (int) $event->input_tokens),
                'output_tokens' => $completed->sum(fn (ModelMindEvent $event): int => (int) $event->output_tokens),
                'total_tokens' => $completed->sum(fn (ModelMindEvent $event): int => (int) $event->total_tokens),
            ],
            'providers' => $this->providerSummary($events),
            'feedback' => [
                'liked' => $feedback->filter(fn (ModelMindEvent $event): bool => ($event->metadata['feedback'] ?? null) === ModelMindMessage::FEEDBACK_LIKED)->count(),
                'disliked' => $feedback->filter(fn (ModelMindEvent $event): bool => ($event->metadata['feedback'] ?? null) === ModelMindMessage::FEEDBACK_DISLIKED)->count(),
            ],
            'action_clicks' => $this->actionClickSummary($clicks),
            'errors' => $this->errorSummary($failed),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): ?ModelMindEvent
    {
        if (! $this->enabled() || ! $this->tableReady()) {
            return null;
        }

        try {
            $event = ModelMindEvent::query()->create([
                ...$attributes,
                'occurred_at' => now(),
            ]);

            event(new ModelMindAnalyticsRecorded($event));

            return $event;
        } catch (Throwable) {
            return null;
        }
    }

    private function enabled(): bool
    {
        return (bool) config('model-mind.analytics.enabled', true);
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable(TableNames::events());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{input_tokens: int|null, output_tokens: int|null, total_tokens: int|null}
     */
    private function usage(array $metadata): array
    {
        $input = $this->intOrNull($metadata['input_tokens'] ?? null);
        $output = $this->intOrNull($metadata['output_tokens'] ?? null);
        $total = $this->intOrNull($metadata['total_tokens'] ?? null);

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total ?? (($input !== null || $output !== null) ? (int) $input + (int) $output : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function provider(array $metadata): ?string
    {
        return $this->cleanScalar($metadata['provider'] ?? $this->configuredProvider(), 80);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function providerModel(array $metadata): ?string
    {
        return $this->cleanScalar($metadata['model'] ?? $metadata['provider_model'] ?? $this->configuredProviderModel(), 160);
    }

    private function configuredProvider(): ?string
    {
        return $this->cleanScalar(config('model-mind.provider.default', 'openai'), 80);
    }

    private function configuredProviderModel(): ?string
    {
        $provider = $this->configuredProvider();
        $driverModel = is_string($provider) ? config("model-mind.provider.drivers.{$provider}.model") : null;

        return $this->cleanScalar($driverModel ?? config('model-mind.provider.model'), 160);
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function cleanScalar(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = str((string) $value)->squish()->limit($limit, '')->toString();

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string|null>
     */
    private function requestMetadata(?Request $request): array
    {
        if (! $request instanceof Request) {
            return [];
        }

        return [
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent() ?? '')->limit(500, '')->toString(),
        ];
    }

    /**
     * @param  Collection<int, ModelMindEvent>  $events
     */
    private function averageLatency(Collection $events): ?int
    {
        $latencies = $events
            ->map(fn (ModelMindEvent $event): ?int => $event->latency_ms === null ? null : (int) $event->latency_ms)
            ->filter(fn (?int $latency): bool => $latency !== null);

        return $latencies->isEmpty() ? null : (int) round($latencies->avg());
    }

    private function rate(int $count, int $total): float
    {
        return $total === 0 ? 0.0 : round($count / $total, 4);
    }

    /**
     * @param  Collection<int, ModelMindEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function providerSummary(Collection $events): array
    {
        return $events
            ->whereIn('type', [ModelMindEvent::TYPE_CHAT_COMPLETED, ModelMindEvent::TYPE_CHAT_FAILED])
            ->groupBy(fn (ModelMindEvent $event): string => ($event->provider ?: 'unknown').'|'.($event->provider_model ?: 'unknown'))
            ->map(function (Collection $group): array {
                /** @var ModelMindEvent $first */
                $first = $group->first();
                $completed = $group->where('type', ModelMindEvent::TYPE_CHAT_COMPLETED);

                return [
                    'provider' => $first->provider ?: 'unknown',
                    'model' => $first->provider_model ?: 'unknown',
                    'completed' => $completed->count(),
                    'failed' => $group->where('type', ModelMindEvent::TYPE_CHAT_FAILED)->count(),
                    'avg_latency_ms' => $this->averageLatency($completed),
                    'total_tokens' => $completed->sum(fn (ModelMindEvent $event): int => (int) $event->total_tokens),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ModelMindEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function actionClickSummary(Collection $events): array
    {
        return $events
            ->groupBy(fn (ModelMindEvent $event): string => (string) ($event->metadata['label'] ?? $event->metadata['kind'] ?? 'Unknown action'))
            ->map(function (Collection $group, string $label): array {
                $metadata = $group->first()?->metadata ?? [];

                return [
                    'label' => $label,
                    'count' => $group->count(),
                    'kind' => $metadata['kind'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ModelMindEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function errorSummary(Collection $events): array
    {
        return $events
            ->groupBy(fn (ModelMindEvent $event): string => (string) ($event->metadata['error_class'] ?? 'Unknown error'))
            ->map(fn (Collection $group, string $errorClass): array => [
                'error_class' => $errorClass,
                'count' => $group->count(),
                'latest_message' => $group->last()?->metadata['error_message'] ?? null,
            ])
            ->values()
            ->all();
    }
}
