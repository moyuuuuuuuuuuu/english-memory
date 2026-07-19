<?php

declare(strict_types=1);

namespace app\common\enums;

enum ReviewMode: string
{
    case ImageRecall = 'image_recall';
    case ListeningSpelling = 'listening_spelling';
    case ZhToEn = 'zh_to_en';
}
