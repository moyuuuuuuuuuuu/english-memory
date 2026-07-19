<?php

declare(strict_types=1);

namespace app\services\contracts;

use app\entities\ProcessedImageSetEntity;
use app\entities\StoredImageSetEntity;

interface ImageStorage
{
    public function store(int $userId, int $cardId, ProcessedImageSetEntity $images): StoredImageSetEntity;

    /** @param list<string> $keys */
    public function deleteKeys(array $keys): void;
}
