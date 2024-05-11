@use(App\Http\Routes\Web)

@php
    /** @var \App\Models\Feed $feed */
    /** @var \App\Models\AudioClip $clip */
@endphp

<p>{{$clip->title}} is being added to {{$feed->name}}. Please wait a few minutes for the audio to download.</p>
<p><a href="{{Web::showFeed($feed)}}">Back to {{$feed->name}}</a></p>
