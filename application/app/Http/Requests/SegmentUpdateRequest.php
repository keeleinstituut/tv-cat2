<?php

namespace App\Http\Requests;

use App\Support\SegmentText;
use Illuminate\Foundation\Http\FormRequest;

class SegmentUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    /** Text from the editor is brought into the segment text form (b i u s sup sub br, escaped & < >). */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('target'))) {
            $this->merge(['target' => SegmentText::normalize($this->input('target'))]);
        }
    }

    public function rules(): array
    {
        return [
            'target' => 'nullable|string',
            'save_repetitions' => 'boolean',
            'confirmed' => 'boolean',
            'locked' => 'boolean',
        ];
    }
}
