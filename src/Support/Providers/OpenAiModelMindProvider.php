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

class OpenAiModelMindProvider implements ModelMindProvider, StreamingModelMindProvider
{
    use ReadsProviderSettings;

    private const DRIVER = 'openai';

    public function __construct(private readonly PromptBuilder $promptBuilder) {}

    public function answer(ModelMindRequestData $request): ModelMindResponseData
    {
        $apiKey = $this->providerSetting(self::DRIVER, 'api_key');

        if (! is_string($apiKey) || blank($apiKey)) {
            throw new RuntimeException('The ModelMind OpenAI API key is not configured.');
        }

        $payload = $this->payload($request);
        $responsePayload = $this->post($apiKey, $payload);
        $answer = $this->extractText($responsePayload);

        if (
            blank($answer)
                && (bool) $this->providerSetting(self::DRIVER, 'retry_when_truncated', false)
            && Arr::get($responsePayload, 'incomplete_details.reason') === 'max_output_tokens'
        ) {
            $payload['max_output_tokens'] = max((int) $payload['max_output_tokens'], 1200);
            $responsePayload = $this->post($apiKey, $payload);
            $answer = $this->extractText($responsePayload);
        }

        if (blank($answer)) {
            throw new RuntimeException('The ModelMind provider response did not include text.');
        }

        return new ModelMindResponseData(trim($answer), [
            'model' => $payload['model'],
        ]);
    }

    /**
     * @return iterable<int, string>
     */
    public function stream(ModelMindRequestData $request): iterable
    {
        $apiKey = $this->providerSetting(self::DRIVER, 'api_key');

        if (! is_string($apiKey) || blank($apiKey)) {
            throw new RuntimeException('The ModelMind OpenAI API key is not configured.');
        }

        $payload = $this->payload($request, stream: true);

        try {
            $response = $this->request($apiKey)
                ->withOptions(['stream' => true])
                ->post($this->providerBaseUrl(self::DRIVER, 'https://api.openai.com/v1').'/responses', $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The ModelMind provider request could not connect.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The ModelMind provider request failed.');
        }

        $body = $response->toPsrResponse()->getBody();
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

        if ($buffer !== '') {
            $delta = $this->extractStreamDelta(rtrim($buffer, "\r"));

            if ($delta !== null) {
                yield $delta;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function streamMetadata(ModelMindRequestData $request): array
    {
        return [
            'model' => $this->payload($request)['model'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ModelMindRequestData $request, bool $stream = false): array
    {
        $payload = [
            'model' => $this->providerSetting(self::DRIVER, 'model', 'gpt-5-mini'),
            'instructions' => $request->instructions,
            'input' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => $this->promptBuilder->input($request->question, $request->session),
                ]],
            ]],
            'max_output_tokens' => (int) $this->providerSetting(self::DRIVER, 'max_output_tokens', 700),
            'store' => (bool) $this->providerSetting(self::DRIVER, 'store', false),
        ];

        if ($stream) {
            $payload['stream'] = true;
        }

        $reasoningEffort = $this->providerSetting(self::DRIVER, 'reasoning_effort');

        if (is_string($reasoningEffort) && filled($reasoningEffort)) {
            $payload['reasoning'] = ['effort' => $reasoningEffort];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $apiKey, array $payload): array
    {
        try {
            $response = $this->request($apiKey)->post($this->providerBaseUrl(self::DRIVER, 'https://api.openai.com/v1').'/responses', $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The ModelMind provider request could not connect.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The ModelMind provider request failed.');
        }

        return $response->json() ?? [];
    }

    private function request(string $apiKey): PendingRequest
    {
        $request = $this->providerRequest(self::DRIVER)
            ->withToken($apiKey)
            ->asJson();

        $organization = $this->providerSetting(self::DRIVER, 'organization');

        if (is_string($organization) && filled($organization)) {
            $request = $request->withHeaders(['OpenAI-Organization' => $organization]);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        $directText = Arr::get($payload, 'output_text');

        if (is_string($directText)) {
            return $directText;
        }

        return collect(Arr::get($payload, 'output', []))
            ->flatMap(fn (array $item): array => Arr::get($item, 'content', []))
            ->map(fn (array $content): string => (string) ($content['text'] ?? ''))
            ->filter()
            ->implode("\n");
    }

    private function extractStreamDelta(string $line): ?string
    {
        if (! str_starts_with($line, 'data:')) {
            return null;
        }

        $data = trim(substr($line, 5));

        if ($data === '' || $data === '[DONE]') {
            return null;
        }

        $payload = json_decode($data, true);

        if (! is_array($payload)) {
            return null;
        }

        $type = (string) ($payload['type'] ?? '');
        $delta = $payload['delta'] ?? null;

        if (! is_string($delta)) {
            return null;
        }

        return in_array($type, ['response.output_text.delta', 'response.refusal.delta'], true)
            ? $delta
            : null;
    }
}
