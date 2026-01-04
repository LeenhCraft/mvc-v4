<?php

declare(strict_types=1);

namespace App\Services\Centinela;

final class CentinelaConfig
{
    private bool $enabled;
    private bool $fileEnabled;
    private string $dir;
    private int $maxBodyBytes;
    private bool $dbEnabled;
    private string $dbTable;
    private bool $dbAutoMigrate;

    /** @var array<string, true> */
    private array $redactHeaders;

    /** @var array<string, true> */
    private array $redactResponseHeaders;

    public function __construct(array $config = [])
    {
        $this->enabled = (bool)($config['enabled'] ?? true);
        $this->fileEnabled = (bool)($config['file_enabled'] ?? true);

        $dir = (string)($config['dir'] ?? '');
        $dir = trim($dir);
        $this->dir = $dir !== '' ? $dir : $this->defaultDir();

        $this->maxBodyBytes = (int)($config['max_body_bytes'] ?? 16384);

        $db = is_array($config['db'] ?? null) ? (array)$config['db'] : [];
        $this->dbEnabled = (bool)($db['enabled'] ?? false);

        $dbTable = (string)($db['table'] ?? 'centinela_logs');
        $dbTable = trim($dbTable);
        $this->dbTable = $dbTable !== '' ? $dbTable : 'centinela_logs';

        $this->dbAutoMigrate = (bool)($db['auto_migrate'] ?? false);

        $this->redactHeaders = $this->buildRedactMap($config['redact_headers'] ?? ['authorization', 'cookie', 'set-cookie']);
        $this->redactResponseHeaders = $this->buildRedactMap($config['redact_response_headers'] ?? ['set-cookie']);
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, true>
     */
    private function buildRedactMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $h) {
            $h = strtolower(trim((string)$h));
            if ($h !== '') {
                $map[$h] = true;
            }
        }
        return $map;
    }

    private function defaultDir(): string
    {
        $root = dirname(__DIR__, 3);
        return $root . DIRECTORY_SEPARATOR . 'centinela';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isFileEnabled(): bool
    {
        return $this->fileEnabled;
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function getMaxBodyBytes(): int
    {
        return $this->maxBodyBytes;
    }

    public function isDbEnabled(): bool
    {
        return $this->dbEnabled;
    }

    public function getDbTable(): string
    {
        return $this->dbTable;
    }

    public function shouldAutoMigrate(): bool
    {
        return $this->dbAutoMigrate;
    }

    /**
     * @return array<string, true>
     */
    public function getRedactHeaders(): array
    {
        return $this->redactHeaders;
    }

    /**
     * @return array<string, true>
     */
    public function getRedactResponseHeaders(): array
    {
        return $this->redactResponseHeaders;
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'file_enabled' => $this->fileEnabled,
            'dir' => $this->dir,
            'max_body_bytes' => $this->maxBodyBytes,
            'redact_headers' => array_keys($this->redactHeaders),
            'redact_response_headers' => array_keys($this->redactResponseHeaders),
            'db' => [
                'enabled' => $this->dbEnabled,
                'table' => $this->dbTable,
                'auto_migrate' => $this->dbAutoMigrate,
            ],
        ];
    }
}
