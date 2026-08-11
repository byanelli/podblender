<?php

namespace App\Console\Commands;

use App\Models\AudioClip;
use App\Support\AudioClipStoragePath;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;

class BackfillAudioClipStoragePaths extends Command
{
    protected $signature = 'clips:slug-storage-paths';

    protected $description = 'Rename stored audio files to slug-based paths and update the matching clips';

    public function handle(Filesystem $storage): int
    {
        $renamed = 0;
        $recordOnly = 0;

        AudioClip::query()
            ->orderBy('id')
            ->each(function (AudioClip $clip) use ($storage, &$renamed, &$recordOnly) {
                $newPath = AudioClipStoragePath::for($clip->audioSource->name, $clip->title);

                if ($newPath === $clip->storage_path) {
                    return;
                }

                if ($storage->exists($clip->storage_path)) {
                    // A file exists, so physically move it to the new path. A local move is a rename, not a copy, so
                    // this stays cheap even for the large files these clips tend to be.
                    $storage->move($clip->storage_path, $newPath);
                    $renamed++;
                } else {
                    // No file on disk yet (a clip still processing or failed before download). Nothing to move; the
                    // download job stores to the clip's storage_path at runtime, so updating the record is enough.
                    $recordOnly++;
                }

                $clip->forceFill(['storage_path' => $newPath])->save();
            });

        $this->info("Renamed $renamed file(s) and updated $recordOnly record(s) without a file on disk.");

        return self::SUCCESS;
    }
}
