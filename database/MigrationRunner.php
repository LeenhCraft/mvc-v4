<?php

declare(strict_types=1);

namespace Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Migration Runner
 *
 * Handles execution of database migrations
 */
class MigrationRunner
{
    private string $migrationsPath;
    private string $migrationsTable;

    public function __construct(string $migrationsPath = null, string $migrationsTable = 'migrations')
    {
        $this->migrationsPath = $migrationsPath ?? __DIR__ . '/migrations';
        $this->migrationsTable = $migrationsTable;
    }

    /**
     * Run pending migrations
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();
        $executed = [];

        $pending = $this->getPendingMigrations();

        foreach ($pending as $migration) {
            $this->executeMigration($migration);
            $executed[] = $migration;
        }

        return $executed;
    }

    /**
     * Rollback last batch of migrations
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();
        $rolledBack = [];

        $lastBatch = $this->getLastBatch();
        if ($lastBatch === null) {
            return $rolledBack;
        }

        $migrations = Capsule::table($this->migrationsTable)
            ->where('batch', $lastBatch)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration->migration);
            $rolledBack[] = $migration->migration;
        }

        return $rolledBack;
    }

    /**
     * Reset all migrations (rollback all)
     */
    public function reset(): array
    {
        $this->ensureMigrationsTable();
        $rolledBack = [];

        $migrations = Capsule::table($this->migrationsTable)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration->migration);
            $rolledBack[] = $migration->migration;
        }

        return $rolledBack;
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();

        $status = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $status[] = [
                'migration' => $name,
                'ran' => in_array($name, $ran, true),
            ];
        }

        return $status;
    }

    private function ensureMigrationsTable(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable($this->migrationsTable)) {
            $schema->create($this->migrationsTable, function (Blueprint $table): void {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });
        }
    }

    private function getPendingMigrations(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();

        $pending = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!in_array($name, $ran, true)) {
                $pending[] = $name;
            }
        }

        return $pending;
    }

    private function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.php');
        sort($files);

        return $files;
    }

    private function getRanMigrations(): array
    {
        return Capsule::table($this->migrationsTable)
            ->pluck('migration')
            ->toArray();
    }

    private function getLastBatch(): ?int
    {
        $batch = Capsule::table($this->migrationsTable)
            ->max('batch');

        return $batch !== null ? (int)$batch : null;
    }

    private function getNextBatch(): int
    {
        $lastBatch = $this->getLastBatch();
        return $lastBatch !== null ? $lastBatch + 1 : 1;
    }

    private function executeMigration(string $name): void
    {
        $file = $this->migrationsPath . '/' . $name . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$file}");
        }

        // Include migration file and get migration instance
        $migration = require $file;

        if (!is_callable($migration)) {
            throw new \RuntimeException("Migration must return a callable: {$name}");
        }

        // Execute up migration
        $migration('up');

        // Record migration
        Capsule::table($this->migrationsTable)->insert([
            'migration' => $name,
            'batch' => $this->getNextBatch(),
        ]);
    }

    private function rollbackMigration(string $name): void
    {
        $file = $this->migrationsPath . '/' . $name . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$file}");
        }

        // Include migration file and get migration instance
        $migration = require $file;

        if (!is_callable($migration)) {
            throw new \RuntimeException("Migration must return a callable: {$name}");
        }

        // Execute down migration
        $migration('down');

        // Remove migration record
        Capsule::table($this->migrationsTable)
            ->where('migration', $name)
            ->delete();
    }
}
