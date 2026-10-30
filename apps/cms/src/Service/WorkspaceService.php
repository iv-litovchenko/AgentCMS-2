<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Yaml\Yaml;

class WorkspaceService
{
    private string $workspacesDir;

    public function __construct(string $workspacesDir)
    {
        $this->workspacesDir = $workspacesDir;
    }

    /**
     * @return list<array{id: string, name: string, folder: string, location: string, description: string, avatar: string, sort_order: int, created_at: string, updated_at: string}>
     */
    public function list(): array
    {
        $this->ensureDirectory();

        $items = [];
        $pattern = $this->workspacesDir . '/*.yaml';
        $files = glob($pattern);
        if ($files === false) {
            return [];
        }

        foreach ($files as $filePath) {
            $workspace = $this->readFile($filePath);
            if ($workspace !== null) {
                $items[] = $workspace;
            }
        }

        usort($items, function (array $a, array $b): int {
            if ($a['sort_order'] !== $b['sort_order']) {
                if ($a['sort_order'] < $b['sort_order']) {
                    return -1;
                }

                return 1;
            }

            return strcmp($a['name'], $b['name']);
        });

        return $items;
    }

    /**
     * @return array{id: string, name: string, folder: string, location: string, description: string, avatar: string, sort_order: int, created_at: string, updated_at: string}|null
     */
    public function findById(string $id): ?array
    {
        if (!$this->isValidId($id)) {
            return null;
        }

        $path = $this->filePathForId($id);
        if (!is_file($path)) {
            return null;
        }

        return $this->readFile($path);
    }

    /**
     * @return array{id: string, name: string, slug: string, folder: string, location: string, description: string, avatar: string, sort_order: int, created_at: string, updated_at: string}|null
     */
    public function findBySlug(string $slug): ?array
    {
        if (!$this->isValidSlug($slug)) {
            return null;
        }

        foreach ($this->list() as $workspace) {
            if ($workspace['slug'] === $slug) {
                return $workspace;
            }
        }

        return null;
    }

    /**
     * @param array{name: string, folder: string, location: string, description?: string, sort_order?: int} $input
     *
     * @return array{id: string, name: string, folder: string, location: string, description: string, avatar: string, sort_order: int, created_at: string, updated_at: string}
     */
    public function create(array $input, ?UploadedFile $avatarFile = null): array
    {
        $this->ensureDirectory();

        $now = $this->nowIso();
        $id = $this->generateId();

        $avatar = '';
        if ($avatarFile !== null) {
            $avatar = $this->storeUploadedAvatar($id, $avatarFile);
        }

        $name = trim($input['name']);
        $slug = $this->ensureUniqueSlug($this->slugify($name), null);

        $workspace = [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'folder' => trim($input['folder']),
            'location' => $input['location'],
            'description' => trim($input['description'] ?? ''),
            'avatar' => $avatar,
            'sort_order' => $this->normalizeSortOrder($input['sort_order'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->writeWorkspace($workspace);

        return $workspace;
    }

    /**
     * @param array{name: string, folder: string, location: string, description?: string, sort_order?: int} $input
     *
     * @return array{id: string, name: string, folder: string, location: string, description: string, avatar: string, sort_order: int, created_at: string, updated_at: string}|null
     */
    public function update(string $id, array $input, ?UploadedFile $avatarFile = null): ?array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return null;
        }

        $existing['name'] = trim($input['name']);
        $existing['slug'] = $this->ensureUniqueSlug($this->slugify($existing['name']), $id);
        $existing['folder'] = trim($input['folder']);
        $existing['location'] = $input['location'];
        $existing['description'] = trim($input['description'] ?? '');
        $existing['sort_order'] = $this->normalizeSortOrder($input['sort_order'] ?? $existing['sort_order']);
        $existing['updated_at'] = $this->nowIso();

        if ($avatarFile !== null) {
            $previousAvatar = $existing['avatar'];
            $existing['avatar'] = $this->storeUploadedAvatar($id, $avatarFile);
            if ($previousAvatar !== '') {
                $this->removeAvatarFile($previousAvatar);
            }
        }

        $this->writeWorkspace($existing);

        return $existing;
    }

    public function delete(string $id): bool
    {
        if (!$this->isValidId($id)) {
            return false;
        }

        $path = $this->filePathForId($id);
        if (!is_file($path)) {
            return false;
        }

        $workspace = $this->readFile($path);
        if ($workspace !== null && $workspace['avatar'] !== '') {
            $this->removeAvatarFile($workspace['avatar']);
        }

        return unlink($path);
    }

    public function resolveAvatarPath(string $filename): ?string
    {
        if (!$this->isValidAvatarFilename($filename)) {
            return null;
        }

        $path = $this->avatarsDir() . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }

        return $path;
    }

