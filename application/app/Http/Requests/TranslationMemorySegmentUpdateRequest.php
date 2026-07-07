<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranslationMemorySegmentUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'target' => 'nullable|string',
        ];
    }
}
