<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class StarterEnvService
{
    private string $registryPath;

    private string $valuesFilePath;

    /** @var array<string, mixed>|null */
    private ?array $valuesCache = null;

    public function __construct(string $registryPath, string $valuesFilePath)
    {
        $this->registryPath = $registryPath;
        $this->valuesFilePath = $valuesFilePath;
    }

    /**
     * @return list<array{
     *     scope: string,
     *     id: string,
     *     label: string,
     *     description: string,
     *     variables: list<array{
     *         key: string,
     *         label: string,
     *         description: string,
     *         sensitive: bool,
     *         default: string,
     *         runtime: string,
     *         stored: string|null,
     *         effective: string
     *     }>
     * }>
     */
    public function listGroups(): array
    {
        $registry = $this->loadRegistry();
        $stored = $this->loadValues();
        $groups = [];

        foreach (['apps', 'packages', 'services'] as $scope) {
            $scopeBlock = $registry[$scope] ?? [];
            if (!is_array($scopeBlock)) {
                continue;
            }

            foreach ($scopeBlock as $id => $meta) {
                if (!is_string($id) || !is_array($meta)) {
                    continue;
                }
                if (!$this->isValidId($id)) {
                    continue;
                }

                $group = $this->buildGroup($scope, $id, $meta, $stored);
                if ($group !== null) {
                    $groups[] = $group;
                }
            }
        }

        return $groups;
    }

    /**
     * @param array<string, string> $values key => value (only keys from registry)
     */
    public function saveGroupValues(string $scope, string $id, array $values): void
    {
        if ($scope !== 'apps' && $scope !== 'packages' && $scope !== 'services') {
            throw new \InvalidArgumentException('Invalid scope.');
        }

        if (!$this->isValidId($id)) {
            throw new \InvalidArgumentException('Invalid target id.');
        }

        $allowedKeys = $this->allowedKeysForTarget($scope, $id);
        if ($allowedKeys === []) {
            throw new \InvalidArgumentException('Unknown target.');
        }

        $sensitiveKeys = $this->sensitiveKeysForTarget($scope, $id);

        $normalized = [];
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (!in_array($key, $allowedKeys, true)) {
                throw new \InvalidArgumentException('Unknown env key: ' . $key);
            }
            if (!is_string($value)) {
                $value = '';
            }
            $trimmed = trim($value);
            if ($trimmed === '' && in_array($key, $sensitiveKeys, true)) {
                continue;
            }
            $normalized[$key] = $trimmed;
        }

        $all = $this->loadValues();
        if (!isset($all['values']) || !is_array($all['values'])) {
            $all['values'] = [];
        }
        if (!isset($all['values'][$scope]) || !is_array($all['values'][$scope])) {
            $all['values'][$scope] = [];
        }
        if (!isset($all['values'][$scope][$id]) || !is_array($all['values'][$scope][$id])) {
            $all['values'][$scope][$id] = [];
        }

        foreach ($allowedKeys as $allowedKey) {
            if (array_key_exists($allowedKey, $normalized)) {
                $all['values'][$scope][$id][$allowedKey] = $normalized[$allowedKey];
            }
        }

        $this->saveValues($all);
        $this->valuesCache = null;
    }

    public function getValuesFileDisplayPath(): string
    {
        return 'var/starter/env.yaml';
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
    private function loadValues(): array
    {
        if ($this->valuesCache !== null) {
            return $this->valuesCache;
        }

        if (!is_file($this->valuesFilePath)) {
            $this->valuesCache = [];

            return $this->valuesCache;
        }

        $parsed = Yaml::parseFile($this->valuesFilePath);
        if (!is_array($parsed)) {
            $this->valuesCache = [];

            return $this->valuesCache;
        }

        $this->valuesCache = $parsed;

        return $this->valuesCache;
    }

    /**
     * @param array<string, mixed> $all
     */
    private function saveValues(array $all): void
    {
        $dir = dirname($this->valuesFilePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create starter env directory.');
            }
        }

        $tmpPath = $this->valuesFilePath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $yaml = Yaml::dump($all, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $handle = fopen($tmpPath, 'cb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open temp env file.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock env file.');
            }

            ftruncate($handle, 0);
            $written = fwrite($handle, $yaml);
            if ($written === false) {
                throw new \RuntimeException('Cannot write env file.');
            }

            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!rename($tmpPath, $this->valuesFilePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Cannot save env file.');
        }
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $storedRoot
     *
     * @return array{
     *     scope: string,
     *     id: string,
     *     label: string,
     *     description: string,
     *     variables: list<array{
     *         key: string,
     *         label: string,
     *         description: string,
     *         sensitive: bool,
     *         default: string,
     *         runtime: string,
     *         stored: string|null,
     *         effective: string
     *     }>
     * }|null
     */
    private function buildGroup(string $scope, string $id, array $meta, array $storedRoot): ?array
    {
        $label = $meta['label'] ?? $id;
        if (!is_string($label)) {
            $label = $id;
        }

        $description = $meta['description'] ?? '';
        if (!is_string($description)) {
            $description = '';
        }

        $rawVariables = $meta['variables'] ?? [];
        if (!is_array($rawVariables)) {
            $rawVariables = [];
        }

        $storedScope = $storedRoot['values'][$scope][$id] ?? [];
        if (!is_array($storedScope)) {
            $storedScope = [];
        }

        $variables = [];
        foreach ($rawVariables as $rawVar) {
            if (!is_array($rawVar)) {
                continue;
            }

            $key = $rawVar['key'] ?? '';
            if (!is_string($key) || !$this->isValidEnvKey($key)) {
                continue;
            }

            $varLabel = $rawVar['label'] ?? $key;
            if (!is_string($varLabel)) {
                $varLabel = $key;
            }

            $varDescription = $rawVar['description'] ?? '';
            if (!is_string($varDescription)) {
                $varDescription = '';
            }

            $default = $rawVar['default'] ?? '';
            if (!is_string($default)) {
                $default = '';
            }

            $sensitive = false;
            if (array_key_exists('sensitive', $rawVar)) {
                $sensitive = (bool) $rawVar['sensitive'];
            }

            $runtime = $this->readRuntimeValue($key, $default);
            $stored = null;
            if (array_key_exists($key, $storedScope) && is_string($storedScope[$key])) {
                $stored = $storedScope[$key];
            }

            $effective = $runtime;
            if ($stored !== null && $stored !== '') {
                $effective = $stored;
            } elseif ($stored !== null && $stored === '' && array_key_exists($key, $storedScope)) {
                $effective = '';
            }

            $variables[] = [
                'key' => $key,
                'label' => $varLabel,
                'description' => $varDescription,
                'sensitive' => $sensitive,
                'default' => $default,
                'runtime' => $runtime,
                'stored' => $stored,
                'effective' => $effective,
            ];
        }

        return [
            'scope' => $scope,
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'variables' => $variables,
        ];
    }

    /**
     * @return list<string>
     */
    private function sensitiveKeysForTarget(string $scope, string $id): array
    {
        $registry = $this->loadRegistry();
        $scopeBlock = $registry[$scope] ?? [];
        if (!is_array($scopeBlock)) {
            return [];
        }

        $meta = $scopeBlock[$id] ?? null;
        if (!is_array($meta)) {
            return [];
        }

        $rawVariables = $meta['variables'] ?? [];
        if (!is_array($rawVariables)) {
            return [];
        }

        $keys = [];
        foreach ($rawVariables as $rawVar) {
            if (!is_array($rawVar)) {
                continue;
            }
            $key = $rawVar['key'] ?? '';
            if (!is_string($key) || !$this->isValidEnvKey($key)) {
                continue;
            }
            $sensitive = false;
            if (array_key_exists('sensitive', $rawVar)) {
                $sensitive = (bool) $rawVar['sensitive'];
            }
            if ($sensitive) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function allowedKeysForTarget(string $scope, string $id): array
    {
        $registry = $this->loadRegistry();
        $scopeBlock = $registry[$scope] ?? [];
        if (!is_array($scopeBlock)) {
            return [];
        }

        $meta = $scopeBlock[$id] ?? null;
        if (!is_array($meta)) {
            return [];
        }

        $rawVariables = $meta['variables'] ?? [];
        if (!is_array($rawVariables)) {
            return [];
        }

        $keys = [];
        foreach ($rawVariables as $rawVar) {
            if (!is_array($rawVar)) {
                continue;
            }
            $key = $rawVar['key'] ?? '';
            if (is_string($key) && $this->isValidEnvKey($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function readRuntimeValue(string $key, string $default): string
    {
        $fromEnv = $_ENV[$key] ?? null;
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        $fromServer = $_SERVER[$key] ?? null;
        if (is_string($fromServer) && $fromServer !== '') {
            return $fromServer;
        }

        return $default;
    }

    private function isValidId(string $id): bool
    {
        if ($id === '') {
            return false;
        }

        return preg_match('/^[a-z0-9_-]+$/', $id) === 1;
    }

    private function isValidEnvKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        return preg_match('/^[A-Z][A-Z0-9_]*$/', $key) === 1;
    }
}
