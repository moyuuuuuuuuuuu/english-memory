<?php

declare(strict_types=1);

namespace app\common\enums;

enum AiGenerationStatus: string
{
    case Queued = 'queued';
    case GeneratingText = 'generating_text';
    case GeneratingImage = 'generating_image';
    case Completed = 'completed';
    case Failed = 'failed';
}
