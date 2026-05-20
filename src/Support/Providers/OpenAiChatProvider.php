<?php

namespace Mbs\LaravelAiChat\Support\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Mbs\LaravelAiChat\Contracts\AiChatProvider;
use Mbs\LaravelAiChat\Data\ChatRequestData;
use Mbs\LaravelAiChat\Data\ChatResponseData;
use Mbs\LaravelAiChat\Support\PromptBuilder;
use RuntimeException;

class OpenAiChatProvider implements AiChatProvider
{
    public function __construct(private readonly PromptBuilder $promptBuilder) {}

    public function answer(ChatRequestData $request): ChatResponseData
    {
        $apiKey = config('mbs-ai-chat.provider.api_key');

        if (! is_string($apiKey) || blank($apiKey)) {
            throw new RuntimeException('The MBS AI Chat OpenAI API key is not configured.');
        }

        $payload = [
            'model' => config('mbs-ai-chat.provider.model', 'gpt-5-mini'),
            'instructions' => $request->instructions,
            'input' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => $this->promptBuilder->input($request->question, $request->session),
                ]],
            ]],
            'max_output_tokens' => (int) config('mbs-ai-chat.provider.max_output_tokens', 700),
            'store' => (bool) config('mbs-ai-chat.provider.store', false),
        ];

        $reasoningEffort = config('mbs-ai-chat.provider.reasoning_effort');

        if (is_string($reasoningEffort) && filled($reasoningEffort)) {
            $payload['reasoning'] = ['effort' => $reasoningEffort];
        }

        $responsePayload = $this->post($apiKey, $payload);
        $answer = $this->extractText($responsePayload);

        if (blank($answer) && Arr::get($responsePayload, 'incomplete_details.reason') === 'max_output_tokens') {
            $payload['max_output_tokens'] = max((int) $payload['max_output_tokens'], 1200);
            $responsePayload = $this->post($apiKey, $payload);
            $answer = $this->extractText($responsePayload);
        }

        if (blank($answer)) {
            throw new RuntimeException('The MBS AI Chat provider response did not include text.');
        }

        return new ChatResponseData(trim($answer), [
            'model' => $payload['model'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $apiKey, array $payload): array
    {
        try {
            $request = Http::acceptJson()
                ->withToken($apiKey)
                ->connectTimeout((int) config('mbs-ai-chat.provider.connect_timeout', 4))
                ->timeout((int) config('mbs-ai-chat.provider.timeout', 20));

            $organization = config('mbs-ai-chat.provider.organization');

            if (is_string($organization) && filled($organization)) {
                $request = $request->withHeaders(['OpenAI-Organization' => $organization]);
            }

            $response = $request->post('https://api.openai.com/v1/responses', $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The MBS AI Chat provider request could not connect.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The MBS AI Chat provider request failed.');
        }

        return $response->json() ?? [];
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
}
