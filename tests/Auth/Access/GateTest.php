<?php

namespace Tests\Auth\Access;

use App\Auth\Access\Gate;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GateTest extends TestCase
{
    private function gate(): Gate
    {
        return $this->app->make(Gate::class);
    }

    /**
     * The abilities FeedPolicy actually defines, so the owner is allowed and a stranger is denied.
     *
     * @return list<array{string, string}>
     */
    public static function definedAbilities(): array
    {
        return [
            ['checkView', 'authorizeView'],
            ['checkUpdate', 'authorizeUpdate'],
            ['checkDelete', 'authorizeDelete'],
        ];
    }

    /**
     * The remaining model abilities the gate exposes but FeedPolicy does not implement, so everyone is denied.
     *
     * @return list<array{string, string}>
     */
    public static function undefinedAbilities(): array
    {
        return [
            ['checkViewAny', 'authorizeViewAny'],
            ['checkCreate', 'authorizeCreate'],
            ['checkRestore', 'authorizeRestore'],
            ['checkForceDelete', 'authorizeForceDelete'],
        ];
    }

    #[Test]
    public function it_lets_the_owner_check_and_authorize_a_defined_ability()
    {
        $owner = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $owner->id]);

        $this->actingAs($owner);
        $gate = $this->gate();

        foreach (self::definedAbilities() as [$check, $authorize]) {
            $this->assertTrue($gate->{$check}($feed), "owner should pass $check");
            $this->assertTrue($gate->{$authorize}($feed)->allowed(), "owner should pass $authorize");
        }
    }

    #[Test]
    public function it_denies_a_non_owner_a_defined_ability()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $owner->id]);

        $this->actingAs($stranger);
        $gate = $this->gate();

        foreach (self::definedAbilities() as [$check, $authorize]) {
            $this->assertFalse($gate->{$check}($feed), "stranger should fail $check");
            $this->assertThrowsAuthorization(fn () => $gate->{$authorize}($feed));
        }
    }

    #[Test]
    public function it_denies_everyone_an_ability_the_policy_does_not_define()
    {
        $owner = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $owner->id]);

        $this->actingAs($owner);
        $gate = $this->gate();

        foreach (self::undefinedAbilities() as [$check, $authorize]) {
            $this->assertFalse($gate->{$check}($feed), "$check should be denied");
            $this->assertThrowsAuthorization(fn () => $gate->{$authorize}($feed));
        }
    }

    private function assertThrowsAuthorization(callable $authorize): void
    {
        try {
            $authorize();
            $this->fail('Expected an AuthorizationException to be thrown.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }
}
