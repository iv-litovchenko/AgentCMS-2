<?php

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'service' => 'api',
    'status' => 'stub',
    'message' => 'Agent CMS Light API — заготовка',
]);
