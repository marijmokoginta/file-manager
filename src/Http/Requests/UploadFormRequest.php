<?php

namespace M2code\FileManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'         => ['required'],
            'options'      => ['sometimes', 'array'],
            'options.*'    => ['sometimes'],
            'cancel_token' => ['sometimes', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A file is required for upload.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $file = $this->input('file');

        // If file is a base64 data URI string, convert it to a temp UploadedFile
        if (is_string($file) && str_starts_with($file, 'data:')) {
            $uploadedFile = $this->convertBase64ToUploadedFile($file);
            $this->merge(['file' => $uploadedFile]);
        }
    }

    protected function convertBase64ToUploadedFile(string $dataUri): UploadedFile
    {
        if (!preg_match('#^data:([^;]+);base64,(.+)$#s', $dataUri, $matches)) {
            abort(422, 'Invalid base64 data URI format.');
        }

        $mimeType = $matches[1];
        $decoded = base64_decode($matches[2], true);

        if ($decoded === false) {
            abort(422, 'Failed to decode base64 content.');
        }

        $extension = $this->mapMimeToExtension($mimeType);
        $tmpPath = tempnam(sys_get_temp_dir(), 'fm_b64_');
        file_put_contents($tmpPath, $decoded);

        return new UploadedFile(
            $tmpPath,
            'upload.' . $extension,
            $mimeType,
            null,
            true,
        );
    }

    protected function mapMimeToExtension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
