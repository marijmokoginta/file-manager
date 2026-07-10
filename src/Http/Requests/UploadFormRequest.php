<?php

namespace M2code\FileManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'    => ['required', 'file'],
            'options' => ['sometimes', 'array'],
            'options.*' => ['sometimes'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A file is required for upload.',
            'file.file'     => 'The uploaded content must be a valid file.',
        ];
    }
}
