<?php

namespace M2code\FileManager\Application\FileRouter;

use Illuminate\Support\Facades\Config;
use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileTypeHandler;
use M2code\FileManager\DTO\FileOperationResult;
use M2code\FileManager\DTO\UploadResponse;

class ImageFileHandler implements FileTypeHandler
{
    public function __construct(
        protected FileSaver $driver,
        protected ?ImageUploader $uploader = null,
    ) {}

    public function canHandle(FileInput $input): bool
    {
        $mimeType = strtolower($input->getMimeType());

        return str_starts_with($mimeType, 'image/')
            && $mimeType !== 'image/svg+xml';
    }

    public function handleSave(FileInput $input, string $folder): FileOperationResult
    {
        return $this->driver->save($input, $folder);
    }

    public function handleUpload(FileInput $input, string $folder, array $options): UploadResponse
    {
        $uploader = $this->uploader ?? app(ImageUploader::class);

        $mergedOptions = $this->mergeOptions($options);
        $result = $uploader->withOptions($mergedOptions)->upload($input, $folder, $this->driver);

        $extra = [];
        if (! empty($result->blurhash)) {
            $extra['blurhash'] = $result->blurhash;
        }
        if ($result->optimizedPath) {
            $extra['optimized_path'] = $result->optimizedPath;
        }
        if ($result->watermarkPath) {
            $extra['watermark_path'] = $result->watermarkPath;
        }
        if ($result->lowQualityPath) {
            $extra['low_quality_path'] = $result->lowQualityPath;
        }

        return new UploadResponse(
            type: 'image',
            tmpPath: $result->path,
            tmpFolder: $folder,
            originalName: $input->getClientOriginalName(),
            size: strlen($input->getContent()),
            mimeType: $input->getMimeType(),
            extra: $extra,
        );
    }

    public function supportedOptions(): array
    {
        return ['optimize', 'blurhash', 'watermark', 'low_quality'];
    }

    public function defaultOptions(): array
    {
        $defaults = Config::get('file-manager.upload.default_options.image', []);

        return array_merge(
            [
                'optimize' => false,
                'blurhash' => false,
                'watermark' => false,
                'low_quality' => false,
            ],
            $defaults,
        );
    }

    protected function mergeOptions(array $requestOptions): array
    {
        $defaults = $this->defaultOptions();
        $supported = $this->supportedOptions();

        $merged = $defaults;
        foreach ($requestOptions as $key => $value) {
            if (in_array($key, $supported, true)) {
                $merged[$key] = (bool) $value;
            }
        }

        return $merged;
    }
}
