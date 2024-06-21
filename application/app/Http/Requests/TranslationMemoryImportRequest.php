<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranslationMemoryImportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'translation_memory_id' => 'required|string',
            // 'files' => 'required|array|size:1',
            'files' => 'required|array|max:1000',
            'files.*' => 'file',
        ];
    }
}
