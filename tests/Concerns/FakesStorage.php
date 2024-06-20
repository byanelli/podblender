<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

trait FakesStorage
{
    protected function fakeStorageThatThrowsExceptionOnPut(): void
    {
        $fsAdapter = new LocalFilesystemAdapter(sys_get_temp_dir());

        $this->app->bind(Filesystem::class, function () use ($fsAdapter) {
            return new class(new \League\Flysystem\Filesystem($fsAdapter), $fsAdapter, config('filesystems.disks.local')) extends FilesystemAdapter
            {
                public function put($path, $contents, $options = [])
                {
                    throw new \Exception;
                }
            };
        });
    }
}
