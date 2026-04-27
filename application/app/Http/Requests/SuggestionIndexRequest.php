<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuggestionIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => 'required|string',
            'context_before' => 'string',
            'context_after' => 'string',
            'source_locale' => 'required|string',
            'target_locale' => 'required|string',
            'providers' => 'array',
            'providers.*' => 'string',
            'translation_memory_ids' => 'array',
            'translation_memory_ids.*' => 'uuid',
            'limit' => 'integer|min:1',
        ];
    }
}
