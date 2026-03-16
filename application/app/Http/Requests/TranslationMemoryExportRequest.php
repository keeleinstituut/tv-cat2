<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranslationMemoryExportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'translation_memory_ids'   => 'required|array|min:1',
            'translation_memory_ids.*' => 'uuid',
            'combined'                 => 'boolean',
        ];
    }
}
