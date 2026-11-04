<?php

namespace App\Service;

class StarterLogJournalService
{
    private string $logDirectory;

    private int $maxLines;

    private int $maxBytesPerFile;

    public function __construct(string $logDirectory, int $maxLines, int $maxBytesPerFile)
    {
        $this->logDirectory = $logDirectory;
        $this->maxLines = $maxLines;
        $this->maxBytesPerFile = $maxBytesPerFile;
    }

    /**
     * @return array{
     *     lines: list<array{source: string, text: string}>,
     *     sources: list<string>
     * }
     */
    public function buildJournal(): array
    {
        $files = $this->resolveLogFiles();
        $chunks = [];

        foreach ($files as $filePath) {
            $source = basename($filePath);
            $lines = $this->readTailLines($filePath);
            foreach ($lines as $line) {
                $chunks[] = [
                    'source' => $source,
                    'text' => $line,
                ];
            }
        }

        if (count($chunks) > $this->maxLines) {
            $chunks = array_slice($chunks, -$this->maxLines);
        }

        $sources = [];
        foreach ($files as $filePath) {
            $sources[] = basename($filePath);
        }

        return [
            'lines' => $chunks,
            'sources' => $sources,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveLogFiles(): array
    {
        if (!is_dir($this->logDirectory)) {
            return [];
        }

        $pattern = $this->logDirectory . '/*.log';
        $files = glob($pattern);
        if ($files === false || $files === []) {
            return [];
        }

        usort($files, function (string $a, string $b): int {
            $mtimeA = @filemtime($a);
            $mtimeB = @filemtime($b);
            if ($mtimeA === false) {
                $mtimeA = 0;
            }
            if ($mtimeB === false) {
                $mtimeB = 0;
            }
            if ($mtimeA === $mtimeB) {
                return strcmp($a, $b);
            }
            if ($mtimeA < $mtimeB) {
                return -1;
            }

            return 1;
        });

        return $files;
    }

    /**
     * @return list<string>
     */
    private function readTailLines(string $filePath): array
    {
        if (!is_readable($filePath)) {
            return [];
        }

        $size = @filesize($filePath);
        if ($size === false || $size === 0) {
            return [];
        }

        $readFrom = 0;
        if ($size > $this->maxBytesPerFile) {
            $readFrom = $size - $this->maxBytesPerFile;
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return [];
        }

        if ($readFrom > 0) {
            fseek($handle, $readFrom);
        }

        $content = stream_get_contents($handle);
        fclose($handle);

        if ($content === false || $content === '') {
            return [];
        }

        $parts = preg_split("/\r\n|\n|\r/", $content);
        if ($parts === false) {
            return [];
        }

        $lines = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $lines[] = $part;
        }

        return $lines;
    }
}
