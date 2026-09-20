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
                    // On a local disk a move is a rename, so it is cheap even for large files.
                    $storage->move($clip->storage_path, $newPath);
                    $renamed++;
                } else {
                    // No file yet: the clip is still processing or its download failed. The download job reads
                    // storage_path when it runs, so updating the record is enough.
                    $recordOnly++;
                }

                $clip->forceFill(['storage_path' => $newPath])->save();
            });

        $this->info("Renamed $renamed file(s) and updated $recordOnly record(s) without a file on disk.");

        return self::SUCCESS;
    }
}
