@php
    /** @var \App\Models\Feed $feed */
@endphp

@php echo '<?xml version="1.0" encoding="UTF-8"?>\n'; @endphp
<rss version="2.0"
     xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
    <channel>
        <title>{{$feed->name}}</title>
        <link>{{route('rss', [$feed])}}</link>
        <description>{{$feed->description}}</description>
        <itunes:owner>
            <itunes:email>{{$feed->user->email}}</itunes:email>
        </itunes:owner>
        {{-- Who publishes the podcast: for a subscription that's the channel, not the podblender user who set it up. --}}
        <itunes:author>{{$feed->author_name}}</itunes:author>
        <itunes:image href="https://placehold.co/400"/> {{--todo: specify image url?--}}
        <language>en-us</language>
        @foreach($feed->audioClipsFinishedProcessing as $clip)
            <item>
                <title>{{$clip->title}}</title>
                <link>{{$clip->platform_url}}</link>
                <description>{{$clip->description}}</description>
                {{-- Per episode, the channel that uploaded it: a playlist can collect several channels' videos. --}}
                <itunes:author>{{$clip->audioSource->name}}</itunes:author>
                @if($clip->pivot->published_at)
                    <pubDate>{{$clip->pivot->published_at->format(\DateTimeInterface::RSS)}}</pubDate>
                @endif
                <enclosure url="{{$clip->audio_url}}"
                           type="audio/mpeg" length="{{$clip->size}}"/>
                <itunes:duration>{{$clip->formatted_time}}</itunes:duration>
                <guid isPermaLink="false">{{$clip->guid}}</guid>
            </item>
        @endforeach
    </channel>
</rss>
