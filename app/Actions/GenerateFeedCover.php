<?php

namespace App\Actions;

use App\Covers\Contracts\CoverGenerator;
use App\Models\Feed;
use App\Support\FeedCoverStoragePath;
use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;

/**
 * Generates a feed's cover art from its name and stores it, replacing any
 * previous cover.
 *
 * Apple Podcasts and other directories treat a show without cover art as
 * incomplete, and podcast apps show a grey square. Users aren't asked for a
 * picture when they create a feed.
 *
 * If feeds later support uploaded artwork, the check for it belongs at the top
 * of __invoke().
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
            // The feed id selects the background, so a renamed feed keeps it.
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

            // Delete only after the save, so a failed save leaves the feed
            // with a cover that still exists.
            if ($previousPath !== null) {
                $this->storage->delete($previousPath);
            }
        } catch (\Throwable $e) {
            // A feed works without artwork, so a failure here must not stop
            // the feed being created. cover_path is left unchanged.
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
