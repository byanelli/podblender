import { route } from "../../vendor/tightenco/ziggy/src/js";

export default {
    feed: (feedId: number) => route('showFeed', {feed: feedId}),
    showMetadata: (feedId: number) => route('showMetadata', {feed: feedId}),
    rss: (feedUuid: string) => route('rss', {feed: feedUuid}),

    api: {
        fetchMetadata: route('fetchMetadata'),
        addClipToFeed: (feedId: number) => route('addClipToFeed', {feed: feedId}),
    }
}
