<?php

namespace Tests\Concerns;

use App\Concerns\FixesUrls;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FixesUrlsTest extends TestCase
{
    /**
     * An object exposing the trait's protected methods so they can be exercised directly.
     */
    private function fixer(): object
    {
        return new class
        {
            use FixesUrls {
                ensureSchemeIsHttps as public;
                removeWwwFromHost as public;
                removeUtmCodesFromUrl as public;
                fixUrlSchemeAndHost as public;
                fixUrl as public;
            }
        };
    }

    #[Test]
    public function it_forces_the_scheme_to_https()
    {
        $this->assertEquals('https://example.com/x', $this->fixer()->ensureSchemeIsHttps('http://example.com/x'));
        $this->assertEquals('https://example.com/x', $this->fixer()->ensureSchemeIsHttps('https://example.com/x'));
    }

    #[Test]
    public function it_removes_a_www_host_prefix()
    {
        $this->assertEquals('https://example.com/x', $this->fixer()->removeWwwFromHost('https://www.example.com/x'));
    }

    #[Test]
    public function it_leaves_a_host_without_a_www_prefix_alone()
    {
        $this->assertEquals('https://example.com/x', $this->fixer()->removeWwwFromHost('https://example.com/x'));
    }

    #[Test]
    public function it_tolerates_a_url_with_no_host()
    {
        // A URL like a mailto: has a null host. The Phase 3 guard means str_starts_with is never handed a null, so the
        // URL passes through unchanged rather than throwing a TypeError.
        $this->assertEquals('mailto:foo@example.com', $this->fixer()->removeWwwFromHost('mailto:foo@example.com'));
    }

    #[Test]
    public function it_leaves_a_url_without_a_query_alone_when_removing_utm_codes()
    {
        $this->assertEquals('https://example.com/x', $this->fixer()->removeUtmCodesFromUrl('https://example.com/x'));
    }

    #[Test]
    public function it_removes_only_the_utm_query_parameters()
    {
        $this->assertEquals(
            'https://example.com/x?a=1&b=2',
            $this->fixer()->removeUtmCodesFromUrl('https://example.com/x?a=1&utm_source=z&b=2')
        );
    }

    #[Test]
    public function it_drops_the_trailing_question_mark_when_every_parameter_was_a_utm_code()
    {
        $this->assertEquals(
            'https://example.com/x',
            $this->fixer()->removeUtmCodesFromUrl('https://example.com/x?utm_source=z&utm_medium=e')
        );
    }

    #[Test]
    public function it_fixes_the_scheme_and_host_together()
    {
        $this->assertEquals('https://example.com/x', $this->fixer()->fixUrlSchemeAndHost('http://www.example.com/x'));
    }

    #[Test]
    public function it_fixes_the_scheme_host_and_utm_codes_together()
    {
        $this->assertEquals(
            'https://example.com/x?keep=1',
            $this->fixer()->fixUrl('http://www.example.com/x?utm_source=z&keep=1')
        );
    }
}
