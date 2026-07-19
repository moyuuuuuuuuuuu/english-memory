<?php

declare(strict_types=1);

namespace app\entities;

final readonly class DownloadedImageEntity
{
    public function __construct(
        private string $path,
        private string $mime,
        private int $bytes,
        private int $width,
        private int $height,
    ) {
    }

    public function path(): string { return $this->path; }
    public function mime(): string { return $this->mime; }
    public function bytes(): int { return $this->bytes; }
    public function width(): int { return $this->width; }
    public function height(): int { return $this->height; }
}
