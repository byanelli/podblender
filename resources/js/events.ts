export default {
    finishedProcessingClip: (feedId: number) => ({
        listen: (callback: Function) => {
            window.Echo.private(`feeds.${feedId}`).listen('FinishedProcessingClip', callback)
        }
    }),
}
