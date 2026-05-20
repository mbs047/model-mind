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

class GeminiModelMindProvider implements ModelMindProvider, StreamingModelMindProvider
{
    use ReadsProviderSettings;

    private const DRIVER = 'gemini';

    public function __construct(private readonly PromptBuilder $promptBuilder) {}

    public function answer(ModelMindRequestData $request): ModelMindResponseData
    {
        $apiKey = $this->apiKey();
        $payload = $this->payload($request);
        $responsePayload = $this->post($apiKey, $payload, stream: false);
        $answer = $this->extractText($responsePayload);

        if (blank($answer)) {
            throw new RuntimeException('The ModelMind Gemini response did not include text.');
        }

        return new ModelMindResponseData(trim($answer), $this->metadata($this->model(), $responsePayload));
    }

    /**
     * @return iterable<int, string>
     */
    public function stream(ModelMindRequestData $request): iterable
    {
        $apiKey = $this->apiKey();
        $payload = $this->payload($request);

        try {
            $response = $this->request()
                ->withOptions(['stream' => true])
                ->post($this->endpoint($apiKey, stream: true), $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The ModelMind Gemini request could not connect.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The ModelMind Gemini request failed.');
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
            throw new RuntimeException('The ModelMind Gemini API key is not configured.');
        }

        return $apiKey;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ModelMindRequestData $request): array
    {
        return [
            'systemInstruction' => [
                'parts' => [[
                    'text' => $request->instructions,
                ]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $this->promptBuilder->input($request->question, $request->session, $request->pageContext),
                ]],
            ]],
            'generationConfig' => [
                'maxOutputTokens' => (int) $this->providerSetting(self::DRIVER, 'max_output_tokens', 700),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $apiKey, array $payload, bool $stream): array
    {
        try {
            $response = $this->request()->post($this->endpoint($apiKey, $stream), $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The ModelMind Gemini request could not connect.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The ModelMind Gemini request failed.');
        }

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return $this->providerRequest(self::DRIVER)->asJson();
    }

    private function endpoint(string $apiKey, bool $stream): string
    {
        $operation = $stream ? 'streamGenerateContent?alt=sse' : 'generateContent';
        $separator = $stream ? '&' : '?';

        return $this->providerBaseUrl(self::DRIVER, 'https://generativelanguage.googleapis.com/v1beta')
            .'/'.$this->modelPath().':'.$operation.$separator.'key='.urlencode($apiKey);
    }

    private function model(): string
    {
        $model = $this->providerSetting(self::DRIVER, 'model', 'gemini-2.0-flash');

        return is_string($model) && filled($model) ? $model : 'gemini-2.0-flash';
    }

    private function modelPath(): string
    {
        $model = $this->model();

        return str_starts_with($model, 'models/') ? $model : 'models/'.$model;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        return collect(Arr::get($payload, 'candidates', []))
            ->flatMap(fn (array $candidate): array => Arr::get($candidate, 'content.parts', []))
            ->map(fn (array $part): string => (string) ($part['text'] ?? ''))
            ->filter()
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadata(string $model, array $payload): array
    {
        return [
            'model' => $model,
            'provider' => self::DRIVER,
            'input_tokens' => $this->intOrNull(Arr::get($payload, 'usageMetadata.promptTokenCount')),
            'output_tokens' => $this->intOrNull(Arr::get($payload, 'usageMetadata.candidatesTokenCount')),
            'total_tokens' => $this->intOrNull(Arr::get($payload, 'usageMetadata.totalTokenCount')),
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

        if (! is_array($payload)) {
            return null;
        }

        $text = $this->extractText($payload);

        return $text !== '' ? $text : null;
    }
}
