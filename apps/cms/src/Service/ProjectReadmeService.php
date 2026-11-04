<?php

namespace App\Service;

class ProjectReadmeService
{
    private string $readmePath;

    public function __construct(string $readmePath)
    {
        $this->readmePath = $readmePath;
    }

    public function getContent(): string
    {
        if (!is_file($this->readmePath) || !is_readable($this->readmePath)) {
            return '';
        }

        $content = file_get_contents($this->readmePath);
        if ($content === false) {
            return '';
        }

        return $content;
    }

    public function isEmpty(): bool
    {
        return trim($this->getContent()) === '';
    }

    public function getDisplayPath(): string
    {
        return 'README.md';
    }
}
