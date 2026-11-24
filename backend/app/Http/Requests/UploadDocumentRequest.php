<?php

namespace App\Http\Requests;

class UploadDocumentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:51200'], // 50MB max default
            'slot' => ['required', 'in:A,B'],
            'workspace_id' => ['sometimes', 'nullable', 'integer', 'exists:workspaces,id'],
            'comparison_job_id' => ['sometimes', 'nullable', 'integer', 'exists:comparison_jobs,id'],
        ];
    }
}
