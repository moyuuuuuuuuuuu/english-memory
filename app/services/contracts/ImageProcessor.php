<?php

declare(strict_types=1);

namespace app\services\contracts;

use app\entities\DownloadedImageEntity;
use app\entities\ProcessedImageSetEntity;

interface ImageProcessor
{
    public function process(DownloadedImageEntity $source): ProcessedImageSetEntity;
}
