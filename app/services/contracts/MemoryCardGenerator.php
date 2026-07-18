<?php

namespace app\services\contracts;

use app\entities\AiGenerationResultEntity;

interface MemoryCardGenerator
{
    public function generate(
        string $text,
        string $contentType,
        string $nativeLanguage,
        string $memoryStyle,
    ): AiGenerationResultEntity;
}
