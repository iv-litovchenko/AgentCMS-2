<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Yaml;

class UiMenuService
{
    private string $configPath;

    private UrlGeneratorInterface $urlGenerator;

    private RequestStack $requestStack;

    /** @var array<string, mixed>|null */
    private ?array $configCache = null;

    public function __construct(string $uiMenuConfigPath, UrlGeneratorInterface $urlGenerator, RequestStack $requestStack)
    {
        $this->configPath = $uiMenuConfigPath;
        $this->urlGenerator = $urlGenerator;
        $this->requestStack = $requestStack;
    }

    /**
     * @return array{display_name: string, email: string, initials: string}
     */
    public function getProfile(): array
    {
        $config = $this->loadConfig();
        $profile = $config['profile'] ?? [];
        if (!is_array($profile)) {
            $profile = [];
        }

        $displayName = $profile['display_name'] ?? '';
        if (!is_string($displayName)) {
            $displayName = '';
        }

        $email = $profile['email'] ?? '';
        if (!is_string($email)) {
            $email = '';
        }

        $initials = $profile['initials'] ?? '';
        if (!is_string($initials)) {
            $initials = '';
        }

        return [
            'display_name' => $displayName,
            'email' => $email,
            'initials' => $initials,
        ];
    }

    /**
     * @return list<array{type: string, label: string, url: string, active: bool, disabled: bool}>
     */
    public function getZoneItems(string $zone): array
    {
        $config = $this->loadConfig();
        $zones = $config['zones'] ?? [];
        if (!is_array($zones)) {
            return [];
        }

        $zoneConfig = $zones[$zone] ?? null;
        if (!is_array($zoneConfig)) {
            return [];
        }

        $rawItems = $zoneConfig['items'] ?? [];
        if (!is_array($rawItems)) {
            return [];
        }

        $currentRoute = $this->getCurrentRoute();
        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $normalized = $this->normalizeItem($rawItem, $currentRoute);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        return $items;
    }

