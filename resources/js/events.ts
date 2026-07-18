export default {
    finishedProcessingClip: (feedId: number) => ({
        listen: (callback: (payload: unknown) => void) => {
            window.Echo.private(`feeds.${feedId}`).listen('FinishedProcessingClip', callback)
        },
        leave: () => {
            window.Echo.leave(`feeds.${feedId}`)
        },
    }),
}
