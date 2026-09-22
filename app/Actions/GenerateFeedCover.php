<?php

namespace App\Actions;

use App\Covers\Contracts\CoverGenerator;
use App\Models\Feed;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Generates a feed's cover art from its name and stores it, replacing any
 * previous cover.
 *
 * Apple Podcasts and other directories treat a show without cover art as
 * incomplete, and podcast apps show a gray square. Users aren't asked for a
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
        $temporaryCoverPath = null;
        $coverHandle = null;
        $newCoverPath = null;
        $previousCoverPath = $feed->cover_path;
        $saved = false;

        try {
            // The feed id selects the background, so a renamed feed keeps it.
            $temporaryCoverPath = $this->generator->generate($feed->name, $feed->id);

            $coverHandle = fopen($temporaryCoverPath, 'r');

            if (! $coverHandle) {
                throw new \RuntimeException("Couldn't open {$temporaryCoverPath} as a resource");
            }

            $newCoverPath = $this->buildCoverPath($feed->name);

            if (! $this->storage->put($newCoverPath, $coverHandle)) {
                throw new \RuntimeException("Couldn't store a cover from {$temporaryCoverPath}");
            }

            $feed->cover_path = $newCoverPath;
            $feed->save();
            $saved = true;

            // The old cover is deleted after the save. If the save fails,
            // cover_path still contains the old file's path, so the file has to exist.
            if ($previousCoverPath !== null) {
                $this->storage->delete($previousCoverPath);
            }
        } catch (\Throwable $e) {
            // The save didn't complete, so no feed references the new cover.
            if ($newCoverPath !== null && ! $saved) {
                $this->storage->delete($newCoverPath);
                $feed->cover_path = $previousCoverPath;
            }

            // A feed works without artwork, so a failure here must not stop
            // the feed being created.
            $this->logger->warning(
                "Couldn't generate a cover for feed {$feed->id}: {$e->getMessage()}"
            );
        } finally {
            if (is_resource($coverHandle)) {
                fclose($coverHandle);
            }

            if ($temporaryCoverPath !== null && file_exists($temporaryCoverPath)) {
                unlink($temporaryCoverPath);
            }
        }
    }

    private function buildCoverPath(string $name): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? 'feed' : Str::limit($base, 100, '');

        do {
            $token = Str::lower(Str::random(6));
            $path = "covers/{$base}-{$token}.jpg";
        } while (Feed::query()->where('cover_path', $path)->exists());

        return $path;
    }
}
