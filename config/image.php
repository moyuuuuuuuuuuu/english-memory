<?php

declare(strict_types=1);

return [
    'source_hosts' => array_values(array_filter(array_map('trim', explode(',', getenv('IMAGE_SOURCE_HOSTS') ?: 's.coze.cn')))),
    'connect_timeout' => (int) (getenv('IMAGE_CONNECT_TIMEOUT') ?: 5),
    'total_timeout' => (int) (getenv('IMAGE_TOTAL_TIMEOUT') ?: 20),
    'max_redirects' => (int) (getenv('IMAGE_MAX_REDIRECTS') ?: 2),
    'max_bytes' => (int) (getenv('IMAGE_MAX_BYTES') ?: 10485760),
    'min_dimension' => (int) (getenv('IMAGE_MIN_DIMENSION') ?: 256),
    'max_dimension' => (int) (getenv('IMAGE_MAX_DIMENSION') ?: 8192),
    'max_pixels' => (int) (getenv('IMAGE_MAX_PIXELS') ?: 40000000),
    'webp_quality' => (int) (getenv('IMAGE_WEBP_QUALITY') ?: 82),
    'storage_driver' => getenv('STORAGE_DRIVER') ?: 'local',
    'storage_public_url' => getenv('STORAGE_PUBLIC_URL') ?: 'http://e.test/storage',
    'storage_local_root' => getenv('STORAGE_LOCAL_ROOT') ?: 'public/storage',
];
