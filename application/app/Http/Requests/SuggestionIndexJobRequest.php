<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuggestionIndexJobRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->filled('segment_id')) {
            return [
                'segment_id'     => 'required|uuid',
                'q'              => 'prohibited',
                'context_before' => 'prohibited',
                'context_after'  => 'prohibited',
                'providers'      => 'array',
                'providers.*'    => 'string',
                'limit'          => 'integer|min:1',
            ];
        }

        return [
            'q'              => 'required|string',
            'context_before' => 'nullable|string',
            'context_after'  => 'nullable|string',
            'providers'      => 'array',
            'providers.*'    => 'string',
            'limit'          => 'integer|min:1',
        ];
    }
}
