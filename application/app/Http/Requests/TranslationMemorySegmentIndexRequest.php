<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranslationMemorySegmentIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'translation_memory_id' => 'required|uuid',
            'source' => 'nullable|string',
            'target' => 'nullable|string',
            'per_page' => 'nullable|integer',
        ];
    }
}
