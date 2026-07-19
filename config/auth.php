<?php

declare(strict_types=1);

return [
    'access_token_ttl' => (int) (getenv('AUTH_ACCESS_TOKEN_TTL') ?: 900),
    'refresh_token_ttl' => (int) (getenv('AUTH_REFRESH_TOKEN_TTL') ?: 2592000),
    'password_reset_ttl' => (int) (getenv('AUTH_PASSWORD_RESET_TTL') ?: 1800),
];
