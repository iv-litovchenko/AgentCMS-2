<?php

namespace App\Service;

use Composer\InstalledVersions;
use Symfony\Component\Yaml\Yaml;

class StarterToggleService
{
    private string $packagesRegistryPath;

    private string $menuConfigPath;

    private string $togglesFilePath;

    /** @var array<string, mixed>|null */
    private ?array $togglesCache = null;

    public function __construct(
        string $packagesRegistryPath,
        string $menuConfigPath,
        string $togglesFilePath
    ) {
        $this->packagesRegistryPath = $packagesRegistryPath;
        $this->menuConfigPath = $menuConfigPath;
        $this->togglesFilePath = $togglesFilePath;
    }

    public function isPackageEnabled(string $id): bool
    {
        foreach ($this->listPackages() as $package) {
            if ($package['id'] === $id) {
                return $package['enabled'];
            }
        }

        return false;
    }

    public function isServiceEnabled(string $id): bool
    {
        foreach ($this->listServices() as $service) {
            if ($service['id'] === $id) {
                return $service['enabled'];
            }
        }

        return false;
    }

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     composer_package: string,
     *     installed: bool,
     *     enabled: bool,
     *     can_toggle: bool
     * }>
     */
    public function listPackages(): array
    {
        $registry = $this->loadPackagesRegistry();
        $rawItems = $registry['items'] ?? [];
        if (!is_array($rawItems)) {
            return [];
        }

        $toggles = $this->loadToggles();
        $packageToggles = $toggles['packages'] ?? [];
        if (!is_array($packageToggles)) {
            $packageToggles = [];
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

            $composerPackage = $rawItem['composer_package'] ?? '';
            if (!is_string($composerPackage)) {
                $composerPackage = '';
            }

            $defaultEnabled = true;
            if (array_key_exists('default_enabled', $rawItem)) {
                $defaultEnabled = (bool) $rawItem['default_enabled'];
            }

            $installed = $this->isComposerPackageInstalled($composerPackage);
            $enabled = $defaultEnabled;
            if (array_key_exists($id, $packageToggles)) {
                $enabled = (bool) $packageToggles[$id];
            }

            if (!$installed) {
                $enabled = false;
            }

            $items[] = [
                'id' => $id,
                'label' => $label,
                'description' => $description,
                'composer_package' => $composerPackage,
                'installed' => $installed,
                'enabled' => $enabled,
                'can_toggle' => $installed,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     subdomain: string,
     *     compose_service: string,
     *     enabled: bool
     * }>
     */
    public function listServices(): array
    {
        $config = $this->loadMenuConfig();
        $services = $config['services'] ?? [];
        if (!is_array($services)) {
            return [];
        }

        $rawItems = $services['items'] ?? [];
        if (!is_array($rawItems)) {
            return [];
        }

        $toggles = $this->loadToggles();
        $serviceToggles = $toggles['services'] ?? [];
        if (!is_array($serviceToggles)) {
            $serviceToggles = [];
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $id = $rawItem['id'] ?? '';
            if (!is_string($id) || !$this->isValidId($id)) {
                $subdomain = $rawItem['subdomain'] ?? '';
                if (is_string($subdomain) && $subdomain !== '') {
                    $id = $subdomain;
                } else {
                    continue;
                }
            }

            $label = $rawItem['label'] ?? $id;
            if (!is_string($label)) {
                $label = $id;
            }

            $description = $rawItem['description'] ?? '';
            if (!is_string($description)) {
                $description = '';
            }

            $subdomain = $rawItem['subdomain'] ?? '';
            if (!is_string($subdomain)) {
                $subdomain = '';
            }

            $composeService = $rawItem['compose_service'] ?? $id;
            if (!is_string($composeService)) {
                $composeService = $id;
            }

            $defaultEnabled = true;
            if (array_key_exists('default_enabled', $rawItem)) {
                $defaultEnabled = (bool) $rawItem['default_enabled'];
            }

            $enabled = $defaultEnabled;
            if (array_key_exists($id, $serviceToggles)) {
                $enabled = (bool) $serviceToggles[$id];
            }

            $items[] = [
                'id' => $id,
                'label' => $label,
                'description' => $description,
                'subdomain' => $subdomain,
                'compose_service' => $composeService,
                'enabled' => $enabled,
            ];
        }

        return $items;
    }

    public function setPackageEnabled(string $id, bool $enabled): void
    {
        if (!$this->isValidId($id)) {
            throw new \InvalidArgumentException('Invalid package id.');
        }

        $found = false;
        foreach ($this->listPackages() as $package) {
            if ($package['id'] === $id) {
                $found = true;
                if (!$package['can_toggle']) {
                    throw new \InvalidArgumentException('Package is not installed.');
                }
                break;
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException('Unknown package.');
        }

        $toggles = $this->loadToggles();
        if (!isset($toggles['packages']) || !is_array($toggles['packages'])) {
            $toggles['packages'] = [];
        }

        $toggles['packages'][$id] = $enabled;
        $this->saveToggles($toggles);
        $this->togglesCache = null;
    }

    public function setServiceEnabled(string $id, bool $enabled): void
    {
        if (!$this->isValidId($id)) {
            throw new \InvalidArgumentException('Invalid service id.');
        }

        $found = false;
        foreach ($this->listServices() as $service) {
            if ($service['id'] === $id) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException('Unknown service.');
        }

        $toggles = $this->loadToggles();
        if (!isset($toggles['services']) || !is_array($toggles['services'])) {
            $toggles['services'] = [];
        }

        $toggles['services'][$id] = $enabled;
        $this->saveToggles($toggles);
        $this->togglesCache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPackagesRegistry(): array
    {
        if (!is_file($this->packagesRegistryPath)) {
            return [];
        }

        $parsed = Yaml::parseFile($this->packagesRegistryPath);
        if (!is_array($parsed)) {
            return [];
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadMenuConfig(): array
    {
        if (!is_file($this->menuConfigPath)) {
            return [];
        }

        $parsed = Yaml::parseFile($this->menuConfigPath);
        if (!is_array($parsed)) {
            return [];
        }

        return $parsed;
    }

    /**
     * @return array{packages?: array<string, bool>, services?: array<string, bool>}
     */
    private function loadToggles(): array
    {
        if ($this->togglesCache !== null) {
            return $this->togglesCache;
        }

        if (!is_file($this->togglesFilePath)) {
            $this->togglesCache = [];

            return $this->togglesCache;
        }

        $parsed = Yaml::parseFile($this->togglesFilePath);
        if (!is_array($parsed)) {
            $this->togglesCache = [];

            return $this->togglesCache;
        }

        $this->togglesCache = $parsed;

        return $this->togglesCache;
    }

    /**
     * @param array{packages?: array<string, bool>, services?: array<string, bool>} $toggles
     */
    private function saveToggles(array $toggles): void
    {
        $dir = dirname($this->togglesFilePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create starter toggles directory.');
            }
        }

        $tmpPath = $this->togglesFilePath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $yaml = Yaml::dump($toggles, 3, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $handle = fopen($tmpPath, 'cb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open temp toggles file.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock toggles file.');
            }

            ftruncate($handle, 0);
            $written = fwrite($handle, $yaml);
            if ($written === false) {
                throw new \RuntimeException('Cannot write toggles file.');
            }

            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!rename($tmpPath, $this->togglesFilePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Cannot save toggles file.');
        }
    }

    private function isComposerPackageInstalled(string $composerPackage): bool
    {
        if ($composerPackage === '') {
            return false;
        }

        if (!class_exists(InstalledVersions::class)) {
            return false;
        }

        return InstalledVersions::isInstalled($composerPackage);
    }

    private function isValidId(string $id): bool
    {
        if ($id === '') {
            return false;
        }

        return preg_match('/^[a-z0-9_-]+$/', $id) === 1;
    }
}
