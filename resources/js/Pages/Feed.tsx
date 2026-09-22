import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { format, parseISO } from 'date-fns';
import {
    ExternalLink,
    ListMusic,
    Pause,
    Play,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import RadioWaves from '@/Components/RadioWaves';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AddClipCard from '@/AppComponents/AddClipCard';
import ClipThumbnail from '@/AppComponents/ClipThumbnail';
import ErrorPanel from '@/AppComponents/ErrorPanel';
import MetadataSeparator from '@/AppComponents/MetadataSeparator';
import events from '@/events';
import routes from '@/routes';
import { AudioClip, ClipProcessingState, Feed as FeedType } from '@/types';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import { cn } from '@/lib/utils';

type StateName = ClipProcessingState['name'];

const STATE_VARIANT: Record<
    StateName,
    'warning' | 'success' | 'secondary' | 'destructive'
> = {
    Processing: 'warning',
    Processed: 'success',
    Unavailable: 'secondary',
    Failed: 'destructive',
};

function StatusBadge({ state }: { state: ClipProcessingState }) {
    const variant = STATE_VARIANT[state.name] ?? 'destructive';
    const label = STATE_VARIANT[state.name] ? state.name : 'Unknown';

    return (
        <Badge variant={variant}>
            <span
                className={cn(
                    'size-1.5 rounded-full',
                    variant === 'warning' && 'bg-warning animate-signal',
                    variant === 'success' && 'bg-success',
                    variant === 'destructive' && 'bg-destructive',
                    variant === 'secondary' && 'bg-muted-foreground/60',
                )}
            />
            {label}
        </Badge>
    );
}

function formatDate(value: string): string {
    try {
        return format(parseISO(value), 'MMM do yyyy');
    } catch {
        return value;
    }
}

function previewLabel(clip: AudioClip, isPlaying: boolean): string {
    switch (clip.processing_state.name) {
        case 'Processed':
            return isPlaying ? `Pause ${clip.title}` : `Preview ${clip.title}`;
        case 'Processing':
            return `Still processing ${clip.title}`;
        default:
            return `Audio unavailable for ${clip.title}`;
    }
}

export default function Feed({ feed }: { feed: FeedType }) {
    const [errorMessage, setErrorMessage] = useState('');
    const [errorOperation, setErrorOperation] = useState('deleting your clip');
    const [isLoading, setIsLoading] = useState(false);
    const [playingId, setPlayingId] = useState<number | null>(null);
    const audioRef = useRef<HTMLAudioElement | null>(null);

    const reloadFeed = () => router.reload({ only: ['feed'] });

    useEffect(() => {
        return () => audioRef.current?.pause();
    }, []);

    useEffect(() => {
        const channel = events.finishedProcessingClip(feed.id);
        channel.listen(() => router.reload({ only: ['feed'] }));

        return () => channel.leave();
    }, [feed.id]);

    const togglePlayback = (clip: AudioClip) => {
        if (clip.processing_state.name !== 'Processed') {
            return;
        }

        if (playingId === clip.id) {
            audioRef.current?.pause();
            setPlayingId(null);
            return;
        }

        if (!audioRef.current) {
            audioRef.current = new Audio();
            audioRef.current.addEventListener('ended', () => setPlayingId(null));
        }

        audioRef.current.src = clip.preview_url as string;
        void audioRef.current.play();
        setPlayingId(clip.id);
    };

    const deleteClip = (clip: AudioClip) => {
        setErrorMessage('');
        setErrorOperation('deleting your clip');
        setIsLoading(true);

        axios
            .delete(routes.api.deleteClip(feed.id, clip.id))
            .then(() => {
                reloadFeed();
                setIsLoading(false);
            })
            .catch((error) => {
                setIsLoading(false);
                setErrorMessage(
                    error.response?.data?.message ?? error.response?.data?.error,
                );
            });
    };

    const retryClip = (clip: AudioClip) => {
        setErrorMessage('');
        setErrorOperation('retrying your download');
        setIsLoading(true);

        axios
            .post(routes.api.retryClip(feed.id, clip.id))
            .then(() => {
                reloadFeed();
                setIsLoading(false);
            })
            .catch((error) => {
                setIsLoading(false);
                setErrorMessage(
                    error.response?.data?.message ?? error.response?.data?.error,
                );
            });
    };

    const clipCount = feed.audio_clips.length;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-stretch gap-4">
                    {/* The cover's height comes from the row, so it meets the top of the chip and the bottom of the
                        clip count, and stops at the height of a one-line name. Its width is fixed so the heading
                        does not move when the image loads. */}
                    {feed.cover_url && (
                        <img
                            src={feed.cover_url}
                            alt=""
                            className="max-h-26 w-26 flex-none rounded-[7px] border-2 border-ink object-cover shadow-hard-sm sm:max-h-28 sm:w-28"
                        />
                    )}
                    <div className="min-w-0">
                        <span className="inline-flex items-center gap-1.5 rounded-full border-2 border-ink bg-secondary px-3 py-1 text-xs font-bold text-secondary-foreground shadow-hard-sm">
                            {feed.subscription != null ? (
                                <>
                                    <RadioWaves className="size-3.5" />
                                    {feed.subscription.name} ·{' '}
                                    {feed.subscription.platform_type.name}
                                </>
                            ) : (
                                <>
                                    <ListMusic className="size-3.5" />
                                    Custom feed
                                </>
                            )}
                        </span>
                        <h1 className="mt-3 font-display text-4xl font-extrabold tracking-tight sm:text-5xl">
                            {feed.name}
                        </h1>
                        <p className="mt-1 text-sm font-semibold text-muted-foreground">
                            {clipCount} {clipCount === 1 ? 'clip' : 'clips'}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={feed.name} />

            <div className="space-y-6">
                {errorMessage !== '' && (
                    <ErrorPanel
                        message={errorMessage}
                        operation={errorOperation}
                    />
                )}

                {feed.subscription == null && (
                    <AddClipCard feedId={feed.id} onAdded={reloadFeed} />
                )}

                {clipCount === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="py-12 text-center text-sm text-muted-foreground">
                            No clips to display yet.
                        </CardContent>
                    </Card>
                ) : (
                    <ul className="space-y-2">
                        {feed.audio_clips.map((clip) => (
                            <li key={clip.id}>
                                <Card className="gap-0 py-0 transition-all hover:-translate-x-px hover:-translate-y-px hover:shadow-hard-lg">
                                    {/* On a phone the actions drop to a row of their own at the bottom, the way a
                                        feed card stacks, so the title gets the full width beside the thumbnail. */}
                                    <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="flex min-w-0 items-start gap-4">
                                            <ClipThumbnail
                                                url={clip.thumbnail_url}
                                                clipId={clip.id}
                                            />

                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-display text-lg leading-tight font-bold">
                                                        {clip.title}
                                                    </p>
                                                    {/* The full width drops the badges onto a line of their own
                                                        on a phone; from sm they sit after the title again. */}
                                                    <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                                                        <StatusBadge
                                                            state={
                                                                clip.processing_state
                                                            }
                                                        />
                                                        <Badge variant="outline">
                                                            {
                                                                clip.audio_source
                                                                    .platform_type.name
                                                            }
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <div className="mt-1.5 text-xs text-muted-foreground">
                                                    <span className="block sm:inline">
                                                        From {clip.audio_source.name}
                                                    </span>
                                                    <MetadataSeparator />
                                                    <span className="block sm:inline">
                                                        Published{' '}
                                                        {formatDate(clip.published_at)}
                                                    </span>
                                                    <MetadataSeparator />
                                                    <span className="block sm:inline">
                                                        Added{' '}
                                                        {formatDate(clip.created_at)}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* The wider gap gives the icons room to be tapped apart on a phone. */}
                                        <div className="flex flex-none items-center gap-2 sm:gap-1">
                                            {clip.preview_url && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className={
                                                        playingId === clip.id
                                                            ? 'text-primary hover:text-primary'
                                                            : 'text-muted-foreground hover:text-foreground'
                                                    }
                                                    disabled={
                                                        clip.processing_state.name !==
                                                        'Processed'
                                                    }
                                                    aria-label={previewLabel(
                                                        clip,
                                                        playingId === clip.id,
                                                    )}
                                                    title={
                                                        clip.processing_state.name ===
                                                        'Processed'
                                                            ? undefined
                                                            : previewLabel(
                                                                  clip,
                                                                  playingId === clip.id,
                                                              )
                                                    }
                                                    onClick={() => togglePlayback(clip)}
                                                >
                                                    {playingId === clip.id ? (
                                                        <Pause />
                                                    ) : (
                                                        <Play />
                                                    )}
                                                </Button>
                                            )}

                                            {clip.processing_state.name ===
                                                'Failed' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-muted-foreground hover:text-foreground"
                                                    disabled={isLoading}
                                                    aria-label={`Retry ${clip.title}`}
                                                    title="Retry download"
                                                    onClick={() => retryClip(clip)}
                                                >
                                                    <RotateCcw />
                                                </Button>
                                            )}

                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-foreground"
                                            >
                                                <a
                                                    href={clip.platform_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    aria-label="Open source"
                                                >
                                                    <ExternalLink />
                                                </a>
                                            </Button>

                                            <AlertDialog>
                                                <AlertDialogTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground hover:text-destructive"
                                                        disabled={isLoading}
                                                        aria-label={`Delete ${clip.title}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>
                                                            Delete this clip?
                                                        </AlertDialogTitle>
                                                        <AlertDialogDescription>
                                                            "{clip.title}" will be
                                                            removed from this feed. This
                                                            can't be undone.
                                                        </AlertDialogDescription>
                                                    </AlertDialogHeader>
                                                    <AlertDialogFooter>
                                                        <AlertDialogCancel>
                                                            Cancel
                                                        </AlertDialogCancel>
                                                        <AlertDialogAction
                                                            onClick={() =>
                                                                deleteClip(clip)
                                                            }
                                                            className="bg-destructive text-destructive-foreground hover:brightness-110"
                                                        >
                                                            Delete clip
                                                        </AlertDialogAction>
                                                    </AlertDialogFooter>
                                                </AlertDialogContent>
                                            </AlertDialog>
                                        </div>
                                    </div>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
