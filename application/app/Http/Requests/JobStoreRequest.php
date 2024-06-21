<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JobStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => 'required|uuid|exists:projects,id',
            'target_locale' => 'required|string',
            'source_files' => 'required|array|size:1',
            'source_files.*' => 'file',
        ];
    }
}
