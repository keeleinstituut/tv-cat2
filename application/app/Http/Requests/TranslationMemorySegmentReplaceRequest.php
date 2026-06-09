<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranslationMemorySegmentReplaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'translation_memory_id' => 'required|uuid',
            'source' => 'nullable|string',
            'target' => 'required|string',
            'replace_target' => 'nullable|string',
        ];
    }
}
