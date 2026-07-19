<?php

declare(strict_types=1);

namespace app\common\enums;

enum ReviewDifficulty: string
{
    case Hard = 'hard';
    case Good = 'good';
    case Easy = 'easy';
}
