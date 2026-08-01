<?php

namespace Tests\Http\Controllers;

use App\Enums\AudioSourceType;
use App\Models\User;
use App\Platforms\Contracts\SourceMetadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class FetchSourceMetadataTest extends TestCase
{
    use FakesPlatform;

    #[Test]
    public function it_previews_a_source_before_anyone_subscribes_to_it()
    {
        // The point of this endpoint: show what a subscription would involve —
        // how many episodes, and by whom — while the form is still open.
        $this->fakePlatform(sourceMetadata: new SourceMetadata(
            name: 'Select Lectures',
            canonicalUrl: 'https://youtube.com/playlist?list=PLabc',
            authorName: 'Lecture Channel',
            type: AudioSourceType::Playlist,
            clipCount: 42,
        ));

        $response = $this->actingAs(User::factory()->create())
            ->postJson('api/fetch-source-metadata', ['url' => 'https://youtube.com/playlist?list=PLabc']);

        $response->assertOk()->assertJsonFragment([
            'metadata' => [
                'name' => 'Select Lectures',
                'canonicalUrl' => 'https://youtube.com/playlist?list=PLabc',
                'authorName' => 'Lecture Channel',
                'type' => ['name' => 'Playlist', 'value' => 'playlist'],
                'clipCount' => 42,
            ],
        ]);
    }

    #[Test]
    public function it_previews_a_channel_as_its_own_author()
    {
        $this->fakePlatform(sourceMetadata: new SourceMetadata(
            name: 'Lecture Channel',
            canonicalUrl: 'https://youtube.com/channel/UCabc',
            authorName: 'Lecture Channel',
            clipCount: 864,
        ));

        $this->actingAs(User::factory()->create())
            ->postJson('api/fetch-source-metadata', ['url' => 'https://youtube.com/channel/UCabc'])
            ->assertOk()
            ->assertJsonPath('metadata.type.value', 'channel')
            ->assertJsonPath('metadata.clipCount', 864);
    }
}
