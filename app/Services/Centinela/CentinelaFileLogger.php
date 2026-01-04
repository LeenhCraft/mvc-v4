<?php

declare(strict_types=1);

namespace App\Services\Centinela;

use DateTimeImmutable;

final class CentinelaFileLogger
{
    private CentinelaConfig $config;

    public function __construct(CentinelaConfig $config)
    {
        $this->config = $config;
    }

    public function log(array $payload): void
    {
        if (!$this->config->isFileEnabled()) {
            return;
        }

        $dir = $this->config->getDir();

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $fileName = $this->generateFileName($payload['id'] ?? 'unknown');
        $filePath = rtrim($dir, "\\/" . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if ($json === false) {
            return;
        }

        @file_put_contents($filePath, $json . PHP_EOL, LOCK_EX);
    }

    private function generateFileName(string $id): string
    {
        $ts = (new DateTimeImmutable())->format('Ymd_His_u');
        return $ts . '_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id) . '.json';
    }
}
