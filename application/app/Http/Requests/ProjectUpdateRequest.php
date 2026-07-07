<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                         => ['sometimes', 'string'],
            'source_locale'                => ['sometimes', 'string'],
            'translation_memories'         => ['sometimes', 'array'],
            'translation_memories.*.id'    => ['required', 'uuid', 'exists:translation_memories,id'],
            'translation_memories.*.read'  => ['required', 'boolean'],
            'translation_memories.*.write' => ['required', 'boolean'],
        ];
    }
}
