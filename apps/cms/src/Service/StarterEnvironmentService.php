<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Kernel;

class StarterEnvironmentService
{
    private string $appVersion;

    public function __construct(string $appVersion)
    {
        $this->appVersion = $appVersion;
    }

    /**
     * @return array{
     *     docker: array{in_container: bool, runtime: string, stack: string},
     *     platform: array{host: string, container_os: string, php_os_family: string, machine: string},
     *     disk: array{path: string, total: string, free: string, total_bytes: int|false, free_bytes: int|false},
     *     technologies: list<array{name: string, version: string}>
     * }
     */
    public function buildReport(): array
    {
        $diskPath = '/';
        $totalBytes = @disk_total_space($diskPath);
        $freeBytes = @disk_free_space($diskPath);

        return [
            'docker' => $this->buildDockerInfo(),
            'platform' => $this->buildPlatformInfo(),
            'disk' => [
                'path' => $diskPath,
                'total' => $this->formatBytes($totalBytes),
                'free' => $this->formatBytes($freeBytes),
                'total_bytes' => $totalBytes,
                'free_bytes' => $freeBytes,
            ],
            'technologies' => $this->buildTechnologies(),
        ];
    }

    /**
     * @return array{in_container: bool, runtime: string, stack: string}
     */
    private function buildDockerInfo(): array
    {
        $inContainer = is_file('/.dockerenv');

        $runtime = 'Не Docker (локальный PHP)';
        if ($inContainer) {
            $runtime = 'Docker (контейнер CMS)';
        }

        $stack = 'agent-cms-light: proxy, cms, db, redis, mcp, api';

        return [
            'in_container' => $inContainer,
            'runtime' => $runtime,
            'stack' => $stack,
        ];
    }

    /**
     * @return array{host: string, container_os: string, php_os_family: string, machine: string}
     */
    private function buildPlatformInfo(): array
    {
        $host = $this->resolveHostPlatform();

        $containerOs = php_uname('s') . ' ' . php_uname('r');
        $machine = php_uname('m');

        return [
            'host' => $host,
            'container_os' => $containerOs,
            'php_os_family' => PHP_OS_FAMILY,
            'machine' => $machine,
        ];
    }

    private function resolveHostPlatform(): string
    {
        $fromEnv = $_ENV['APP_HOST_PLATFORM'] ?? '';
        if (!is_string($fromEnv)) {
            $fromEnv = '';
        }
        $fromEnv = strtolower(trim($fromEnv));

        if ($fromEnv === 'mac' || $fromEnv === 'darwin') {
            return 'macOS (хост)';
        }
        if ($fromEnv === 'linux') {
            return 'Linux (хост)';
        }
        if ($fromEnv === 'windows' || $fromEnv === 'win') {
            return 'Windows (хост)';
        }
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return 'Не указано — задайте APP_HOST_PLATFORM=mac|linux|windows в docker-compose';
    }

    /**
     * @return list<array{name: string, version: string}>
     */
    private function buildTechnologies(): array
    {
        $items = [];
        $items[] = ['name' => 'Agent CMS Light', 'version' => $this->appVersion];
        $items[] = ['name' => 'PHP', 'version' => PHP_VERSION];
        $items[] = ['name' => 'Symfony', 'version' => Kernel::VERSION];

        $postgresVersion = $this->fetchPostgresVersion();
        if ($postgresVersion !== null) {
            $items[] = ['name' => 'PostgreSQL', 'version' => $postgresVersion];
        } else {
            $items[] = ['name' => 'PostgreSQL', 'version' => 'недоступно'];
        }

        $redisVersion = $this->fetchRedisVersion();
        if ($redisVersion !== null) {
            $items[] = ['name' => 'Redis', 'version' => $redisVersion];
        } else {
            $items[] = ['name' => 'Redis', 'version' => 'недоступно'];
        }

        $items[] = ['name' => 'ext-intl', 'version' => $this->extensionVersion('intl')];
        $items[] = ['name' => 'ext-pdo_pgsql', 'version' => $this->extensionVersion('pdo_pgsql')];
        $items[] = ['name' => 'ext-redis', 'version' => $this->extensionVersion('redis')];
        $items[] = ['name' => 'ext-opcache', 'version' => $this->extensionVersion('opcache')];

        return $items;
    }

