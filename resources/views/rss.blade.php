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
        {{-- The source's publisher for a subscription, the feed's user for a custom feed. --}}
        <itunes:author>{{$feed->author_name}}</itunes:author>
        {{-- Omitted without a cover, as with episode artwork below. Podcast apps read itunes:image, and plain RSS
             readers read <image>. --}}
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
                {{-- The uploader. In a playlist it can differ from the feed's author. --}}
                <itunes:author>{{$clip->audioSource->name}}</itunes:author>
                @if($clip->pivot->published_at)
                    <pubDate>{{$clip->pivot->published_at->format(\DateTimeInterface::RSS)}}</pubDate>
                @endif
                <enclosure url="{{$clip->audio_url}}"
                           type="audio/mpeg" length="{{$clip->size}}"/>
                <itunes:duration>{{$clip->formatted_time}}</itunes:duration>
                {{-- Omitted without artwork. Given an empty href, some clients show a broken image instead of the
                     channel's artwork. --}}
                @if($clip->thumbnail_url)
                    <itunes:image href="{{$clip->thumbnail_url}}"/>
                @endif
                <guid isPermaLink="false">{{$clip->guid}}</guid>
            </item>
        @endforeach
    </channel>
</rss>
