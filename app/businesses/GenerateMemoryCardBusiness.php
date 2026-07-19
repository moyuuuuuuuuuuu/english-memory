<?php

namespace app\businesses;

use app\common\enums\BusinessCode;

use app\entities\MemoryCardGenerationEntity;
use app\services\contracts\MemoryCardGenerator;

final class GenerateMemoryCardBusiness
{
    private const CONTENT_TYPES = ['word', 'sentence'];
    private const MEMORY_STYLES = ['auto', 'phonetic_story', 'semantic_scene'];

    public function __construct(private readonly MemoryCardGenerator $generator)
    {
    }

    public function generate(
        string $text,
        string $contentType = 'word',
        string $memoryStyle = 'auto',
    ): MemoryCardGenerationEntity {
        $text = trim($text);
        if ($text === '') {
            return MemoryCardGenerationEntity::failure(422, BusinessCode::InvalidInput, '请输入英文单词或句子。');
        }

        if (!in_array($contentType, self::CONTENT_TYPES, true)
            || !in_array($memoryStyle, self::MEMORY_STYLES, true)) {
            return MemoryCardGenerationEntity::failure(422, BusinessCode::InvalidInput, '参数值不在允许范围内。');
        }

        try {
            $result = $this->generator->generate($text, $contentType, 'zh-CN', $memoryStyle);
        } catch (\Throwable) {
            return MemoryCardGenerationEntity::failure(
                502,
                BusinessCode::AiProviderError,
                '记忆卡生成服务暂时不可用，请稍后重试。',
            );
        }

        if (!$result->isSuccess()) {
            return MemoryCardGenerationEntity::failure(
                422,
                BusinessCode::GenerationFailed,
                $result->error() ?: '无法生成记忆卡。',
            );
        }

        return MemoryCardGenerationEntity::success($result);
    }
}
