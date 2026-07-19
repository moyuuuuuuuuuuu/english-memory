<?php

declare(strict_types=1);

namespace app\businesses;

final class ReviewAnswerNormalizerBusiness
{
    public function normalize(string $answer): string
    {
        $answer = preg_replace('/\s+/u', ' ', trim($answer)) ?? '';
        $answer = preg_replace('/[\p{P}\p{S}]+$/u', '', $answer) ?? '';
        return mb_strtolower(trim($answer), 'UTF-8');
    }
}
