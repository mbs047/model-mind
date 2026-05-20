<?php

namespace Mbs\ModelMind\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Mbs\ModelMind\Models\ModelMindMessage;

class FeedbackModelMindMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'uuid'],
            'feedback' => ['required', Rule::in([
                ModelMindMessage::FEEDBACK_LIKED,
                ModelMindMessage::FEEDBACK_DISLIKED,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
