@use(App\Http\Routes\Web)

@php
    /** @var \App\Models\Feed $feed */
@endphp

<h1>{{$feed->name}}</h1>
@foreach($feed->audioClips as $clip)
    <p>{{$clip->title}}</p>
@endforeach

<form action="{{Web::confirmAddClipToFeed($feed)}}" method="get">
    @csrf
    URL:<input name="url"/>
    <button type="submit">Add to Feed</button>
</form>
