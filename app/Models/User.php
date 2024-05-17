<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

/**
 * Columns:
 * @property \DateTimeInterface $created_at
 * @property string $email
 * @property \DateTimeInterface $email_verified_at
 * @property int $id
 * @property string $name
 * @property string $password
 * @property string $remember_token
 * @property \DateTimeInterface $updated_at
 *
 * Relations:
 * @property Collection<int, Feed> $feeds
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Columns
    const COL_CREATED_AT = 'created_at';
    const COL_EMAIL = 'email';
    const COL_EMAIL_VERIFIED_AT = 'email_verified_at';
    const COL_ID = 'id';
    const COL_NAME = 'name';
    const COL_PASSWORD = 'password';
    const COL_REMEMBER_TOKEN = 'remember_token';
    const COL_UPDATED_AT = 'updated_at';

    // Relations
    const REL_FEEDS = 'feeds';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public static function auth(): User {
        return Auth::user();
    }

    public function feeds(): HasMany {
        return $this->hasMany(Feed::class);
    }
}
