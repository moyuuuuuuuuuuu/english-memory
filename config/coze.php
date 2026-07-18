<?php

return [
    'api_base' => getenv('COZE_API_BASE') ?: 'https://api.coze.cn',
    'workflow_id' => getenv('COZE_WORKFLOW_ID') ?: '',
    'access_token' => getenv('COZE_ACCESS_TOKEN') ?: '',
    'timeout' => (int) (getenv('COZE_TIMEOUT') ?: 180),
];
