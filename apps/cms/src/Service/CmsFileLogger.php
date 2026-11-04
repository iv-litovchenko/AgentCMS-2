<?php

namespace App\Service;

class CmsFileLogger
{
    private string $logFilePath;

    public function __construct(string $logFilePath)
    {
        $this->logFilePath = $logFilePath;
    }

    public function info(string $message): void
    {
        $this->append('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->append('ERROR', $message);
    }

    private function append(string $level, string $message): void
    {
        $line = sprintf(
            "[%s] %s: %s\n",
            gmdate('Y-m-d H:i:s'),
            $level,
            str_replace(["\r", "\n"], ' ', $message)
        );

        $dir = dirname($this->logFilePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                return;
            }
        }

        $handle = fopen($this->logFilePath, 'ab');
        if ($handle === false) {
            return;
        }

        try {
            if (flock($handle, LOCK_EX)) {
                fwrite($handle, $line);
                fflush($handle);
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }
}
