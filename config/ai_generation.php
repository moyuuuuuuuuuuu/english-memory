<?php

declare(strict_types=1);

return [
    'stream' => getenv('AI_GENERATION_STREAM') ?: 'english-memory:ai-generation',
    'group' => getenv('AI_GENERATION_GROUP') ?: 'english-memory-workers',
    'consumer_prefix' => getenv('AI_GENERATION_CONSUMER_PREFIX') ?: 'worker',
    'block_ms' => (int) (getenv('AI_GENERATION_BLOCK_MS') ?: 5000),
    'claim_idle_ms' => (int) (getenv('AI_GENERATION_CLAIM_IDLE_MS') ?: 60000),
    'compensation_age_seconds' => (int) (getenv('AI_GENERATION_COMPENSATION_AGE_SECONDS') ?: 120),
];
