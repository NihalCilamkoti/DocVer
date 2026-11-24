<?php

namespace App\Http\Requests;

class ChatMessageRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