    private function extensionVersion(string $extension): string
    {
        if (!extension_loaded($extension)) {
            return 'не установлено';
        }

        $version = phpversion($extension);
        if ($version === false || $version === '') {
            return 'да';
        }

        return $version;
    }

    private function fetchPostgresVersion(): ?string
    {
        $databaseUrl = $_ENV['DATABASE_URL'] ?? '';
        if (!is_string($databaseUrl) || $databaseUrl === '') {
            return null;
        }

        $parts = parse_url($databaseUrl);
        if ($parts === false || !is_array($parts)) {
            return null;
        }

        $host = 'db';
        if (isset($parts['host']) && is_string($parts['host']) && $parts['host'] !== '') {
            $host = $parts['host'];
        }

        $port = 5432;
        if (isset($parts['port'])) {
            $port = (int) $parts['port'];
        }

        $user = '';
        if (isset($parts['user']) && is_string($parts['user'])) {
            $user = $parts['user'];
        }

        $pass = '';
        if (isset($parts['pass']) && is_string($parts['pass'])) {
            $pass = $parts['pass'];
        }

        $dbName = 'postgres';
        if (isset($parts['path']) && is_string($parts['path'])) {
            $path = trim($parts['path'], '/');
            if ($path !== '') {
                $dbName = $path;
            }
        }

        $dsn = 'pgsql:host=' . $host . ';port=' . (string) $port . ';dbname=' . $dbName;

        try {
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_TIMEOUT => 2,
            ]);
            $statement = $pdo->query('SELECT version()');
            if ($statement === false) {
                return null;
            }
            $value = $statement->fetchColumn();
            if (!is_string($value)) {
                return null;
            }

            return $this->shortenPostgresVersion($value);
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function shortenPostgresVersion(string $full): string
    {
        if (preg_match('/PostgreSQL\s+([0-9.]+)/', $full, $matches) === 1) {
            return 'PostgreSQL ' . $matches[1];
        }

        if (strlen($full) > 80) {
            return substr($full, 0, 77) . '...';
        }

        return $full;
    }

    private function fetchRedisVersion(): ?string
    {
        if (!extension_loaded('redis')) {
            return null;
        }

        $redisUrl = $_ENV['REDIS_URL'] ?? 'redis://redis:6379';
        if (!is_string($redisUrl)) {
            $redisUrl = 'redis://redis:6379';
        }

        $parts = parse_url($redisUrl);
        if ($parts === false || !is_array($parts)) {
            return null;
        }

        $host = 'redis';
        if (isset($parts['host']) && is_string($parts['host']) && $parts['host'] !== '') {
            $host = $parts['host'];
        }

        $port = 6379;
        if (isset($parts['port'])) {
            $port = (int) $parts['port'];
        }

        try {
            $redis = new \Redis();
            $connected = $redis->connect($host, $port, 2.0);
            if (!$connected) {
                return null;
            }
            $info = $redis->info('server');
            if (!is_array($info)) {
                return null;
            }
            if (isset($info['redis_version']) && is_string($info['redis_version'])) {
                return $info['redis_version'];
            }
        } catch (\Exception $exception) {
            return null;
        }

        return null;
    }

    /**
     * @param int|false $bytes
     */
    private function formatBytes($bytes): string
    {
        if ($bytes === false) {
            return '—';
        }

        if ($bytes < 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unitIndex = 0;
        while ($value >= 1024.0 && $unitIndex < count($units) - 1) {
            $value = $value / 1024.0;
            $unitIndex = $unitIndex + 1;
        }

        if ($unitIndex === 0) {
            return (string) (int) $value . ' ' . $units[$unitIndex];
        }

        return number_format($value, 1, '.', ' ') . ' ' . $units[$unitIndex];
    }
}
