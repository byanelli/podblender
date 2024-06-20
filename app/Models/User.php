<?php

namespace App\Models;

use Based\Fluent\Fluent;
use Based\Fluent\Relations\Relation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, Fluent;

    public CarbonImmutable $created_at;
    const string COL_CREATED_AT = 'created_at';

    public string $email;
    const string COL_EMAIL = 'email';

    public ?CarbonImmutable $email_verified_at;
    const string COL_EMAIL_VERIFIED_AT = 'email_verified_at';

    public int $id;
    const string COL_ID = 'id';

    public string $name;
    const string COL_NAME = 'name';

    public string $password;
    const string COL_PASSWORD = 'password';

    public ?string $remember_token;
    const string COL_REMEMBER_TOKEN = 'remember_token';

    public string $updated_at;
    const string COL_UPDATED_AT = 'updated_at';

    /**
     * @var Collection<int, Feed>
     * @see self::feeds()
     */
    #[Relation]
    public Collection $feeds;
    const string REL_FEEDS = 'feeds';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        self::COL_PASSWORD,
        self::COL_REMEMBER_TOKEN,
    ];

    public function feeds(): HasMany {
        return $this->hasMany(Feed::class);
    }
}
