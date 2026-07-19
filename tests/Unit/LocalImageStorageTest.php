<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\ImageArtifactEntity;
use app\entities\ProcessedImageSetEntity;
use app\services\LocalImageStorage;
use PHPUnit\Framework\TestCase;

final class LocalImageStorageTest extends TestCase
{
    private string $root;
    private string $sources;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir() . '/english-memory-storage-' . bin2hex(random_bytes(6));
        $this->root = $base . '/public/storage';
        $this->sources = $base . '/sources';
        mkdir($this->root, 0755, true);
        mkdir($this->sources, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree(dirname($this->root, 2));
        parent::tearDown();
    }

    public function test_it_atomically_stores_all_variants_under_an_owned_path(): void
    {
        $processed = $this->processed();
        $storage = new LocalImageStorage($this->root, 'http://e.test/storage', 'local');

        $stored = $storage->store(12, 34, $processed);

        foreach ($stored->keys() as $key) {
            self::assertMatchesRegularExpression('#^memory-cards/12/34/[a-f0-9]{32}-(original|large|small)\.(png|webp)$#', $key);
            $path = $this->root . '/' . $key;
            self::assertFileExists($path);
            self::assertStringStartsWith(realpath($this->root), realpath($path));
        }
        self::assertSame($processed->large()->sha256(), $stored->largeSha256());
        self::assertSame(
            'http://e.test/storage/' . $stored->largeKey(),
            $stored->largeUrl(),
        );
    }

    public function test_delete_keys_is_idempotent_and_rejects_unsafe_keys(): void
    {
        $storage = new LocalImageStorage($this->root, 'http://e.test/storage', 'local');
        $stored = $storage->store(1, 2, $this->processed());

        $storage->deleteKeys($stored->keys());
        $storage->deleteKeys($stored->keys());

        foreach ($stored->keys() as $key) {
            self::assertFileDoesNotExist($this->root . '/' . $key);
        }

        $this->expectException(ImageImportException::class);
        $storage->deleteKeys(['../outside.txt']);
    }

    public function test_partial_store_failure_removes_files_from_that_import(): void
    {
        $processed = $this->processed();
        unlink($processed->large()->path());
        $storage = new LocalImageStorage($this->root, 'http://e.test/storage', 'local');

        try {
            $storage->store(7, 8, $processed);
            self::fail('Expected storage failure.');
        } catch (ImageImportException $exception) {
            self::assertSame(BusinessCode::ImageStorageFailed, $exception->businessCode());
        }

        self::assertSame([], glob($this->root . '/memory-cards/7/8/*') ?: []);
    }

    private function processed(): ProcessedImageSetEntity
    {
        $original = $this->artifact('original', 'png', 'image/png', 'original-bytes');
        $large = $this->artifact('large', 'webp', 'image/webp', 'large-bytes');
        $small = $this->artifact('small', 'webp', 'image/webp', 'small-bytes');

        return new ProcessedImageSetEntity($original, $large, $small);
    }

    private function artifact(string $role, string $extension, string $mime, string $bytes): ImageArtifactEntity
    {
        $path = $this->sources . '/' . $role . '-' . bin2hex(random_bytes(4));
        file_put_contents($path, $bytes);

        return new ImageArtifactEntity(
            $role,
            $path,
            $extension,
            $mime,
            640,
            480,
            strlen($bytes),
            hash('sha256', $bytes),
        );
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
