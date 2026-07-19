<?php

declare(strict_types=1);

namespace app\services\contracts;

use app\entities\DownloadedImageEntity;

interface RemoteImageDownloader
{
    public function download(string $url): DownloadedImageEntity;
}
