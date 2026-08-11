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

        // Inserting without supplying an id works and hands out ascending keys.
        $this->assertGreaterThan($first->id, $second->id);

        // A real AUTOINCREMENT key never reuses an id, even after the highest row is deleted. A plain single-column
        // integer primary key on SQLite is only a rowid alias, which would hand the freed id straight back — the
        // accident the migration exists to turn into a proper auto-increment key on every driver.
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
            'title' => $title,
        ]);

        // The column was string(255); FindOrCreateAudioClip writes titles up to 497 characters. A 400-character title
        // must survive the round trip intact rather than being cut to 255.
        $this->assertEquals($title, $clip->fresh()->title);
    }
}
