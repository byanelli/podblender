<?php

namespace Tests\Database;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    #[Test]
    public function feeds_have_an_auto_incrementing_primary_key()
    {
        $first = Feed::factory()->create();
        $second = Feed::factory()->create();

        $this->assertGreaterThan($first->id, $second->id);

        // An AUTOINCREMENT key never reuses an id, even after the highest row is deleted. On SQLite, a plain integer
        // primary key is a rowid alias, which would reuse the freed id.
        $highest = $second->id;
        $second->delete();

        $this->assertGreaterThan($highest, Feed::factory()->create()->id);
    }

    #[Test]
    public function an_audio_clip_with_a_long_title_persists()
    {
        $title = Str::repeat('a', 400);

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
            'title'           => $title,
        ]);

        // FindOrCreateAudioClip writes titles of up to 500 characters, so the column must store more than 255.
        $this->assertEquals($title, $clip->fresh()->title);
    }
}
