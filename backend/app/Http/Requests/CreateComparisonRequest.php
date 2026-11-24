<?php

namespace App\Http\Requests;

class CreateComparisonRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'document_a_id' => ['required', 'integer', 'exists:document_metadata,id'],
            'document_b_id' => ['required', 'integer', 'exists:document_metadata,id', 'different:document_a_id'],
            'prompt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'focus_prompt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'options' => ['sometimes', 'array'],
        ];
    }
}
