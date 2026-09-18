<?php

namespace App\Actions;

use App\Covers\Contracts\CoverGenerator;
use App\Models\Feed;
use App\Support\FeedCoverStoragePath;
use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;

/**
 * Draws a feed's show artwork from its name and stores it, replacing whatever
 * the feed had before.
 *
 * Every podcast needs cover art: Apple Podcasts and the other directories treat
 * a show without it as incomplete, and a listener's app shows a grey square. No
 * one is asked to supply a picture when they make a feed, so the app draws one.
 *
 * This is the single place that decides a feed should get a drawn cover. When
 * feeds can carry an uploaded picture of their own, the check for "this feed
 * has its own artwork, leave it alone" belongs at the top of __invoke().
 */
readonly class GenerateFeedCover
{
    public function __construct(
        private CoverGenerator $generator,
        private Filesystem $storage,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(Feed $feed): void
    {
        $temporaryPath = null;
        $handle = null;

        try {
            // The feed id, not its name: a feed that gets renamed keeps the
            // background its owner already knows it by.
            $temporaryPath = $this->generator->generate($feed->name, $feed->id);

            $handle = fopen($temporaryPath, 'r');

            if (! $handle) {
                throw new \RuntimeException("Couldn't open {$temporaryPath} as a resource");
            }

            $path = FeedCoverStoragePath::for($feed->name);

            if (! $this->storage->put($path, $handle)) {
                throw new \RuntimeException("Couldn't store a cover from {$temporaryPath}");
            }

            $previousPath = $feed->cover_path;

            $feed->cover_path = $path;
            $feed->save();

            // Only once the feed points at the new file: if saving fails, the
            // feed still has a cover a listener can fetch.
            if ($previousPath !== null) {
                $this->storage->delete($previousPath);
            }
        } catch (\Throwable $e) {
            // Artwork is a nicety. A feed without it still collects clips and
            // still plays, so nothing here is allowed to stop a feed being
            // made — say what went wrong and leave cover_path as it was.
            $this->logger->warning(
                "Couldn't generate a cover for feed {$feed->id}: {$e->getMessage()}"
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if ($temporaryPath !== null && file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
