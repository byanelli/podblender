@use(App\Http\Routes\Web)
@php
    /** @var \App\Models\User $user */
@endphp


@foreach($user->feeds as $feed)
    <p><a href="{{Web::showFeed($feed)}}">{{$feed->name}}</a></p>
@endforeach
<div>!!!!leijrgilejgr
</div>

<a href="{{Web::logout()}}">Logout</a>
