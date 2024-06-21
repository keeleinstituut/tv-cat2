<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JobDownloadRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|in:source,source_xliff,target,target_xliff',
            'job_ids' => 'required|array|min:1',
            'job_ids.*' => 'required|uuid|exists:jobs,id',
        ];
    }
}
