<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class Home
{
    public function __invoke(Request $request): View {
        return view('home', ['user' => User::auth()->load(User::REL_FEEDS)]);
    }
}
