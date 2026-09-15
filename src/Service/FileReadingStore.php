<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class FileReadingStore
{
    public function __construct(
        private readonly string $path
    ) {
    }

    public function isReady(): bool
    {
        try {
            $this->readAll();
            $directory = dirname($this->path);

            if (
                !is_dir($directory)
                && !mkdir($directory, 0775, true)
                && !is_dir($directory)
            ) {
                return false;
            }

            return is_writable($directory)
                && (!is_file($this->path) || is_writable($this->path));
        } catch (\Throwable) {
            return false;
        }
    }

    public function add(array $reading): array
    {
        $readings = $this->readAll();
        $readings[] = $reading;

        $this->writeAll($readings);

        return $reading;
    }

    public function listByDevice(string $deviceId): array
    {
        return array_values(array_filter(
            $this->readAll(),
            static fn (array $reading): bool =>
                $reading['device_id'] === $deviceId
        ));
    }

    public function deleteByDeviceAndId(
        string $deviceId,
        string $readingId
    ): bool {
        $readings = $this->readAll();
        $remaining = [];
        $deleted = false;

        foreach ($readings as $reading) {
            if (
                $reading['device_id'] === $deviceId
                && $reading['id'] === $readingId
            ) {
                $deleted = true;
                continue;
            }

            $remaining[] = $reading;
        }

        if ($deleted) {
            $this->writeAll($remaining);
        }

        return $deleted;
    }

    private function readAll(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Le fichier de stockage contient un JSON invalide.'
            );
        }

        return array_values(array_filter(
            $decoded,
            'is_array'
        ));
    }

    private function writeAll(array $readings): void
    {
        $directory = dirname($this->path);

        if (
            !is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Impossible de créer le dossier de stockage.'
            );
        }

        $encoded = json_encode(
            $readings,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if (
            $encoded === false
            || file_put_contents(
                $this->path,
                $encoded . PHP_EOL,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException(
                'Impossible d’écrire dans le fichier de stockage.'
            );
        }
    }
}