<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?CarbonImmutable $email_verified_at
 * @property string $password
 * @property ?string $remember_token
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property Collection<int, Feed> $feeds
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    const string COL_EMAIL_VERIFIED_AT = 'email_verified_at';
    const string COL_NAME = 'name';
    const string COL_PASSWORD = 'password';
    const string COL_REMEMBER_TOKEN = 'remember_token';
    const string REL_FEEDS = 'feeds';

    protected $casts = [
        // Without this, email_verified_at is a string rather than the date the @property annotation above promises.
        self::COL_EMAIL_VERIFIED_AT => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        self::COL_PASSWORD,
        self::COL_REMEMBER_TOKEN,
    ];

    /**
     * @return HasMany<Feed, self>
     */
    public function feeds(): HasMany
    {
        return $this->hasMany(Feed::class);
    }
}
