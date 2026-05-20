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

class OllamaModelMindProvider implements ModelMindProvider, StreamingModelMindProvider
{
    use ReadsProviderSettings;

    private const DRIVER = 'ollama';

    public function __construct(private readonly PromptBuilder $promptBuilder) {}

    public function answer(ModelMindRequestData $request): ModelMindResponseData
    {
        $payload = $this->payload($request, stream: false);
        $responsePayload = $this->post($payload);
        $answer = $this->extractText($responsePayload);

        if (blank($answer)) {
            throw new RuntimeException('The ModelMind Ollama response did not include text.');
        }

        return new ModelMindResponseData(trim($answer), $this->metadata($this->model(), $responsePayload));
    }

    /**
     * @return iterable<int, string>
     */
    public function stream(ModelMindRequestData $request): iterable
    {
        $payload = $this->payload($request, stream: true);

        try {
            $response = $this->request()
                ->withOptions(['stream' => true])
                ->post($this->endpoint(), $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The ModelMind Ollama request could not connect.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The ModelMind Ollama request failed.');
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

    /**
     * @return array<string, mixed>
     */
    private function payload(ModelMindRequestData $request, bool $stream): array
    {
        $payload = [
            'model' => $this->model(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $request->instructions,
                ],
                [
                    'role' => 'user',
                    'content' => $this->promptBuilder->input($request->question, $request->session),
                ],
            ],
            'stream' => $stream,
        ];

        $options = $this->providerSetting(self::DRIVER, 'options', []);

        if (is_array($options) && $options !== []) {
            $payload['options'] = $options;
        }

        $keepAlive = $this->providerSetting(self::DRIVER, 'keep_alive');

        if (is_string($keepAlive) && filled($keepAlive)) {
            $payload['keep_alive'] = $keepAlive;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(array $payload): array
    {
        try {
            $response = $this->request()->post($this->endpoint(), $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The ModelMind Ollama request could not connect.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The ModelMind Ollama request failed.');
        }

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return $this->providerRequest(self::DRIVER)->asJson();
    }

    private function endpoint(): string
    {
        return $this->providerBaseUrl(self::DRIVER, 'http://127.0.0.1:11434').'/api/chat';
    }

    private function model(): string
    {
        $model = $this->providerSetting(self::DRIVER, 'model', 'llama3.1');

        return is_string($model) && filled($model) ? $model : 'llama3.1';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        $message = Arr::get($payload, 'message.content');

        if (is_string($message)) {
            return $message;
        }

        $response = $payload['response'] ?? null;

        return is_string($response) ? $response : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadata(string $model, array $payload): array
    {
        $inputTokens = $this->intOrNull($payload['prompt_eval_count'] ?? null);
        $outputTokens = $this->intOrNull($payload['eval_count'] ?? null);

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
                $line = trim(substr($buffer, 0, $position));
                $buffer = substr($buffer, $position + 1);
                $delta = $this->extractStreamDelta($line);

                if ($delta !== null) {
                    yield $delta;
                }
            }
        }

        if (trim($buffer) !== '') {
            $delta = $this->extractStreamDelta(trim($buffer));

            if ($delta !== null) {
                yield $delta;
            }
        }
    }

    private function extractStreamDelta(string $line): ?string
    {
        $payload = json_decode($line, true);

        if (! is_array($payload)) {
            return null;
        }

        $text = Arr::get($payload, 'message.content');

        return is_string($text) && $text !== '' ? $text : null;
    }
}
