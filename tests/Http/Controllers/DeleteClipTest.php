<?php

namespace Tests\Http\Controllers;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeleteClipTest extends TestCase
{
    private function clip(): AudioClip
    {
        return AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
        ]);
    }

    #[Test]
    public function it_detaches_a_clip_from_the_owners_feed()
    {
        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id]);
        $clip = $this->clip();
        $feed->audioClips()->attach($clip);

        $this->assertEquals(1, $feed->audioClips()->count());

        $this->actingAs($user)->delete("/feeds/{$feed->id}/clips/{$clip->id}");

        $this->assertEquals(0, $feed->audioClips()->count());
        // The clip itself is only detached, never deleted.
        $this->assertModelExists($clip);
    }

    #[Test]
    public function it_rejects_deleting_a_clip_that_is_not_on_the_feed()
    {
        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id]);
        $clip = $this->clip();

        $this->expectException(HttpException::class);

        $this->actingAs($user)->delete("/feeds/{$feed->id}/clips/{$clip->id}");
    }

    #[Test]
    public function it_does_not_let_another_user_delete_a_clip()
    {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id + 1]);
        $clip = $this->clip();
        $feed->audioClips()->attach($clip);

        $this->actingAs($user)->delete("/feeds/{$feed->id}/clips/{$clip->id}");
    }

    #[Test]
    public function it_does_not_let_a_guest_delete_a_clip()
    {
        $this->expectException(AuthenticationException::class);

        $feed = Feed::factory()->create();
        $clip = $this->clip();
        $feed->audioClips()->attach($clip);

        $this->delete("/feeds/{$feed->id}/clips/{$clip->id}");
    }
}
