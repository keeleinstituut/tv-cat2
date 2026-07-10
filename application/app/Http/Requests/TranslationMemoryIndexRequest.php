<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TranslationMemoryIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string'],
            'tenant_id' => ['nullable', 'string'],
            'source_locale' => ['nullable', 'array'],
            'source_locale.*' => ['nullable', 'string'],
            'target_locale' => ['nullable', 'array'],
            'target_locale.*' => ['nullable', 'string'],
            'visibility' => ['nullable', 'array'],
            'visibility.*' => ['nullable', Rule::in(['private', 'shared', 'public'])],
            'tv_domain' => ['nullable', 'array'],
            'tv_domain.*' => ['nullable', 'string'],
            'tv_tags' => ['nullable', 'array'],
            'tv_tags.*' => ['nullable', 'string'],
            'with_segment_count' => ['boolean'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
