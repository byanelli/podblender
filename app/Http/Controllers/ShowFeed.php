<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShowFeed
{
    public function __invoke(Request $request, Feed $feed): View {
        return view('feed', ['feed' => $feed]);
    }
}
