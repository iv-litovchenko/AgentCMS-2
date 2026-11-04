<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class ConnectorService
{
    private string $registryPath;

    private string $tokensFilePath;

    private string $publicBaseDomain;

    private string $publicHttpPort;

    private string $publicHttpsPort;

    /** @var array<string, mixed>|null */
    private ?array $tokensCache = null;

    public function __construct(
        string $registryPath,
        string $tokensFilePath,
        string $publicBaseDomain,
        string $publicHttpPort,
        string $publicHttpsPort
    ) {
        $this->registryPath = $registryPath;
        $this->tokensFilePath = $tokensFilePath;
        $this->publicBaseDomain = $publicBaseDomain;
        $this->publicHttpPort = $publicHttpPort;
        $this->publicHttpsPort = $publicHttpsPort;
    }

    /**
     * @return array{
     *     scheme: string,
     *     cms_url: string,
     *     mcp_url: string,
     *     mcp_json_example: string,
     *     mcp_auth_configured: bool
     * }
     */
    public function buildCurrentConnection(): array
    {
        $registry = $this->loadRegistry();
        $mcpPath = $registry['mcp_path'] ?? '/mcp/';
        if (!is_string($mcpPath)) {
            $mcpPath = '/mcp/';
        }

        $scheme = 'https';

        $host = $this->publicBaseDomain;
        if ($host === '') {
            $host = 'localhost';
        }

        $portSuffix = $this->portSuffixForScheme($scheme);
        $baseUrl = $scheme . '://' . $host . $portSuffix;
        $mcpUrl = rtrim($baseUrl, '/') . '/' . ltrim($mcpPath, '/');

        $authToken = $this->getMcpAuthTokenValue();

        return [
            'scheme' => $scheme,
            'cms_url' => rtrim($baseUrl, '/') . '/',
            'mcp_url' => $mcpUrl,
            'mcp_json_example' => $this->buildMcpJsonExample($mcpUrl, $authToken),
            'mcp_auth_configured' => $authToken !== '',
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     value: string,
     *     sensitive: bool
     * }>
     */
    public function listTokens(): array
    {
        $registry = $this->loadRegistry();
        $rawItems = $registry['tokens']['items'] ?? [];
        if (!is_array($rawItems)) {
            return [];
        }

        $stored = $this->loadTokenValues();
        $values = $stored['values'] ?? [];
        if (!is_array($values)) {
            $values = [];
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $id = $rawItem['id'] ?? '';
            if (!is_string($id) || !$this->isValidId($id)) {
                continue;
            }

            $label = $rawItem['label'] ?? $id;
            if (!is_string($label)) {
                $label = $id;
            }

            $description = $rawItem['description'] ?? '';
            if (!is_string($description)) {
                $description = '';
            }

            $value = '';
            if (array_key_exists($id, $values) && is_string($values[$id])) {
                $value = $values[$id];
            }

            $items[] = [
                'id' => $id,
                'label' => $label,
                'description' => $description,
                'value' => $value,
                'sensitive' => true,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, string> $values
     */
    public function saveTokenValues(array $values): void
    {
        $allowed = [];
        foreach ($this->listTokensFromRegistry() as $id) {
            $allowed[] = $id;
        }

        $normalized = [];
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (!in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException('Unknown token id: ' . $key);
            }
            if (!is_string($value)) {
                $value = '';
            }
            $normalized[$key] = trim($value);
        }

        foreach ($allowed as $id) {
            if (!array_key_exists($id, $normalized)) {
                $normalized[$id] = '';
            }
        }

        $payload = ['values' => $normalized];
        $this->writeTokenValues($payload);
        $this->tokensCache = null;
    }

    public function getTokensFileDisplayPath(): string
    {
        return 'var/connector/tokens.yaml';
    }

    private function buildMcpJsonExample(string $mcpUrl, string $authToken): string
    {
        $authorization = $this->formatMcpAuthorizationHeaderValue($authToken);

        $server = [
            'url' => $mcpUrl,
            'headers' => [
                'Authorization' => $authorization,
            ],
        ];

        $payload = [
            'mcpServers' => [
                'agent-cms' => $server,
            ],
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return '{}';
        }

        return $encoded;
    }

    private function getMcpAuthTokenValue(): string
    {
        $tokenId = $this->resolveMcpAuthTokenId();
        $stored = $this->loadTokenValues();
        $values = $stored['values'] ?? [];
        if (!is_array($values)) {
            $values = [];
        }

        $token = '';
        if (array_key_exists($tokenId, $values) && is_string($values[$tokenId])) {
            $token = trim($values[$tokenId]);
        }

        return $token;
    }

    private function formatMcpAuthorizationHeaderValue(string $token): string
    {
        if ($token === '') {
            return 'Bearer <не задан>';
        }

        return 'Bearer ' . $token;
    }

    private function resolveMcpAuthTokenId(): string
    {
        $registry = $this->loadRegistry();
        $tokenId = $registry['mcp_auth_token_id'] ?? 'cursor_mcp';
        if (!is_string($tokenId) || !$this->isValidId($tokenId)) {
            return 'cursor_mcp';
        }

        return $tokenId;
    }

    private function portSuffixForScheme(string $scheme): string
    {
        if ($scheme === 'http') {
            if ($this->publicHttpPort !== '' && $this->publicHttpPort !== '80') {
                return ':' . $this->publicHttpPort;
            }

            return '';
        }

        if ($this->publicHttpsPort !== '' && $this->publicHttpsPort !== '443') {
            return ':' . $this->publicHttpsPort;
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function listTokensFromRegistry(): array
    {
        $registry = $this->loadRegistry();
        $rawItems = $registry['tokens']['items'] ?? [];
        if (!is_array($rawItems)) {
            return [];
        }

        $ids = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }
            $id = $rawItem['id'] ?? '';
            if (is_string($id) && $this->isValidId($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRegistry(): array
    {
        if (!is_file($this->registryPath)) {
            return [];
        }

        $parsed = Yaml::parseFile($this->registryPath);
        if (!is_array($parsed)) {
            return [];
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTokenValues(): array
    {
        if ($this->tokensCache !== null) {
            return $this->tokensCache;
        }

        if (!is_file($this->tokensFilePath)) {
            $this->tokensCache = [];

            return $this->tokensCache;
        }

        $parsed = Yaml::parseFile($this->tokensFilePath);
        if (!is_array($parsed)) {
            $this->tokensCache = [];

            return $this->tokensCache;
        }

        $this->tokensCache = $parsed;

        return $this->tokensCache;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeTokenValues(array $payload): void
    {
        $dir = dirname($this->tokensFilePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create connector tokens directory.');
            }
        }

        $tmpPath = $this->tokensFilePath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $yaml = Yaml::dump($payload, 3, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $handle = fopen($tmpPath, 'cb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open temp tokens file.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock tokens file.');
            }

            ftruncate($handle, 0);
            $written = fwrite($handle, $yaml);
            if ($written === false) {
                throw new \RuntimeException('Cannot write tokens file.');
            }

            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!rename($tmpPath, $this->tokensFilePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Cannot save tokens file.');
        }
    }

    private function isValidId(string $id): bool
    {
        if ($id === '') {
            return false;
        }

        return preg_match('/^[a-z0-9_-]+$/', $id) === 1;
    }
}
