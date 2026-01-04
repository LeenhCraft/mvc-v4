<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base Model for all application models
 *
 * Provides common functionality and enforces consistency
 */
abstract class BaseModel extends Model
{
    /**
     * Indicates if the model should be timestamped.
     * Override in child models if needed
     */
    public $timestamps = true;

    /**
     * The storage format of the model's date columns.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * The attributes that should be cast to native types.
     * @var array<string, string>
     */
    protected $casts = [];

    /**
     * Get all models with optional pagination
     *
     * @param int|null $perPage Number of items per page (null for all)
     * @return mixed
     */
    public static function getAll(?int $perPage = null)
    {
        $query = static::query();
        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Find by ID or fail with 404
     *
     * @param int|string $id
     * @return static
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findOrFail404($id): self
    {
        return static::findOrFail($id);
    }

    /**
     * Check if model exists by ID
     *
     * @param int|string $id
     * @return bool
     */
    public static function exists($id): bool
    {
        return static::where(static::make()->getKeyName(), $id)->exists();
    }
}
