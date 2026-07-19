<?php

declare(strict_types=1);

namespace app\entities;

final readonly class StoredImageSetEntity
{
    public function __construct(
        private string $driver,
        private string $originalKey,
        private string $largeKey,
        private string $smallKey,
        private string $originalSha256,
        private string $largeSha256,
        private string $smallSha256,
        private string $originalMime,
        private int $originalWidth,
        private int $originalHeight,
        private int $originalBytes,
        private string $largeUrl,
        private string $smallUrl,
    ) {
    }

    public function driver(): string { return $this->driver; }
    public function originalKey(): string { return $this->originalKey; }
    public function largeKey(): string { return $this->largeKey; }
    public function smallKey(): string { return $this->smallKey; }
    public function originalSha256(): string { return $this->originalSha256; }
    public function largeSha256(): string { return $this->largeSha256; }
    public function smallSha256(): string { return $this->smallSha256; }
    public function originalMime(): string { return $this->originalMime; }
    public function originalWidth(): int { return $this->originalWidth; }
    public function originalHeight(): int { return $this->originalHeight; }
    public function originalBytes(): int { return $this->originalBytes; }
    public function largeUrl(): string { return $this->largeUrl; }
    public function smallUrl(): string { return $this->smallUrl; }

    /** @return list<string> */
    public function keys(): array { return [$this->originalKey, $this->largeKey, $this->smallKey]; }
}
