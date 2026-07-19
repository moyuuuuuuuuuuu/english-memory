<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\DownloadedImageEntity;
use app\services\GdImageProcessor;
use PHPUnit\Framework\TestCase;

final class GdImageProcessorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/english-memory-gd-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function test_it_preserves_original_and_generates_proportional_webp_variants(): void
    {
        $source = $this->fixture('png', 1200, 600, true);
        $processor = $this->processor();

        $processed = $processor->process($this->downloaded($source, 'image/png', 1200, 600));

        self::assertSame(file_get_contents($source), file_get_contents($processed->original()->path()));
        self::assertSame([1024, 512], [$processed->large()->width(), $processed->large()->height()]);
        self::assertSame([512, 256], [$processed->small()->width(), $processed->small()->height()]);
        self::assertSame('image/webp', $processed->large()->mime());
        foreach ($processed->all() as $artifact) {
            self::assertSame(hash_file('sha256', $artifact->path()), $artifact->sha256());
            self::assertSame(filesize($artifact->path()), $artifact->bytes());
        }

        $large = imagecreatefromwebp($processed->large()->path());
        self::assertGreaterThan(0, (imagecolorat($large, 0, 0) >> 24) & 0x7F);
        imagedestroy($large);
    }

    public function test_it_never_enlarges_small_images(): void
    {
        $source = $this->fixture('jpeg', 300, 280);
        $processed = $this->processor()->process($this->downloaded($source, 'image/jpeg', 300, 280));

        self::assertSame([300, 280], [$processed->large()->width(), $processed->large()->height()]);
        self::assertSame([300, 280], [$processed->small()->width(), $processed->small()->height()]);
    }

    public function test_it_accepts_webp_source_and_keeps_original_extension(): void
    {
        $source = $this->fixture('webp', 640, 480);
        $processed = $this->processor()->process($this->downloaded($source, 'image/webp', 640, 480));

        self::assertSame('webp', $processed->original()->extension());
        self::assertSame('image/webp', $processed->original()->mime());
    }

    public function test_it_rejects_corrupt_mismatched_unsupported_or_unsafe_dimensions(): void
    {
        $corrupt = $this->directory . '/corrupt.png';
        file_put_contents($corrupt, 'not an image');
        $png = $this->fixture('png', 300, 300);
        $small = $this->fixture('jpeg', 100, 100);

        $cases = [
            [$this->downloaded($corrupt, 'image/png', 300, 300), $this->processor()],
            [$this->downloaded($png, 'image/jpeg', 300, 300), $this->processor()],
            [$this->downloaded($png, 'image/gif', 300, 300), $this->processor()],
            [$this->downloaded($small, 'image/jpeg', 100, 100), $this->processor()],
            [$this->downloaded($png, 'image/png', 300, 300), $this->processor(maxPixels: 80000)],
        ];

        foreach ($cases as [$download, $processor]) {
            try {
                $processor->process($download);
                self::fail('Expected invalid image rejection.');
            } catch (ImageImportException $exception) {
                self::assertSame(BusinessCode::InvalidImage, $exception->businessCode());
            }
        }
    }

    private function processor(int $maxPixels = 40000000): GdImageProcessor
    {
        return new GdImageProcessor($this->directory, 256, 8192, $maxPixels, 82);
    }

    private function downloaded(string $path, string $mime, int $width, int $height): DownloadedImageEntity
    {
        return new DownloadedImageEntity($path, $mime, filesize($path), $width, $height);
    }

    private function fixture(string $format, int $width, int $height, bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $color = imagecolorallocatealpha($image, 20, 40, 60, 64);
        } else {
            $color = imagecolorallocate($image, 20, 40, 60);
        }
        imagefill($image, 0, 0, $color);
        $path = $this->directory . '/' . bin2hex(random_bytes(5)) . '.' . ($format === 'jpeg' ? 'jpg' : $format);
        match ($format) {
            'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 90),
        };
        imagedestroy($image);
        return $path;
    }
}
