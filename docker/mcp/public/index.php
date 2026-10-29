<?php

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'service' => 'mcp',
    'status' => 'stub',
    'message' => 'Agent CMS Light MCP — заготовка',
]);
