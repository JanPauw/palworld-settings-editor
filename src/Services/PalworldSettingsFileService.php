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
