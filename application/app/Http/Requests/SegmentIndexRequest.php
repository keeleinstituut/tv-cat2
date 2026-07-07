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
            'filter_empty' => 'boolean',
            'filter_not_empty' => 'boolean',
            'filter_first_repetition' => 'boolean',
            'filter_score_101' => 'boolean',
            'filter_score_100' => 'boolean',
            'filter_score_99' => 'boolean',
            'filter_fuzzy' => 'boolean',
            'filter_tm' => 'boolean',
            'filter_nt' => 'boolean',
            'filter_mt' => 'boolean',
            'filter_no_match' => 'boolean',
            'sort' => 'string|in:source_asc,source_desc,shortest,longest,match_asc,match_desc',
        ];
    }
}
