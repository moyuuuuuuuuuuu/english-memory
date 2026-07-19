<?php

declare(strict_types=1);

namespace app\entities;

final readonly class ImageArtifactEntity
{
    public function __construct(
        private string $role,
        private string $path,
        private string $extension,
        private string $mime,
        private int $width,
        private int $height,
        private int $bytes,
        private string $sha256,
    ) {
    }

    public function role(): string { return $this->role; }
    public function path(): string { return $this->path; }
    public function extension(): string { return $this->extension; }
    public function mime(): string { return $this->mime; }
    public function width(): int { return $this->width; }
    public function height(): int { return $this->height; }
    public function bytes(): int { return $this->bytes; }
    public function sha256(): string { return $this->sha256; }
}
