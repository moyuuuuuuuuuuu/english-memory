<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\DownloadedImageEntity;
use app\entities\ImageArtifactEntity;
use app\entities\ProcessedImageSetEntity;
use app\entities\StoredImageSetEntity;
use PHPUnit\Framework\TestCase;

final class ImageImportContractTest extends TestCase
{
    public function test_stable_image_failure_codes_and_safe_exception_contract(): void
    {
        self::assertSame([
            'IMAGE_SOURCE_NOT_ALLOWED',
            'IMAGE_DOWNLOAD_FAILED',
            'INVALID_IMAGE',
            'IMAGE_PROCESSING_FAILED',
            'IMAGE_STORAGE_FAILED',
        ], array_map(
            static fn (BusinessCode $code): string => $code->value,
            [
                BusinessCode::ImageSourceNotAllowed,
                BusinessCode::ImageDownloadFailed,
                BusinessCode::InvalidImage,
                BusinessCode::ImageProcessingFailed,
                BusinessCode::ImageStorageFailed,
            ],
        ));

        $exception = new ImageImportException(
            BusinessCode::InvalidImage,
            'image_validation',
            '图片内容无效。',
        );

        self::assertSame(BusinessCode::InvalidImage, $exception->businessCode());
        self::assertSame('image_validation', $exception->failureType());
        self::assertSame('图片内容无效。', $exception->safeMessage());
        self::assertSame('图片内容无效。', $exception->getMessage());
    }

    public function test_image_entities_expose_typed_artifact_metadata(): void
    {
        $download = new DownloadedImageEntity('/tmp/source.png', 'image/png', 123, 640, 480);
        $original = new ImageArtifactEntity('original', '/tmp/original.png', 'png', 'image/png', 640, 480, 123, str_repeat('a', 64));
        $large = new ImageArtifactEntity('large', '/tmp/large.webp', 'webp', 'image/webp', 640, 480, 100, str_repeat('b', 64));
        $small = new ImageArtifactEntity('small', '/tmp/small.webp', 'webp', 'image/webp', 512, 384, 80, str_repeat('c', 64));
        $processed = new ProcessedImageSetEntity($original, $large, $small);
        $stored = new StoredImageSetEntity(
            'local',
            'memory-cards/1/2/a-original.png',
            'memory-cards/1/2/a-large.webp',
            'memory-cards/1/2/a-small.webp',
            $original->sha256(),
            $large->sha256(),
            $small->sha256(),
            'image/png',
            640,
            480,
            123,
            'http://e.test/storage/memory-cards/1/2/a-large.webp',
            'http://e.test/storage/memory-cards/1/2/a-small.webp',
        );

        self::assertSame('/tmp/source.png', $download->path());
        self::assertSame(123, $download->bytes());
        self::assertSame($large, $processed->large());
        self::assertSame('local', $stored->driver());
        self::assertSame('memory-cards/1/2/a-large.webp', $stored->largeKey());
        self::assertSame('http://e.test/storage/memory-cards/1/2/a-small.webp', $stored->smallUrl());
    }

    public function test_image_configuration_declares_all_safety_limits(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/image.php';

        self::assertContains('s.coze.cn', $config['source_hosts']);
        self::assertSame(10485760, $config['max_bytes']);
        self::assertSame(40000000, $config['max_pixels']);
        self::assertSame(82, $config['webp_quality']);
    }
}
