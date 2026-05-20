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
        ];
    }
}
