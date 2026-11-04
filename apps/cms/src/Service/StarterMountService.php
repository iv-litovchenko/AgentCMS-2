<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class StarterMountService
{
    private string $registryPath;

    private string $mountsFilePath;

    private string $projectRoot;

    /** @var array<string, mixed>|null */
    private ?array $mountsCache = null;

    public function __construct(string $registryPath, string $mountsFilePath, string $projectRoot)
    {
        $this->registryPath = $registryPath;
        $this->mountsFilePath = $mountsFilePath;
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     host_path: string,
     *     container_path: string,
     *     read_only: bool
     * }>
     */
    public function listMounts(): array
    {
        $data = $this->loadMountsFile();
        $rawItems = $data['items'] ?? [];
        if (!is_array($rawItems)) {
            return $this->defaultMounts();
        }

        if ($rawItems === []) {
            return $this->defaultMounts();
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }
            $normalized = $this->normalizeMountItem($rawItem);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        if ($items === []) {
            return $this->defaultMounts();
        }

        return $items;
    }

    /**
     * @return list<array{id: string, label: string, host_path: string, container_path: string}>
     */
    public function listPresetsForPlatform(string $platform): array
    {
        $registry = $this->loadRegistry();
        $presetsRoot = $registry['presets'] ?? [];
        if (!is_array($presetsRoot)) {
            return [];
        }

        $platformKey = $this->normalizePlatformKey($platform);
        $platformPresets = $presetsRoot[$platformKey] ?? [];
        if (!is_array($platformPresets)) {
            return [];
        }

        $items = [];
        foreach ($platformPresets as $presetId => $preset) {
            if (!is_string($presetId) || !is_array($preset)) {
                continue;
            }
            if (!$this->isValidId($presetId)) {
                continue;
            }

            $label = $preset['label'] ?? $presetId;
            if (!is_string($label)) {
                $label = $presetId;
            }

            $hostPath = $preset['host_path'] ?? '';
            if (!is_string($hostPath)) {
                $hostPath = '';
            }

            $containerPath = $preset['container_path'] ?? '';
            if (!is_string($containerPath)) {
                $containerPath = '';
            }

            if ($containerPath === '') {
                $prefix = $registry['default_container_prefix'] ?? '/var/www/mounts';
                if (!is_string($prefix)) {
                    $prefix = '/var/www/mounts';
                }
                $containerPath = rtrim($prefix, '/') . '/' . $presetId;
            }

            $items[] = [
                'id' => $presetId,
                'label' => $label,
                'host_path' => $hostPath,
                'container_path' => $containerPath,
            ];
        }

        return $items;
    }

    public function resolveHostPlatform(): string
    {
        $fromEnv = $_ENV['APP_HOST_PLATFORM'] ?? '';
        if (!is_string($fromEnv)) {
            $fromEnv = '';
        }

        return $this->normalizePlatformKey($fromEnv);
    }

    public function addPresetMount(string $presetId): void
    {
        if (!$this->isValidId($presetId)) {
            throw new \InvalidArgumentException('Invalid preset id.');
        }

        $platform = $this->resolveHostPlatform();
        $found = null;
        foreach ($this->listPresetsForPlatform($platform) as $preset) {
            if ($preset['id'] === $presetId) {
                $found = $preset;
                break;
            }
        }

        if ($found === null) {
            throw new \InvalidArgumentException('Unknown preset.');
        }

        $mounts = $this->listMounts();
        foreach ($mounts as $mount) {
            if ($mount['id'] === $presetId) {
                throw new \InvalidArgumentException('Mount already exists.');
            }
        }

        $mounts[] = [
            'id' => $presetId,
            'label' => $found['label'],
            'host_path' => $found['host_path'],
            'container_path' => $found['container_path'],
            'read_only' => false,
        ];

        $this->saveMounts($mounts);
    }

    public function addCustomMount(): void
    {
        $registry = $this->loadRegistry();
        $prefix = $registry['default_container_prefix'] ?? '/var/www/mounts';
        if (!is_string($prefix)) {
            $prefix = '/var/www/mounts';
        }

        $mounts = $this->listMounts();
        $index = 1;
        $id = 'custom_' . (string) $index;
        while ($this->hasMountId($mounts, $id)) {
            $index = $index + 1;
            $id = 'custom_' . (string) $index;
        }

        $mounts[] = [
            'id' => $id,
            'label' => 'Точка монтирования',
            'host_path' => '',
            'container_path' => rtrim($prefix, '/') . '/' . $id,
            'read_only' => false,
        ];

        $this->saveMounts($mounts);
    }

    /**
     * @param list<array{
     *     id: string,
     *     label: string,
     *     host_path: string,
     *     container_path: string,
     *     read_only: bool
     * }> $mounts
     */
    public function saveMountsList(array $mounts): void
    {
        $normalized = [];
        foreach ($mounts as $mount) {
            $item = $this->normalizeMountItem($mount);
            if ($item === null) {
                throw new \InvalidArgumentException('Invalid mount entry.');
            }
            $normalized[] = $item;
        }

        $this->saveMounts($normalized);
    }

    public function removeMount(string $id): void
    {
        if (!$this->isValidId($id)) {
            throw new \InvalidArgumentException('Invalid mount id.');
        }

        $mounts = $this->listMounts();
        $next = [];
        $removed = false;
        foreach ($mounts as $mount) {
            if ($mount['id'] === $id) {
                $removed = true;
                continue;
            }
            $next[] = $mount;
        }

        if (!$removed) {
            throw new \InvalidArgumentException('Mount not found.');
        }

        if ($next === []) {
            $next = $this->defaultMounts();
        }

        $this->saveMounts($next);
    }

    public function getMountsFileDisplayPath(): string
    {
        return 'var/starter/mounts.yaml';
    }

    public function getOverrideSnippetPath(): string
    {
        return 'docker-compose.override.starter-mounts.yml';
    }

    public function buildOverrideYaml(): string
    {
        $lines = [];
        $lines[] = '# Сгенерировано из Стартера (ENV → монтирования). Подключение:';
        $lines[] = '# docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.override.starter-mounts.yml up -d';
        $lines[] = '# или вручную скопируйте volumes в docker-compose.override.yml';
        $lines[] = 'services:';
        $lines[] = '  cms:';
        $lines[] = '    volumes:';

        $mounts = $this->listMounts();
        $hasVolume = false;
        foreach ($mounts as $mount) {
            $hostPath = trim($mount['host_path']);
            if ($hostPath === '') {
                continue;
            }
            $hasVolume = true;
            $suffix = '';
            if ($mount['read_only']) {
                $suffix = ':ro';
            }
            $lines[] = '      - ' . $hostPath . ':' . $mount['container_path'] . $suffix;
        }

        if (!$hasVolume) {
            $lines[] = '      # (нет заполненных host_path — добавьте пути и сохраните)';
        }

        return implode("\n", $lines) . "\n";
    }

    public function writeOverrideSnippetFile(): bool
    {
        $target = $this->projectRoot . '/docker-compose.override.starter-mounts.yml';
        $yaml = $this->buildOverrideYaml();
        $dir = dirname($target);
        if (!is_dir($dir)) {
            return false;
        }

        $tmpPath = $target . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $written = @file_put_contents($tmpPath, $yaml);
        if ($written === false) {
            @unlink($tmpPath);

            return false;
        }

        if (!@rename($tmpPath, $target)) {
            @unlink($tmpPath);

            return false;
        }

        return true;
    }

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     host_path: string,
     *     container_path: string,
     *     read_only: bool
     * }>
     */
    private function defaultMounts(): array
    {
        return [
            [
                'id' => 'host_mount',
                'label' => 'Папка на хосте',
                'host_path' => '',
                'container_path' => '/var/www/mounts/host',
                'read_only' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $rawItem
     *
     * @return array{
     *     id: string,
     *     label: string,
     *     host_path: string,
     *     container_path: string,
     *     read_only: bool
     * }|null
     */
    private function normalizeMountItem(array $rawItem): ?array
    {
        $id = $rawItem['id'] ?? '';
        if (!is_string($id) || !$this->isValidId($id)) {
            return null;
        }

        $label = $rawItem['label'] ?? $id;
        if (!is_string($label)) {
            $label = $id;
        }

        $hostPath = $rawItem['host_path'] ?? '';
        if (!is_string($hostPath)) {
            $hostPath = '';
        }

        $containerPath = $rawItem['container_path'] ?? '';
        if (!is_string($containerPath)) {
            $containerPath = '';
        }

        if ($containerPath === '' || !$this->isValidContainerPath($containerPath)) {
            return null;
        }

        $readOnly = false;
        if (array_key_exists('read_only', $rawItem)) {
            $readOnly = (bool) $rawItem['read_only'];
        }

        return [
            'id' => $id,
            'label' => $label,
            'host_path' => trim($hostPath),
            'container_path' => $containerPath,
            'read_only' => $readOnly,
        ];
    }

    /**
     * @param list<array{id: string}> $mounts
     */
    private function hasMountId(array $mounts, string $id): bool
    {
        foreach ($mounts as $mount) {
            if ($mount['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{
     *     id: string,
     *     label: string,
     *     host_path: string,
     *     container_path: string,
     *     read_only: bool
     * }> $mounts
     */
    private function saveMounts(array $mounts): void
    {
        $payload = ['items' => $mounts];
        $this->writeMountsFile($payload);
        $this->mountsCache = null;
        $this->writeOverrideSnippetFile();
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
    private function loadMountsFile(): array
    {
        if ($this->mountsCache !== null) {
            return $this->mountsCache;
        }

        if (!is_file($this->mountsFilePath)) {
            $this->mountsCache = [];

            return $this->mountsCache;
        }

        $parsed = Yaml::parseFile($this->mountsFilePath);
        if (!is_array($parsed)) {
            $this->mountsCache = [];

            return $this->mountsCache;
        }

        $this->mountsCache = $parsed;

        return $this->mountsCache;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeMountsFile(array $payload): void
    {
        $dir = dirname($this->mountsFilePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create starter mounts directory.');
            }
        }

        $tmpPath = $this->mountsFilePath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $yaml = Yaml::dump($payload, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $handle = fopen($tmpPath, 'cb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open temp mounts file.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock mounts file.');
            }

            ftruncate($handle, 0);
            $written = fwrite($handle, $yaml);
            if ($written === false) {
                throw new \RuntimeException('Cannot write mounts file.');
            }

            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!rename($tmpPath, $this->mountsFilePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Cannot save mounts file.');
        }
    }

    private function normalizePlatformKey(string $platform): string
    {
        $key = strtolower(trim($platform));
        if ($key === 'darwin') {
            return 'mac';
        }
        if ($key === 'win') {
            return 'windows';
        }
        if ($key === 'mac' || $key === 'linux' || $key === 'windows') {
            return $key;
        }
        if ($key === '') {
            return 'mac';
        }

        return $key;
    }

    private function isValidId(string $id): bool
    {
        if ($id === '') {
            return false;
        }

        return preg_match('/^[a-z0-9_-]+$/', $id) === 1;
    }

    private function isValidContainerPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] !== '/') {
            return false;
        }

        return preg_match('/[\r\n\0]/', $path) !== 1;
    }
}
