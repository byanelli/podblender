<?php

namespace App\Enums;

/**
 * What kind of thing an AudioSource is on its platform.
 *
 * The distinction that matters is authorship: a channel's name is also the name
 * of whoever publishes it, so it can be credited as the author of a feed. A
 * playlist's name is a collection ("Select Lectures"), which would read as
 * nonsense in a podcast app's author field, so a playlist is credited to the
 * channel that owns it instead.
 */
enum AudioSourceType: string
{
    case Channel = 'channel';
    case Playlist = 'playlist';
}
