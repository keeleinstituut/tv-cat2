<?php

namespace App\Http\Requests;

use App\Support\SegmentText;
use Illuminate\Foundation\Http\FormRequest;

class TranslationMemorySegmentUpdateRequest extends FormRequest
{
    /** Text from the editor is brought into the segment text form (b i u s sup sub br, escaped & < >). */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('source'))) {
            $this->merge(['source' => SegmentText::normalize($this->input('source'))]);
        }
        if (is_string($this->input('target'))) {
            $this->merge(['target' => SegmentText::normalize($this->input('target'))]);
        }
    }

    public function rules(): array
    {
        return [
            'source' => 'nullable|string',
            'target' => 'nullable|string',
        ];
    }
}
