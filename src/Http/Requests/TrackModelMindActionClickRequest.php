<?php

namespace Mbs\ModelMind\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackModelMindActionClickRequest extends FormRequest
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
            'message_id' => ['nullable', 'uuid'],
            'label' => ['nullable', 'string', 'max:160'],
            'url' => ['required', 'string', 'max:2048'],
            'kind' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'in:action,citation,unknown'],
            'index' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }
}
