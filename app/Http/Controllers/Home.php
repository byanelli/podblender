<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class Home
{
    public function __invoke(Request $request): View {
        /* @see resources/views/home.blade.php */
        return view('home', [
            'user' => User::auth()->load(User::REL_FEEDS.'.'.Feed::REL_AUDIO_CLIPS_FINISHED_PROCESSING)
        ]);
    }
}
