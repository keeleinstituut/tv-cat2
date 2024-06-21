<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SegmentIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'job_id' => 'required|uuid',
            'source' => 'string',
            'target' => 'string',
            'per_page' => 'integer',
        ];
    }
}
