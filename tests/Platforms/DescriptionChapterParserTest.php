<?php

namespace Tests\Platforms;

use App\Platforms\Contracts\Chapter;
use App\Platforms\DescriptionChapterParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DescriptionChapterParserTest extends TestCase
{
    /**
     * @return list<array{int, string}>
     */
    private function parse(string $description): array
    {
        return array_map(
            fn (Chapter $c) => [$c->startSeconds, $c->title],
            DescriptionChapterParser::parse($description),
        );
    }

    #[Test]
    public function it_reads_chapters_from_a_description()
    {
        // From https://www.youtube.com/watch?v=IEOqgPfGLQ8, shortened.
        $description = <<<'TXT'
            What does it mean to be a Rubyist when agents write the code? Recorded at 12:30 on day two.

            CHAPTERS
            00:00 Welcome to "AI World"
            02:55 Speedrunning the stages of grief
            04:24 Who still feels like a Rubyist?
            13:13 Spinel: compiling Ruby to native binaries

            Subscribe for more.
            TXT;

        $this->assertEquals([
            [0, 'Welcome to "AI World"'],
            [175, 'Speedrunning the stages of grief'],
            [264, 'Who still feels like a Rubyist?'],
            [793, 'Spinel: compiling Ruby to native binaries'],
        ], $this->parse($description));
    }

    #[Test]
    public function it_reads_hours_brackets_bullets_and_separators()
    {
        $description = "(0:00) Intro\n• 5:07 - Part one\n[59:59] – Part two\n1:02:03 | Part three\r\n10:00:00: Late";

        $this->assertEquals([
            [0, 'Intro'],
            [307, 'Part one'],
            [3599, 'Part two'],
            [3723, 'Part three'],
            [36000, 'Late'],
        ], $this->parse($description));
    }

    #[Test]
    public function it_finds_no_chapters_when_the_first_is_not_at_zero()
    {
        $this->assertEquals([], $this->parse("0:10 One\n1:00 Two\n2:00 Three"));
    }

    #[Test]
    public function it_finds_no_chapters_when_there_are_fewer_than_three()
    {
        $this->assertEquals([], $this->parse("0:00 One\n1:00 Two"));
    }

    #[Test]
    public function it_finds_no_chapters_when_the_timestamps_do_not_increase()
    {
        $this->assertEquals([], $this->parse("0:00 One\n2:00 Two\n1:00 Three"));
    }

    #[Test]
    public function it_ignores_invalid_timestamps_and_lines_without_a_title()
    {
        $this->assertEquals([
            [0, 'One'],
            [60, 'Two'],
            [120, 'Three'],
        ], $this->parse("0:00 One\n0:75 Bad seconds\n1:00 Two\n1:60:00 Bad minutes\n1:30\n2:00 Three"));
    }

    #[Test]
    public function it_finds_no_chapters_in_a_description_without_timestamps()
    {
        $this->assertEquals([], $this->parse('Just a description.'));
    }
}
