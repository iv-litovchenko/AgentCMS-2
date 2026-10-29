<?php

declare(strict_types=1);

use AgentCmsLight\Shared\GreetingService;

require_once dirname(__DIR__) . '/shared/php/src/GreetingService.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
}
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$path = '/';
if (is_string($parsedPath)) {
    $path = $parsedPath;
}

if ($method === 'GET' && ($path === '/' || $path === '')) {
    echo json_encode([
        'service' => 'mcp',
        'protocol' => 'jsonrpc-2.0',
        'tools' => [
            [
                'name' => 'hello',
                'description' => 'Returns the shared greeting message',
            ],
        ],
        'hint' => 'POST JSON-RPC: methods tools/list, tools/call (name: hello)',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error' => ['code' => -32700, 'message' => 'Parse error'],
        'id' => null,
    ]);
    exit;
}

$id = null;
if (array_key_exists('id', $payload)) {
    $id = $payload['id'];
}
$rpcMethod = '';
if (isset($payload['method'])) {
    $rpcMethod = (string) $payload['method'];
}

$response = mcp_dispatch($rpcMethod, $payload, new GreetingService());

echo json_encode([
    'jsonrpc' => '2.0',
    'id' => $id,
    'result' => $response,
], JSON_UNESCAPED_UNICODE);

/**
 * @param array<string, mixed> $payload
 *
 * @return array<string, mixed>
 */
function mcp_dispatch(string $rpcMethod, array $payload, GreetingService $greetingService): array
{
    if ($rpcMethod === 'initialize') {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => ['tools' => new stdClass()],
            'serverInfo' => ['name' => 'agent-cms-light-mcp', 'version' => '0.1.0'],
        ];
    }

    if ($rpcMethod === 'tools/list') {
        return [
            'tools' => [
                [
                    'name' => 'hello',
                    'description' => 'Returns the shared greeting message',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => new stdClass(),
                    ],
                ],
            ],
        ];
    }

    if ($rpcMethod === 'tools/call') {
        $params = [];
        if (isset($payload['params']) && is_array($payload['params'])) {
            $params = $payload['params'];
        }
        $name = '';
        if (isset($params['name'])) {
            $name = (string) $params['name'];
        }

        if ($name !== 'hello') {
            return [
                'content' => [
                    ['type' => 'text', 'text' => 'Unknown tool'],
                ],
                'isError' => true,
            ];
        }

        return [
            'content' => [
                ['type' => 'text', 'text' => $greetingService->getMessage()],
            ],
            'isError' => false,
        ];
    }

    return [
        'content' => [
            ['type' => 'text', 'text' => 'Unsupported method: ' . $rpcMethod],
        ],
        'isError' => true,
    ];
}
