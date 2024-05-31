<?php

namespace App;

abstract class Helpers
{
    public static function removeWwwFromHost(string $host): string {
        return str_starts_with($host, 'www.')
            ? substr($host, strlen('www.'))
            : $host;
    }
}
