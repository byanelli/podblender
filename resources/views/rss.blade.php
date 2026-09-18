@php
    /** @var \App\Models\Feed $feed */
@endphp

@php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; @endphp
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
        {{-- Show artwork, when the feed has any. Left out entirely otherwise, for the reason given on the episode
             artwork below: a tag pointing at nothing is worse than no tag. Both elements carry the same picture —
             itunes:image is what the podcast apps read, and <image> is what plain RSS readers read. --}}
        @if($feed->cover_url)
            <itunes:image href="{{$feed->cover_url}}"/>
            <image>
                <url>{{$feed->cover_url}}</url>
                <title>{{$feed->name}}</title>
                <link>{{route('rss', [$feed])}}</link>
            </image>
        @endif
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
                {{-- Episode artwork, when the clip has any. A missing href is worse than a missing tag: some clients
                     show a broken image where the channel's own picture would otherwise stand in. --}}
                @if($clip->thumbnail_url)
                    <itunes:image href="{{$clip->thumbnail_url}}"/>
                @endif
                <guid isPermaLink="false">{{$clip->guid}}</guid>
            </item>
        @endforeach
    </channel>
</rss>
