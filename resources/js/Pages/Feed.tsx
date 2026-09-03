import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { format, parseISO } from 'date-fns';
import { ExternalLink, ListMusic, Pause, Play, Trash2 } from 'lucide-react';
import RadioWaves from '@/Components/RadioWaves';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AddClipForm from '@/AppComponents/AddClipForm';
import ErrorPanel from '@/AppComponents/ErrorPanel';
import events from '@/events';
import routes from '@/routes';
import { AudioClip, ClipProcessingState, Feed as FeedType } from '@/types';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
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

export default function Feed({ feed }: { feed: FeedType }) {
    const [errorMessage, setErrorMessage] = useState('');
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

    const clipCount = feed.audio_clips.length;

    return (
        <AuthenticatedLayout
            header={
                <div>
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
            }
        >
            <Head title={feed.name} />

            <div className="space-y-6">
                {errorMessage !== '' && (
                    <ErrorPanel
                        message={errorMessage}
                        operation="deleting your clip"
                    />
                )}

                {feed.subscription == null && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Add a clip</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AddClipForm feedId={feed.id} onAdded={reloadFeed} />
                        </CardContent>
                    </Card>
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
                                    <div className="flex items-start justify-between gap-4 p-4">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-bold">
                                                    {clip.title}
                                                </p>
                                                <StatusBadge
                                                    state={clip.processing_state}
                                                />
                                                <Badge variant="outline">
                                                    {
                                                        clip.audio_source
                                                            .platform_type.name
                                                    }
                                                </Badge>
                                            </div>
                                            <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                                <span>From {clip.audio_source.name}</span>
                                                <span className="opacity-40">/</span>
                                                <span>
                                                    Published{' '}
                                                    {formatDate(clip.published_at)}
                                                </span>
                                                <span className="opacity-40">/</span>
                                                <span>
                                                    Added {formatDate(clip.created_at)}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex flex-none items-center gap-1">
                                            {clip.preview_url && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className={
                                                        playingId === clip.id
                                                            ? 'text-primary hover:text-primary'
                                                            : 'text-muted-foreground hover:text-foreground'
                                                    }
                                                    aria-label={
                                                        playingId === clip.id
                                                            ? `Pause ${clip.title}`
                                                            : `Preview ${clip.title}`
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
