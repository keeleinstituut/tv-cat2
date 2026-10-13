<?php

namespace App\Http\Requests;

use App\Services\FiltersClient;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => 'required|uuid|exists:projects,id',
            'target_locale' => 'required|string',
            'source_files' => 'required_without:source_file_url|array|size:1',
            'source_files.*' => 'file',
            'source_file_url' => 'required_without:source_files|url',
            'source_file_name' => 'required_with:source_file_url|string',
        ];
    }

    /**
     * The file type must be one the filters service can read. When the service cannot be asked the check is skipped:
     * the job then fails with the service's own error instead of the upload being refused.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $name = $this->hasFile('source_files.0')
                ? $this->file('source_files.0')->getClientOriginalName()
                : (string) $this->input('source_file_name');
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            try {
                $formats = app(FiltersClient::class)->formats();
            } catch (Throwable $e) {
                Log::warning('Could not check the file type against the filters service: ' . $e->getMessage());

                return;
            }

            if ($formats && !in_array($extension, $formats, true)) {
                $validator->errors()->add(
                    $this->hasFile('source_files.0') ? 'source_files.0' : 'source_file_name',
                    $extension === '' ? 'The file has no extension.' : "Files of type .$extension are not supported.",
                );
            }
        });
    }
}
