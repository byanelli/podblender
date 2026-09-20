<?php

namespace App\Enums;

/**
 * What kind of thing an AudioSource is on its platform.
 *
 * The type determines a feed's author. A channel's name is its publisher's
 * name, so the channel is the author. A playlist's name describes a collection
 * ("Select Lectures"), so the author is the channel the playlist belongs to.
 */
enum AudioSourceType: string
{
    case Channel = 'channel';
    case Playlist = 'playlist';
}
