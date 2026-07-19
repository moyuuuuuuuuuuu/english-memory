<?php

declare(strict_types=1);

namespace app\entities;

final readonly class ProcessedImageSetEntity
{
    public function __construct(
        private ImageArtifactEntity $original,
        private ImageArtifactEntity $large,
        private ImageArtifactEntity $small,
    ) {
    }

    public function original(): ImageArtifactEntity { return $this->original; }
    public function large(): ImageArtifactEntity { return $this->large; }
    public function small(): ImageArtifactEntity { return $this->small; }

    /** @return list<ImageArtifactEntity> */
    public function all(): array { return [$this->original, $this->large, $this->small]; }
}
