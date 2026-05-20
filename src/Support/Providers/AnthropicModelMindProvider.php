<?php

namespace Mbs\ModelMind\Support\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Contracts\StreamingModelMindProvider;
use Mbs\ModelMind\Data\ModelMindRequestData;
use Mbs\ModelMind\Data\ModelMindResponseData;
use Mbs\ModelMind\Support\PromptBuilder;
use Mbs\ModelMind\Support\Providers\Concerns\ReadsProviderSettings;
use RuntimeException;

class AnthropicModelMindProvider implements ModelMindProvider, StreamingModelMindProvider
{
    use ReadsProviderSettings;

    private const DRIVER = 'anthropic';

    public function __construct(private readonly PromptBuilder $promptBuilder) {}

    public function answer(ModelMindRequestData $request): ModelMindResponseData
    {
        $apiKey = $this->apiKey();
        $payload = $this->payload($request);
        $responsePayload = $this->post($apiKey, $payload);
        $answer = $this->extractText($responsePayload);

        if (blank($answer)) {
            throw new RuntimeException('The ModelMind Anthropic response did not include text.');
        }

        return new ModelMindResponseData(trim($answer), $this->metadata((string) $payload['model'], $responsePayload));
    }

    /**
     * @return iterable<int, string>
     */
    public function stream(ModelMindRequestData $request): iterable
    {
        $apiKey = $this->apiKey();
        $payload = $this->payload($request, stream: true);

        try {
            $response = $this->request($apiKey)
                ->withOptions(['stream' => true])
                ->post($this->endpoint(), $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The ModelMind Anthropic request could not connect.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The ModelMind Anthropic request failed.');
        }

        yield from $this->streamDeltas($response->toPsrResponse()->getBody());
    }

    /**
     * @return array<string, mixed>
     */
    public function streamMetadata(ModelMindRequestData $request): array
    {
        return [
            'model' => $this->model(),
            'provider' => self::DRIVER,
        ];
    }

    private function apiKey(): string
    {
        $apiKey = $this->providerSetting(self::DRIVER, 'api_key');

        if (! is_string($apiKey) || blank($apiKey)) {
            throw new RuntimeException('The ModelMind Anthropic API key is not configured.');
        }

        return $apiKey;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ModelMindRequestData $request, bool $stream = false): array
    {
        return [
            'model' => $this->model(),
            'system' => $request->instructions,
            'messages' => [[
                'role' => 'user',
                'content' => $this->promptBuilder->input($request->question, $request->session, $request->pageContext),
            ]],
            'max_tokens' => (int) $this->providerSetting(self::DRIVER, 'max_output_tokens', 700),
            'stream' => $stream,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $apiKey, array $payload): array
    {
        try {
            $response = $this->request($apiKey)->post($this->endpoint(), $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The ModelMind Anthropic request could not connect.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The ModelMind Anthropic request failed.');
        }

        return $response->json() ?? [];
    }

    private function request(string $apiKey): PendingRequest
    {
        return $this->providerRequest(self::DRIVER)
            ->asJson()
            ->withHeaders([
                'anthropic-version' => (string) $this->providerSetting(self::DRIVER, 'version', '2023-06-01'),
                'x-api-key' => $apiKey,
            ]);
    }

    private function endpoint(): string
    {
        return $this->providerBaseUrl(self::DRIVER, 'https://api.anthropic.com/v1').'/messages';
    }

    private function model(): string
    {
        $model = $this->providerSetting(self::DRIVER, 'model', 'claude-3-5-haiku-latest');

        return is_string($model) && filled($model) ? $model : 'claude-3-5-haiku-latest';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        return collect(Arr::get($payload, 'content', []))
            ->map(fn (array $content): string => (string) ($content['text'] ?? ''))
            ->filter()
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadata(string $model, array $payload): array
    {
        $inputTokens = $this->intOrNull(Arr::get($payload, 'usage.input_tokens'));
        $outputTokens = $this->intOrNull(Arr::get($payload, 'usage.output_tokens'));

        return [
            'model' => $model,
            'provider' => self::DRIVER,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => ($inputTokens !== null || $outputTokens !== null) ? (int) $inputTokens + (int) $outputTokens : null,
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    /**
     * @return iterable<int, string>
     */
    private function streamDeltas(mixed $body): iterable
    {
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(1024);

            if ($chunk === '') {
                usleep(10000);

                continue;
            }

            $buffer .= $chunk;

            while (($position = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $position), "\r");
                $buffer = substr($buffer, $position + 1);
                $delta = $this->extractStreamDelta($line);

                if ($delta !== null) {
                    yield $delta;
                }
            }
        }
    }

    private function extractStreamDelta(string $line): ?string
    {
        if (! str_starts_with($line, 'data:')) {
            return null;
        }

        $payload = json_decode(trim(substr($line, 5)), true);

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'content_block_delta') {
            return null;
        }

        $text = Arr::get($payload, 'delta.text');

        return is_string($text) && $text !== '' ? $text : null;
    }
}
