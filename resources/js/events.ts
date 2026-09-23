export default {
    // Both events arrive on one private channel, so they share a single leave().
    feed: (feedId: number) => ({
        onFinishedProcessingClip: (callback: (payload: unknown) => void) => {
            window.Echo.private(`feeds.${feedId}`).listen('FinishedProcessingClip', callback)
        },
        onInboundEmailUpdated: (callback: (payload: unknown) => void) => {
            window.Echo.private(`feeds.${feedId}`).listen('InboundEmailUpdated', callback)
        },
        leave: () => {
            window.Echo.leave(`feeds.${feedId}`)
        },
    }),
}
