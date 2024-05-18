<?php

namespace Tests\Actions;

use App\Actions\SaveYoutubeVideo;
use App\Models\AudioClip;
use App\Apis\YoutubeDownloader\Client;
use App\Apis\YoutubeDownloader\Metadata;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaveYoutubeVideoTest extends TestCase
{
    #[Test]
    public function it_saves_a_youtube_video() {
        $this->app->bind(Client::class, function () {
            return new class (
                $this->app,
                $this->app->make(Repository::class),
                Process::fake()
            ) extends Client {
                public function getMetadata(string $url): Metadata {
                    return new Metadata(
                        id: 'lijwliejfwlef',
                        title: 'foo',
                        description: 'zzz',
                        channel_id: '9340e9tjh490e5',
                        channel: 'bar',
                        duration: 123,
                    );
                }
            };
        });

        /** @var SaveYoutubeVideo $saveYoutubeVideo */
        $saveYoutubeVideo = $this->app->make(SaveYoutubeVideo::class);

        $clip = $saveYoutubeVideo(1);

        $this->assertEquals('lijwliejfwlef', $clip->platform_id);
        $this->assertEquals('foo', $clip->title);
        $this->assertEquals('zzz', $clip->description);
        $this->assertEquals(123, $clip->duration);
        $this->assertEquals('9340e9tjh490e5', $clip->audioSource->platform_id);
        $this->assertEquals('bar', $clip->audioSource->name);
        $this->assertTrue($clip->processing);
    }
}
