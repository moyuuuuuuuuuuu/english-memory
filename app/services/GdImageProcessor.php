<?php

declare(strict_types=1);

namespace app\services;

use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\DownloadedImageEntity;
use app\entities\ImageArtifactEntity;
use app\entities\ProcessedImageSetEntity;
use app\services\contracts\ImageProcessor;
use GdImage;
use Throwable;

final class GdImageProcessor implements ImageProcessor
{
    public function __construct(
        private readonly string $temporaryDirectory,
        private readonly int $minDimension,
        private readonly int $maxDimension,
        private readonly int $maxPixels,
        private readonly int $webpQuality,
    ) {
    }

    public function process(DownloadedImageEntity $source): ProcessedImageSetEntity
    {
        $paths = [];
        $image = null;
        try {
            [$mime, $width, $height] = $this->validatedMetadata($source);
            $image = $this->decode($source->path(), $mime);
            if (!$image instanceof GdImage) {
                throw $this->invalidImage();
            }

            $originalPath = $this->temporaryPath('original-');
            $paths[] = $originalPath;
            if (!copy($source->path(), $originalPath)) {
                throw $this->processingFailure();
            }
            $original = $this->artifact(
                'original',
                $originalPath,
                $this->extensionForMime($mime),
                $mime,
                $width,
                $height,
            );

            $large = $this->variant($image, $width, $height, 1024, 'large', $paths);
            $small = $this->variant($image, $width, $height, 512, 'small', $paths);

            return new ProcessedImageSetEntity($original, $large, $small);
        } catch (ImageImportException $exception) {
            $this->deletePaths($paths);
            throw $exception;
        } catch (Throwable) {
            $this->deletePaths($paths);
            throw $this->processingFailure();
        } finally {
            if ($image instanceof GdImage) {
                imagedestroy($image);
            }
        }
    }

    /** @return array{string, int, int} */
    private function validatedMetadata(DownloadedImageEntity $source): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source->path());
        $metadata = @getimagesize($source->path());
        if (!is_string($mime) || !is_array($metadata)) {
            throw $this->invalidImage();
        }
        $width = (int) ($metadata[0] ?? 0);
        $height = (int) ($metadata[1] ?? 0);
        $metadataMime = (string) ($metadata['mime'] ?? '');
        if ($mime !== $metadataMime
            || $mime !== $source->mime()
            || $width !== $source->width()
            || $height !== $source->height()
            || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $width < $this->minDimension
            || $height < $this->minDimension
            || $width > $this->maxDimension
            || $height > $this->maxDimension
            || $width * $height > $this->maxPixels) {
            throw $this->invalidImage();
        }

        return [$mime, $width, $height];
    }

    private function decode(string $path, string $mime): GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
    }

    private function variant(
        GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $maxEdge,
        string $role,
        array &$paths,
    ): ImageArtifactEntity {
        $scale = min(1, $maxEdge / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        if (!$canvas instanceof GdImage) {
            throw $this->processingFailure();
        }

        try {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
            if (!imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                $width,
                $height,
                $sourceWidth,
                $sourceHeight,
            )) {
                throw $this->processingFailure();
            }

            $path = $this->temporaryPath($role . '-');
            $paths[] = $path;
            if (!imagewebp($canvas, $path, $this->webpQuality)) {
                throw $this->processingFailure();
            }

            return $this->artifact($role, $path, 'webp', 'image/webp', $width, $height);
        } finally {
            imagedestroy($canvas);
        }
    }

    private function artifact(
        string $role,
        string $path,
        string $extension,
        string $mime,
        int $width,
        int $height,
    ): ImageArtifactEntity {
        $bytes = filesize($path);
        $sha256 = hash_file('sha256', $path);
        if ($bytes === false || $sha256 === false) {
            throw $this->processingFailure();
        }

        return new ImageArtifactEntity(
            $role,
            $path,
            $extension,
            $mime,
            $width,
            $height,
            (int) $bytes,
            $sha256,
        );
    }

    private function temporaryPath(string $prefix): string
    {
        if (!is_dir($this->temporaryDirectory)
            && !mkdir($this->temporaryDirectory, 0700, true)
            && !is_dir($this->temporaryDirectory)) {
            throw $this->processingFailure();
        }
        $path = tempnam($this->temporaryDirectory, $prefix);
        if ($path === false) {
            throw $this->processingFailure();
        }
        return $path;
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw $this->invalidImage(),
        };
    }

    private function deletePaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function invalidImage(): ImageImportException
    {
        return new ImageImportException(
            BusinessCode::InvalidImage,
            'image_validation',
            '图片内容无效或不符合尺寸要求。',
        );
    }

    private function processingFailure(): ImageImportException
    {
        return new ImageImportException(
            BusinessCode::ImageProcessingFailed,
            'image_processing',
            '图片处理失败，请手动重试。',
        );
    }
}
