<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'source_locale' => ['string'],
            'target_locale' => ['string'],
            'meta' => ['array'],
            'meta.*' => ['string'],
            'filter' => ['array'],
            'with_segment_count' => ['boolean']
        ];
    }
}
