<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class FooterQuoteService
{
    private string $registryPath;

    /** @var list<array{text: string, hold_ms: int}>|null */
    private ?array $itemsCache = null;

    public function __construct(string $registryPath)
    {
        $this->registryPath = $registryPath;
    }

    public function pickRandomText(): string
    {
        $items = $this->loadItems();
        if ($items === []) {
            return '';
        }

        $index = random_int(0, count($items) - 1);
        $picked = $items[$index];

        return $picked['text'];
    }

    /**
     * @return list<array{text: string, hold_ms: int}>
     */
    private function loadItems(): array
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        if (!is_file($this->registryPath)) {
            $this->itemsCache = [];

            return $this->itemsCache;
        }

        $parsed = Yaml::parseFile($this->registryPath);
        if (!is_array($parsed)) {
            $this->itemsCache = [];

            return $this->itemsCache;
        }

        $rawItems = $parsed['items'] ?? [];
        if (!is_array($rawItems)) {
            $this->itemsCache = [];

            return $this->itemsCache;
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $kind = $rawItem['kind'] ?? 'text';
            if (!is_string($kind)) {
                $kind = 'text';
            }

            $display = $this->resolveDisplayText($kind, $rawItem);
            if ($display === '') {
                continue;
            }

            $holdMs = 6500;
            if (array_key_exists('hold_ms', $rawItem)) {
                $holdMs = (int) $rawItem['hold_ms'];
            }
            if (array_key_exists('holdMs', $rawItem)) {
                $holdMs = (int) $rawItem['holdMs'];
            }

            $items[] = [
                'text' => $display,
                'hold_ms' => $holdMs,
            ];
        }

        $this->itemsCache = $items;

        return $this->itemsCache;
    }

    /**
     * @param array<string, mixed> $rawItem
     */
    private function resolveDisplayText(string $kind, array $rawItem): string
    {
        if ($kind === 'arc') {
            $parts = $rawItem['parts'] ?? [];
            if (!is_array($parts)) {
                return '';
            }

            $segments = [];
            foreach ($parts as $part) {
                if (!is_string($part)) {
                    continue;
                }
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $segments[] = $part;
            }

            if ($segments === []) {
                return '';
            }

            return implode(' · ', $segments);
        }

        $text = $rawItem['text'] ?? '';
        if (!is_string($text)) {
            return '';
        }

        return trim($text);
    }
}
