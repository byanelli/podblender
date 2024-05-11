@use(App\Http\Routes\Web)

@php
    /** @var \App\Models\Feed $feed */
    /** @var \App\YoutubeDownloader\Metadata $metadata */
@endphp

<p>{{$metadata->title}}</p>
<p>by {{$metadata->channel}}</p>
<p>{{$metadata->description}}</p>

<form action="{{Web::addClipToFeed($feed)}}" method="post">
    @csrf
    <input type="hidden" name="id" value="{{$metadata->id}}"/>
    <button type="submit">Confirm Add to Feed</button>
</form>
