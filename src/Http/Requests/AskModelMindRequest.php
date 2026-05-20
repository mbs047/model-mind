<?php

namespace Mbs\ModelMind\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskModelMindRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'uuid'],
            'question' => ['required', 'string', 'min:2', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'min:1', 'max:10000'],
            'page_context' => ['sometimes', 'array'],
            'page_context.url' => ['nullable', 'string', 'max:2048'],
            'page_context.title' => ['nullable', 'string', 'max:300'],
            'page_context.description' => ['nullable', 'string', 'max:1000'],
            'page_context.selection' => ['nullable', 'string', 'max:5000'],
            'page_context.content' => ['nullable', 'string', 'max:15000'],
            'page_context.locale' => ['nullable', 'string', 'max:40'],
            'page_context.headings' => ['sometimes', 'array', 'max:20'],
            'page_context.headings.*' => ['nullable', 'string', 'max:240'],
        ];
    }
}