    public function isValidAvatarFilename(string $filename): bool
    {
        if ($filename === '') {
            return false;
        }

        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9._-]+$/', $filename);
    }

    public function initialsFromName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name);
        if ($parts === false || count($parts) === 0) {
            return mb_strtoupper(mb_substr($name, 0, 1));
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        $first = mb_substr($parts[0], 0, 1);
        $second = mb_substr($parts[1], 0, 1);

        return mb_strtoupper($first . $second);
    }

    private function storeUploadedAvatar(string $workspaceId, UploadedFile $file): string
    {
        $this->ensureAvatarsDirectory();

        $extension = $file->guessExtension();
        if ($extension === null || $extension === '') {
            $extension = $file->getClientOriginalExtension();
        }
        if ($extension === null || $extension === '') {
            $extension = 'jpg';
        }

        $extension = strtolower($extension);
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        if ($extension === null || $extension === '') {
            $extension = 'jpg';
        }

        do {
            $filename = $workspaceId . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
            $targetPath = $this->avatarsDir() . '/' . $filename;
        } while (is_file($targetPath));

        $file->move($this->avatarsDir(), $filename);

        return $filename;
    }

    private function removeAvatarFile(string $filename): void
    {
        if (!$this->isValidAvatarFilename($filename)) {
            return;
        }

        $path = $this->avatarsDir() . '/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function avatarsDir(): string
    {
        return $this->workspacesDir . '/avatars';
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->workspacesDir)) {
            mkdir($this->workspacesDir, 0775, true);
        }
    }

    private function ensureAvatarsDirectory(): void
    {
        $this->ensureDirectory();
        if (!is_dir($this->avatarsDir())) {
            mkdir($this->avatarsDir(), 0775, true);
        }
    }

    private function filePathForId(string $id): string
    {
        return $this->workspacesDir . '/' . $id . '.yaml';
    }

    private function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $id);
    }

    private function generateId(): string
    {
        do {
            $id = bin2hex(random_bytes(8));
            $path = $this->filePathForId($id);
        } while (is_file($path));

        return $id;
    }

    private function nowIso(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
    }

    /**
     * @return array{id: string, name: string, folder: string, location: string, description: string, avatar: string, created_at: string, updated_at: string}|null
     */
    private function readFile(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        try {
            $parsed = Yaml::parse($content);
        } catch (\Exception $exception) {
            return null;
        }

        if (!is_array($parsed)) {
            return null;
        }

        return $this->normalizeRecord($parsed, basename($filePath, '.yaml'));
    }

    /**
     * @param array<string, mixed> $parsed
     *
     * @return array{id: string, name: string, folder: string, location: string, description: string, avatar: string, created_at: string, updated_at: string}|null
     */
    private function normalizeRecord(array $parsed, string $fallbackId): ?array
    {
        $id = $parsed['id'] ?? $fallbackId;
        if (!is_string($id) || !$this->isValidId($id)) {
            return null;
        }

        $name = $parsed['name'] ?? '';
        $folder = $parsed['folder'] ?? '';
        $location = $parsed['location'] ?? '';
        if (!is_string($name) || !is_string($folder) || !is_string($location)) {
            return null;
        }

        if ($location !== 'local' && $location !== 'server') {
            return null;
        }

        $description = $parsed['description'] ?? '';
        if (!is_string($description)) {
            $description = '';
        }

        $avatar = $parsed['avatar'] ?? '';
        if (!is_string($avatar)) {
            $avatar = '';
        }
        if ($avatar !== '' && !$this->isValidAvatarFilename($avatar)) {
            $avatar = '';
        }

        $sortOrder = $this->normalizeSortOrder($parsed['sort_order'] ?? 0);

        $slug = $parsed['slug'] ?? '';
        if (!is_string($slug) || !$this->isValidSlug($slug)) {
            $slug = $this->slugify($name);
        }

        $createdAt = $parsed['created_at'] ?? '';
        $updatedAt = $parsed['updated_at'] ?? '';
        if (!is_string($createdAt)) {
            $createdAt = $this->nowIso();
        }
        if (!is_string($updatedAt)) {
            $updatedAt = $createdAt;
        }

        return [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'folder' => $folder,
            'location' => $location,
            'description' => $description,
            'avatar' => $avatar,
            'sort_order' => $sortOrder,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    private function slugify(string $name): string
    {
        $text = $this->transliterate($name);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        if ($text === null) {
            $text = '';
        }
        $text = trim($text, '-');
        if ($text === '') {
            $text = 'workspace';
        }

        return $text;
    }

    private function ensureUniqueSlug(string $baseSlug, ?string $excludeId): string
    {
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . (string) $suffix;
            $suffix = $suffix + 1;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?string $excludeId): bool
    {
        foreach ($this->list() as $workspace) {
            if ($excludeId !== null && $workspace['id'] === $excludeId) {
                continue;
            }
            if ($workspace['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }

    private function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    private function transliterate(string $text): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'E',
            'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M',
            'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
            'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        ];

        $length = mb_strlen($text, 'UTF-8');
        $result = '';
        for ($i = 0; $i < $length; $i = $i + 1) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            if (isset($map[$char])) {
                $result = $result . $map[$char];
                continue;
            }
            $result = $result . $char;
        }

        return $result;
    }

    private function normalizeSortOrder(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param array{id: string, name: string, folder: string, location: string, description: string, avatar: string, sort_order: int, created_at: string, updated_at: string} $workspace
     */
    private function writeWorkspace(array $workspace): void
    {
        $this->ensureDirectory();

        $id = $workspace['id'];
        if (!$this->isValidId($id)) {
            throw new \InvalidArgumentException('Invalid workspace id.');
        }

        $path = $this->filePathForId($id);
        $tmpPath = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));

        $yaml = Yaml::dump($workspace, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $handle = fopen($tmpPath, 'cb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open temp workspace file.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock workspace file.');
            }

            ftruncate($handle, 0);
            $written = fwrite($handle, $yaml);
            if ($written === false) {
                throw new \RuntimeException('Cannot write workspace file.');
            }

            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Cannot save workspace file.');
        }
    }
}
