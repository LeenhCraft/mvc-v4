<?php

declare(strict_types=1);

namespace App\Models;

/**
 * User Model
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends BaseModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Find user by email
     *
     * @param string $email
     * @return static|null
     */
    public static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }

    /**
     * Check if email is already taken
     *
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = static::where('email', $email);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get users with verified emails
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getVerified()
    {
        return static::whereNotNull('email_verified_at')->get();
    }

    /**
     * Check if user's email is verified
     *
     * @return bool
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Mark email as verified
     *
     * @return bool
     */
    public function markEmailAsVerified(): bool
    {
        $this->email_verified_at = now();
        return $this->save();
    }
}
