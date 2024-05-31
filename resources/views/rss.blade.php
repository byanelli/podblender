@use(App\Http\Routes\Web)

@php
    /** @var \App\Models\Feed $feed */
    /** @var \App\Platforms\Contracts\PlatformFactory $platformFactory */
@endphp

@php echo '<?xml version="1.0" encoding="UTF-8"?>\n'; @endphp
<rss version="2.0"
     xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
    <channel>
        <title>{{$feed->name}}</title>
        <link>{{Web::rss($feed)}}</link>
        <description>{{$feed->description}}</description>
        <itunes:owner>
            <itunes:email>{{$feed->user->email}}</itunes:email>
        </itunes:owner>
        <itunes:author>{{$feed->user->name}}</itunes:author>
        <itunes:image href="{{$feed->imageUrl}}"/>
        <language>en-us</language>
        @foreach($feed->audioClipsFinishedProcessing as $clip)
            @php($platform = $platformFactory->make($clip->platformType))
            <item>
                <title>{{$clip->title}}</title>
                <link>{{$platform->getUrlFromId($clip->platform_id)}}</link>
                <description>{{$clip->description}}</description>
                {{--todo: this should come from the feed/clip pivot--}}
                <pubDate>{{$clip->created_at->format(\DateTimeInterface::RSS)}}</pubDate>
                <enclosure url="{{$clip->audio_url}}"
                           type="audio/mpeg" length="{{$clip->size}}"/>
                <itunes:duration>{{$clip->formatted_time}}</itunes:duration>
                <guid isPermaLink="false">{{$clip->guid}}</guid>
            </item>
        @endforeach
    </channel>
</rss>
