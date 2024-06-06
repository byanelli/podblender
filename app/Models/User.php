<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
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
    const string COL_CREATED_AT = 'created_at';
    const string COL_EMAIL = 'email';
    const string COL_EMAIL_VERIFIED_AT = 'email_verified_at';
    const string COL_ID = 'id';
    const string COL_NAME = 'name';
    const string COL_PASSWORD = 'password';
    const string COL_REMEMBER_TOKEN = 'remember_token';
    const string COL_UPDATED_AT = 'updated_at';

    // Relations
    const string REL_FEEDS = 'feeds';

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

    public function feeds(): HasMany {
        return $this->hasMany(Feed::class);
    }
}
