<?php

namespace Mbs\LaravelAiChat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Mbs\LaravelAiChat\Models\MbsAiChatMessage;

class FeedbackMbsAiChatMessageRequest extends FormRequest
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
                MbsAiChatMessage::FEEDBACK_LIKED,
                MbsAiChatMessage::FEEDBACK_DISLIKED,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
