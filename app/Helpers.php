<?php

namespace App;

abstract class Helpers
{
    public static function removeWwwFromHost(string $host): string {
        return str_starts_with($host, 'www.')
            ? substr($host, strlen('www.'))
            : $host;
    }

    public static function unquote(string $str): string {
        $str = (str_starts_with($str, "'") || str_starts_with($str, '"')) ? substr($str, 1) : $str;
        $str = (str_ends_with($str, "'") || str_ends_with($str, '"')) ? substr($str, 0, -1) : $str;

        return $str;
    }
}
