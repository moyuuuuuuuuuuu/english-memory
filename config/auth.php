<?php

declare(strict_types=1);

return [
    'access_token_ttl' => (int) (getenv('AUTH_ACCESS_TOKEN_TTL') ?: 900),
];
