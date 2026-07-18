<?php

namespace Tests\Policies;

use App\Models\Feed;
use App\Models\User;
use App\Policies\FeedPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedPolicyTest extends TestCase
{
    #[Test]
    public function it_allows_the_owner_and_denies_everyone_else()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $owner->id]);

        $policy = new FeedPolicy;

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue($policy->{$ability}($owner, $feed), "owner should be allowed to $ability");
            $this->assertFalse($policy->{$ability}($stranger, $feed), "stranger should be denied $ability");
        }
    }
}
