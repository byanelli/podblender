<?php

namespace Tests\Http\Controllers;

use App\Enums\ClipProcessingState;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RetryClipTest extends TestCase
{
    private function clip(ClipProcessingState $state): AudioClip
    {
        return AudioClip::factory()->create([
            'audio_source_id'  => AudioSource::factory()->create()->id,
            'processing_state' => $state,
        ]);
    }

    /**
     * The job's clip property is private, so it is read by reflection.
     */
    private function clipOf(DownloadAndStoreAudioClip $job): AudioClip
    {
        return (new ReflectionProperty($job, 'clip'))->getValue($job);
    }

    #[Test]
    public function it_queues_another_download_for_a_failed_clip()
    {
        Bus::fake();

        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id]);
        $clip = $this->clip(ClipProcessingState::Failed);
        $feed->audioClips()->attach($clip);

        $this->actingAs($user)
            ->post("/feeds/{$feed->id}/clips/{$clip->id}/retry")
            ->assertSuccessful();

        // While Processing, the feed page shows the clip as processing and the RSS omits it.
        $this->assertEquals(
            ClipProcessingState::Processing,
            $clip->fresh()->processing_state
        );

        Bus::assertDispatchedTimes(DownloadAndStoreAudioClip::class, 1);
        Bus::assertDispatched(
            DownloadAndStoreAudioClip::class,
            fn (DownloadAndStoreAudioClip $job) => $this->clipOf($job)->is($clip),
        );
    }

    #[Test]
    #[TestWith([ClipProcessingState::Unavailable])]
    #[TestWith([ClipProcessingState::Processed])]
    #[TestWith([ClipProcessingState::Processing])]
    public function it_refuses_to_retry_a_clip_that_did_not_fail(ClipProcessingState $state)
    {
        Bus::fake();
        $this->withExceptionHandling();

        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id]);
        $clip = $this->clip($state);
        $feed->audioClips()->attach($clip);

        // Unavailable means the platform reported the content as permanently gone, so a retry would fail again.
        $this->actingAs($user)
            ->post("/feeds/{$feed->id}/clips/{$clip->id}/retry")
            ->assertStatus(Response::HTTP_CONFLICT);

        $this->assertEquals($state, $clip->fresh()->processing_state);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_rejects_retrying_a_clip_that_is_not_on_the_feed()
    {
        Bus::fake();
        $this->withExceptionHandling();

        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id]);
        $clip = $this->clip(ClipProcessingState::Failed);

        $this->actingAs($user)
            ->post("/feeds/{$feed->id}/clips/{$clip->id}/retry")
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertEquals(
            ClipProcessingState::Failed,
            $clip->fresh()->processing_state
        );

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_does_not_let_another_user_retry_a_clip()
    {
        Bus::fake();
        $this->withExceptionHandling();

        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id + 1]);
        $clip = $this->clip(ClipProcessingState::Failed);
        $feed->audioClips()->attach($clip);

        $this->actingAs($user)
            ->post("/feeds/{$feed->id}/clips/{$clip->id}/retry")
            ->assertForbidden();

        $this->assertEquals(
            ClipProcessingState::Failed,
            $clip->fresh()->processing_state
        );

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_sends_a_guest_to_the_login_page()
    {
        Bus::fake();
        $this->withExceptionHandling();

        $feed = Feed::factory()->create();
        $clip = $this->clip(ClipProcessingState::Failed);
        $feed->audioClips()->attach($clip);

        $this->post("/feeds/{$feed->id}/clips/{$clip->id}/retry")
            ->assertRedirect(route('login'));

        $this->assertEquals(
            ClipProcessingState::Failed,
            $clip->fresh()->processing_state
        );

        Bus::assertNothingDispatched();
    }
}
