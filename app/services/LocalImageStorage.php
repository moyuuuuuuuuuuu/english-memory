<?php

declare(strict_types=1);

namespace app\services;

use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\ImageArtifactEntity;
use app\entities\ProcessedImageSetEntity;
use app\entities\StoredImageSetEntity;
use app\services\contracts\ImageStorage;
use Throwable;

final class LocalImageStorage implements ImageStorage
{
    private string $root;

    public function __construct(
        string $root,
        private readonly string $publicUrl,
        private readonly string $driver = 'local',
    ) {
        if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) {
            throw $this->storageFailure();
        }
        $resolved = realpath($root);
        if ($resolved === false) {
            throw $this->storageFailure();
        }
        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function store(int $userId, int $cardId, ProcessedImageSetEntity $images): StoredImageSetEntity
    {
        if ($userId <= 0 || $cardId <= 0) {
            throw $this->storageFailure();
        }
        $directory = $this->root . "/memory-cards/{$userId}/{$cardId}";
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw $this->storageFailure();
        }
        $resolvedDirectory = realpath($directory);
        if ($resolvedDirectory === false || !$this->withinRoot($resolvedDirectory)) {
            throw $this->storageFailure();
        }

        $importId = bin2hex(random_bytes(16));
        $keys = [];
        $writtenPaths = [];
        $temporaryPaths = [];
        try {
            foreach ($images->all() as $artifact) {
                $this->validateArtifact($artifact);
                $key = "memory-cards/{$userId}/{$cardId}/{$importId}-{$artifact->role()}.{$artifact->extension()}";
                $destination = $this->root . '/' . $key;
                $temporary = $destination . '.tmp-' . bin2hex(random_bytes(6));
                $temporaryPaths[] = $temporary;
                $this->copyAtomically($artifact, $temporary, $destination);
                $writtenPaths[] = $destination;
                $keys[$artifact->role()] = $key;
            }

            $original = $images->original();
            return new StoredImageSetEntity(
                $this->driver,
                $keys['original'],
                $keys['large'],
                $keys['small'],
                $original->sha256(),
                $images->large()->sha256(),
                $images->small()->sha256(),
                $original->mime(),
                $original->width(),
                $original->height(),
                $original->bytes(),
                $this->publicUrl($keys['large']),
                $this->publicUrl($keys['small']),
            );
        } catch (Throwable) {
            foreach (array_merge($temporaryPaths, $writtenPaths) as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            throw $this->storageFailure();
        }
    }

    public function deleteKeys(array $keys): void
    {
        $directories = [];
        foreach ($keys as $key) {
            if (!is_string($key) || !$this->validKey($key)) {
                throw $this->storageFailure();
            }
            $path = $this->root . '/' . $key;
            if (!$this->withinRoot(dirname($path))) {
                throw $this->storageFailure();
            }
            if (is_file($path) && !unlink($path)) {
                throw $this->storageFailure();
            }
            $directories[dirname($path)] = true;
        }

        foreach (array_keys($directories) as $directory) {
            $this->removeEmptyDirectory($directory);
            $this->removeEmptyDirectory(dirname($directory));
        }
    }

    private function copyAtomically(ImageArtifactEntity $artifact, string $temporary, string $destination): void
    {
        $input = @fopen($artifact->path(), 'rb');
        $output = @fopen($temporary, 'xb');
        if ($input === false || $output === false) {
            if (is_resource($input)) { fclose($input); }
            if (is_resource($output)) { fclose($output); }
            throw $this->storageFailure();
        }
        try {
            if (stream_copy_to_stream($input, $output) === false || !fflush($output)) {
                throw $this->storageFailure();
            }
            if (function_exists('fsync')) {
                fsync($output);
            }
        } finally {
            fclose($input);
            fclose($output);
        }
        if (hash_file('sha256', $temporary) !== $artifact->sha256()
            || !rename($temporary, $destination)
            || !chmod($destination, 0644)) {
            throw $this->storageFailure();
        }
    }

    private function validateArtifact(ImageArtifactEntity $artifact): void
    {
        $allowed = [
            'original' => ['jpg', 'png', 'webp'],
            'large' => ['webp'],
            'small' => ['webp'],
        ];
        if (!isset($allowed[$artifact->role()])
            || !in_array($artifact->extension(), $allowed[$artifact->role()], true)
            || !is_file($artifact->path())) {
            throw $this->storageFailure();
        }
    }

    private function validKey(string $key): bool
    {
        return preg_match(
            '#^memory-cards/[1-9][0-9]*/[1-9][0-9]*/[a-f0-9]{32}-(original|large|small)\.(jpg|png|webp)$#D',
            $key,
        ) === 1;
    }

    private function withinRoot(string $path): bool
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $this->root), '/');
        return $normalized === $root || str_starts_with($normalized . '/', $root . '/');
    }

    private function publicUrl(string $key): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $key)));
        return rtrim($this->publicUrl, '/') . '/' . $encoded;
    }

    private function removeEmptyDirectory(string $directory): void
    {
        if ($this->withinRoot($directory)
            && $directory !== $this->root
            && is_dir($directory)
            && (scandir($directory) ?: []) === ['.', '..']) {
            @rmdir($directory);
        }
    }

    private function storageFailure(): ImageImportException
    {
        return new ImageImportException(
            BusinessCode::ImageStorageFailed,
            'image_storage',
            '图片保存失败，请手动重试。',
        );
    }
}
