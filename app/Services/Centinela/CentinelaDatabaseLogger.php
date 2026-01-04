<?php

declare(strict_types=1);

namespace App\Services\Centinela;

use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

final class CentinelaDatabaseLogger
{
    private CentinelaConfig $config;

    public function __construct(CentinelaConfig $config)
    {
        $this->config = $config;
    }

    public function log(array $payload): void
    {
        if (!$this->config->isDbEnabled()) {
            return;
        }

        $table = $this->config->getDbTable();

        try {
            if ($this->config->shouldAutoMigrate()) {
                $this->ensureTable($table);
            }

            $row = $this->buildDatabaseRow($payload);
            Capsule::table($table)->insert($row);
        } catch (\Throwable) {
            // Silently fail to avoid breaking the application
        }
    }

    private function buildDatabaseRow(array $payload): array
    {
        $req = is_array($payload['request'] ?? null) ? (array)$payload['request'] : [];
        $resp = is_array($payload['response'] ?? null) ? (array)$payload['response'] : null;
        $meta = is_array($payload['meta'] ?? null) ? (array)$payload['meta'] : [];

        $headersJson = json_encode($req['headers'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $decodedBodyJson = json_encode($req['decoded_body'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $uploadedFilesJson = json_encode($req['uploaded_files'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $routeJson = json_encode($req['route'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $queryParamsJson = json_encode($req['query_params'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $responseHeadersJson = json_encode(is_array($resp) ? ($resp['headers'] ?? null) : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $row = [
            'request_id' => (string)($payload['id'] ?? ''),
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'method' => (string)($req['method'] ?? ''),
            'uri' => (string)($req['uri'] ?? ''),
            'path' => (string)($req['path'] ?? ''),
            'query' => (string)($req['query'] ?? ''),
            'query_params_json' => $queryParamsJson !== false ? $queryParamsJson : null,
            'ip' => (string)($req['ip'] ?? ''),
            'user_agent' => (string)($req['user_agent'] ?? ''),
            'content_type' => (string)($req['content_type'] ?? ''),
            'content_length' => (string)($req['content_length'] ?? ''),
            'headers_json' => $headersJson !== false ? $headersJson : null,
            'body' => is_string($req['body'] ?? null) ? $req['body'] : null,
            'decoded_body_json' => $decodedBodyJson !== false ? $decodedBodyJson : null,
            'uploaded_files_json' => $uploadedFilesJson !== false ? $uploadedFilesJson : null,
            'route_json' => $routeJson !== false ? $routeJson : null,
            'status_code' => is_array($resp) ? (int)($resp['status'] ?? 0) : null,
            'duration_ms' => isset($meta['duration_ms']) ? (int)$meta['duration_ms'] : null,
        ];

        // Add response_headers_json column if it exists in the table
        $schema = Capsule::schema();
        if ($schema->hasColumn($this->config->getDbTable(), 'response_headers_json')) {
            $row['response_headers_json'] = $responseHeadersJson !== false ? $responseHeadersJson : null;
        }

        return $row;
    }

    private function ensureTable(string $table): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable($table)) {
            $schema->create($table, function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->string('request_id', 64)->index();
                $t->timestamp('created_at')->useCurrent();
                $t->string('method', 16);
                $t->text('uri');
                $t->text('path');
                $t->text('query')->nullable();
                $t->longText('query_params_json')->nullable();
                $t->string('ip', 45)->nullable();
                $t->text('user_agent')->nullable();
                $t->string('content_type', 255)->nullable();
                $t->string('content_length', 64)->nullable();
                $t->longText('headers_json')->nullable();
                $t->longText('body')->nullable();
                $t->longText('decoded_body_json')->nullable();
                $t->longText('uploaded_files_json')->nullable();
                $t->longText('route_json')->nullable();
                $t->longText('response_headers_json')->nullable();
                $t->integer('status_code')->nullable();
                $t->integer('duration_ms')->nullable();
            });
            return;
        }

        // Add missing columns if table already exists
        if (!$schema->hasColumn($table, 'response_headers_json')) {
            $schema->table($table, function (Blueprint $t): void {
                $t->longText('response_headers_json')->nullable();
            });
        }
    }
}