    /**
     * @return list<array{label: string, name: string, url: string, active: bool}>
     */
    public function getAppItems(): array
    {
        $config = $this->loadConfig();
        $apps = $config['apps'] ?? [];
        if (!is_array($apps)) {
            return [];
        }

        $rawItems = $apps['items'] ?? [];
        if (!is_array($rawItems)) {
            return [];
        }

        $currentRoute = $this->getCurrentRoute();
        $currentAppName = $this->getCurrentAppName();
        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $label = $rawItem['label'] ?? '';
            if (!is_string($label)) {
                $label = '';
            }

            $name = $rawItem['name'] ?? '';
            if (!is_string($name) || !$this->isValidAppName($name)) {
                continue;
            }

            $url = $this->urlGenerator->generate('app_show', ['name' => $name]);
            $defaultSection = $this->getDefaultAppSectionSlugFromRaw($rawItem);
            if ($defaultSection !== null) {
                $url = $this->urlGenerator->generate('app_section', [
                    'name' => $name,
                    'section' => $defaultSection,
                ]);
            }

            $active = false;
            if ($currentAppName === $name) {
                if ($currentRoute === 'app_show' || $currentRoute === 'app_section') {
                    $active = true;
                }
            }

            $items[] = [
                'label' => $label,
                'name' => $name,
                'url' => $url,
                'active' => $active,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{label: string, slug: string, url: string, active: bool}>
     */
    public function getAppSections(string $appName): array
    {
        if (!$this->isValidAppName($appName)) {
            return [];
        }

        $rawApp = $this->findRawAppByName($appName);
        if ($rawApp === null) {
            return [];
        }

        $rawSections = $rawApp['sections'] ?? [];
        if (!is_array($rawSections)) {
            return [];
        }

        $currentRoute = $this->getCurrentRoute();
        $currentAppName = $this->getCurrentAppName();
        $currentSection = $this->getCurrentAppSection();

        $sections = [];
        foreach ($rawSections as $rawSection) {
            if (!is_array($rawSection)) {
                continue;
            }

            $label = $rawSection['label'] ?? '';
            if (!is_string($label)) {
                $label = '';
            }

            $slug = $rawSection['slug'] ?? '';
            if (!is_string($slug) || !$this->isValidSectionSlug($slug)) {
                continue;
            }

            $url = $this->urlGenerator->generate('app_section', [
                'name' => $appName,
                'section' => $slug,
            ]);

            $active = false;
            if ($currentRoute === 'app_section' && $currentAppName === $appName && $currentSection === $slug) {
                $active = true;
            }

            $sections[] = [
                'label' => $label,
                'slug' => $slug,
                'url' => $url,
                'active' => $active,
            ];
        }

        return $sections;
    }

    public function getDefaultAppSectionSlug(string $appName): ?string
    {
        $rawApp = $this->findRawAppByName($appName);
        if ($rawApp === null) {
            return null;
        }

        return $this->getDefaultAppSectionSlugFromRaw($rawApp);
    }

    /**
     * @return array{label: string, slug: string}|null
     */
    public function findAppSection(string $appName, string $sectionSlug): ?array
    {
        foreach ($this->getAppSections($appName) as $section) {
            if ($section['slug'] === $sectionSlug) {
                return [
                    'label' => $section['label'],
                    'slug' => $section['slug'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{label: string, name: string, hide_apps_nav: bool}|null
     */
    public function findAppByName(string $name): ?array
    {
        if (!$this->isValidAppName($name)) {
            return null;
        }

        $rawApp = $this->findRawAppByName($name);
        if ($rawApp === null) {
            return null;
        }

        $label = $rawApp['label'] ?? '';
        if (!is_string($label)) {
            $label = '';
        }

        $hideAppsNav = $rawApp['hide_apps_nav'] ?? false;
        if (!is_bool($hideAppsNav)) {
            $hideAppsNav = false;
        }

        return [
            'label' => $label,
            'name' => $name,
            'hide_apps_nav' => $hideAppsNav,
        ];
    }

    public function hasActiveApp(): bool
    {
        foreach ($this->getAppItems() as $item) {
            if ($item['active']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $rawItem
     *
     * @return array{type: string, label: string, url: string, active: bool, disabled: bool}|null
     */
    private function normalizeItem(array $rawItem, string $currentRoute): ?array
    {
        if (!empty($rawItem['divider'])) {
            return [
                'type' => 'divider',
                'label' => '',
                'url' => '',
                'active' => false,
                'disabled' => false,
            ];
        }

        $label = $rawItem['label'] ?? '';
        if (!is_string($label)) {
            $label = '';
        }

        $enabled = $rawItem['enabled'] ?? true;
        if (!is_bool($enabled)) {
            $enabled = true;
        }

        $route = $rawItem['route'] ?? '';
        if (!is_string($route)) {
            $route = '';
        }

        $routeParams = $rawItem['route_params'] ?? [];
        if (!is_array($routeParams)) {
            $routeParams = [];
        }

        $url = '';
        if ($enabled && $route !== '') {
            $url = $this->urlGenerator->generate($route, $routeParams);
        }

        $activeRoutes = $rawItem['active_routes'] ?? [];
        if (!is_array($activeRoutes)) {
            $activeRoutes = [];
        }

        $active = false;
        if ($currentRoute !== '' && in_array($currentRoute, $activeRoutes, true)) {
            $active = true;
        }

        $disabled = false;
        if (!$enabled) {
            $disabled = true;
        }

        return [
            'type' => 'link',
            'label' => $label,
            'url' => $url,
            'active' => $active,
            'disabled' => $disabled,
        ];
    }

    private function getCurrentRoute(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        $route = $request->attributes->get('_route');
        if (!is_string($route)) {
            return '';
        }

        return $route;
    }

    private function getCurrentAppName(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        $name = $request->attributes->get('name');
        if (!is_string($name)) {
            return '';
        }

        return $name;
    }

    private function getCurrentAppSection(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        $section = $request->attributes->get('section');
        if (!is_string($section)) {
            return '';
        }

        return $section;
    }

    /**
     * @param array<string, mixed> $rawApp
     */
    private function getDefaultAppSectionSlugFromRaw(array $rawApp): ?string
    {
        $rawSections = $rawApp['sections'] ?? [];
        if (!is_array($rawSections) || count($rawSections) === 0) {
            return null;
        }

        $first = $rawSections[0];
        if (!is_array($first)) {
            return null;
        }

        $slug = $first['slug'] ?? '';
        if (!is_string($slug) || !$this->isValidSectionSlug($slug)) {
            return null;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRawAppByName(string $name): ?array
    {
        $config = $this->loadConfig();
        $apps = $config['apps'] ?? [];
        if (!is_array($apps)) {
            return null;
        }

        $rawItems = $apps['items'] ?? [];
        if (!is_array($rawItems)) {
            return null;
        }

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $itemName = $rawItem['name'] ?? '';
            if (!is_string($itemName)) {
                continue;
            }

            if ($itemName === $name) {
                return $rawItem;
            }
        }

        return null;
    }

    private function isValidAppName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9_-]+$/', $name);
    }

    private function isValidSectionSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9_-]+$/', $slug);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        if (!is_file($this->configPath)) {
            $this->configCache = [];

            return $this->configCache;
        }

        $content = file_get_contents($this->configPath);
        if ($content === false) {
            $this->configCache = [];

            return $this->configCache;
        }

        try {
            $parsed = Yaml::parse($content);
        } catch (\Exception $exception) {
            $this->configCache = [];

            return $this->configCache;
        }

        if (!is_array($parsed)) {
            $this->configCache = [];

            return $this->configCache;
        }

        $this->configCache = $parsed;

        return $this->configCache;
    }
}
