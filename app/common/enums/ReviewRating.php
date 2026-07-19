<?php

declare(strict_types=1);

namespace app\common\enums;

enum ReviewRating: string
{
    case Again = 'again';
    case Hard = 'hard';
    case Good = 'good';
    case Easy = 'easy';
}
