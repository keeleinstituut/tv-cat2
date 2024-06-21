<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'source_locale' => ['required', 'string'],
//            'target_locale' => ['required', 'array', 'min:1'],
//            'target_locale.*' => ['string'],
//            'source_files' => ['array', 'min:1'],
//            'source_files.*' => ['file'],
        ];
    }
}
