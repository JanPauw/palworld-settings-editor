<?php

namespace JanPauw\PalworldSettingsEditor\Services;

use App\Repositories\Daemon\DaemonFileRepository;
use Exception;

class PalworldSettingsFileService
{
    public function __construct(
        private readonly DaemonFileRepository $fileRepository,
    ) {
    }

    public function exists(mixed $server, string $path): bool
    {
        try {
            $content = $this->fileRepository->setServer($server)->getContent($path);

            return is_string($content);
        } catch (Exception) {
            return false;
        }
    }

    public function read(mixed $server, string $path): string
    {
        return $this->fileRepository->setServer($server)->getContent($path);
    }

    public function write(mixed $server, string $path, string $contents): void
    {
        $response = $this->fileRepository->setServer($server)->putContent($path, $contents);

        if ($response->failed()) {
            throw new Exception('Failed to write Palworld settings file.');
        }
    }

    public function copy(mixed $server, string $from, string $to): void
    {
        $contents = $this->read($server, $from);

        $this->write($server, $to, $contents);
    }

    /**
     * List timestamped backups of the settings file (named "<file>.bak-...")
     * that live in the same directory as the settings file.
     *
     * @return array<int, array{name: string, path: string, size: mixed, modified: mixed}>
     */
    public function listBackups(mixed $server, string $settingsPath): array
    {
        $directory = $this->directoryOf($settingsPath);
        $prefix = basename($settingsPath) . '.bak-';

        try {
            $entries = $this->fileRepository->setServer($server)->getDirectory($directory === '' ? '/' : $directory);
        } catch (Exception) {
            return [];
        }

        $backups = [];

        foreach ($entries as $entry) {
            $name = (string) data_get($entry, 'name');

            if ($name === '' || ! str_starts_with($name, $prefix)) {
                continue;
            }

            if ((bool) data_get($entry, 'directory', false)) {
                continue;
            }

            $backups[] = [
                'name' => $name,
                'path' => ($directory === '' ? '' : $directory . '/') . $name,
                'size' => data_get($entry, 'size'),
                'modified' => data_get($entry, 'modified') ?? data_get($entry, 'modified_at'),
            ];
        }

        // Newest first — the Ymd-His suffix sorts chronologically.
        usort($backups, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $backups;
    }

    public function deleteBackup(mixed $server, string $settingsPath, string $backupName): void
    {
        $directory = $this->directoryOf($settingsPath);

        $response = $this->fileRepository
            ->setServer($server)
            ->deleteFiles($directory === '' ? null : $directory, [$backupName]);

        if ($response->failed()) {
            throw new Exception('Failed to delete the backup file.');
        }
    }

    private function directoryOf(string $path): string
    {
        $position = strrpos($path, '/');

        return $position === false ? '' : substr($path, 0, $position);
    }

    public function getOptionSettingsLine(string $contents): ?string
    {
        foreach (preg_split("/\\r\\n|\\n|\\r/", $contents) ?: [] as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, 'OptionSettings=')) {
                return $trimmed;
            }
        }

        return null;
    }
}
