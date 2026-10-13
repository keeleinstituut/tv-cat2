<?php

namespace App\Http\Requests;

use App\Support\SegmentText;
use Illuminate\Foundation\Http\FormRequest;

class SegmentBulkUpdateRequest extends FormRequest
{
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
            'job_id'                  => 'required|uuid',
            'segment_ids'             => 'array',
            'segment_ids.*'           => 'uuid',
            'source'                  => 'string',
            'target_filter'           => 'string',
            'filter_empty'            => 'boolean',
            'filter_not_empty'        => 'boolean',
            'filter_first_repetition' => 'boolean',
            'filter_score_101'        => 'boolean',
            'filter_score_100'        => 'boolean',
            'filter_score_99'         => 'boolean',
            'filter_fuzzy'            => 'boolean',
            'filter_tm'               => 'boolean',
            'filter_nt'               => 'boolean',
            'filter_mt'               => 'boolean',
            'filter_no_match'         => 'boolean',
            'filter_locked'           => 'boolean',
            'filter_not_locked'       => 'boolean',
            'confirmed'               => 'boolean',
            'target'                  => 'nullable|string',
            'save_repetitions'        => 'boolean',
        ];
    }
}
