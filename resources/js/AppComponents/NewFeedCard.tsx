import { FormEventHandler, useEffect, useRef, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { Link2, ListMusic, Loader2, Rss, TriangleAlert } from 'lucide-react';
import RadioWaves from '@/Components/RadioWaves';

import routes from '@/routes';
import { SourceMetadataResponseBody } from '@/roma';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import SubscriptionOptions, {
    BackfillChoice,
    backfillSinceFor,
    episodesImplied,
} from '@/AppComponents/SubscriptionOptions';

type NewFeedType = 'custom' | 'subscription';

/**
 * A subscription is created in two steps: look up the source at the URL, then
 * confirm. The confirm step shows the source's episode count, since a full
 * backfill can be hundreds of downloads.
 */
type Display = 'form' | 'confirm';

export default function NewFeedCard({
    className,
    onCreated,
}: {
    className?: string;
    onCreated: () => void;
}) {
    const [newFeedType, setNewFeedType] = useState<NewFeedType>('custom');
    const [display, setDisplay] = useState<Display>('form');
    const [name, setName] = useState('');
    const [url, setUrl] = useState('');
    const [source, setSource] = useState<SourceMetadataResponseBody | null>(null);
    const [backfill, setBackfill] = useState<BackfillChoice>('default');
    const [since, setSince] = useState('');
    const [tracksNewEpisodes, setTracksNewEpisodes] = useState(true);
    const [isLoading, setIsLoading] = useState(false);
    const [errorTitle, setErrorTitle] = useState('');
    const [errorMessage, setErrorMessage] = useState('');

    const urlInputRef = useRef<HTMLInputElement>(null);
    const confirmButtonRef = useRef<HTMLButtonElement>(null);
    const isFirstRender = useRef(true);

    const onConfirmStep = display === 'confirm' && source !== null;

    // Focus follows the step, because the control that was pressed is no longer on the page.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        if (display === 'confirm') {
            confirmButtonRef.current?.focus();
        } else {
            urlInputRef.current?.focus();
        }
    }, [display]);

    const clearError = () => {
        setErrorTitle('');
        setErrorMessage('');
    };

    const showError = (title: string) => (error: any) => {
        setIsLoading(false);
        setErrorTitle(title);
        setErrorMessage(
            error.response?.data?.message ?? error.response?.data?.error,
        );
    };

    const resetForm = () => {
        setName('');
        setUrl('');
        setSource(null);
        setBackfill('default');
        setSince('');
        setTracksNewEpisodes(true);
        setDisplay('form');
    };

    const onSuccess = () => {
        setIsLoading(false);
        resetForm();
        onCreated();
    };

    const cancel = () => {
        clearError();
        setDisplay('form');
    };

    /**
     * For a subscription, looks up the source at the URL. A custom feed has no
     * source, so it's created immediately.
     */
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setIsLoading(true);
        clearError();

        if (newFeedType === 'custom') {
            axios
                .post(routes.api.createCustomFeed, { name })
                .then(onSuccess)
                .catch(showError("Couldn't create that feed"));

            return;
        }

        axios
            .post(routes.api.fetchSourceMetadata, { url })
            .then((response: AxiosResponse<SourceMetadataResponseBody>) => {
                setIsLoading(false);
                setSource(response.data);
                setDisplay('confirm');
            })
            .catch(showError('There was an error processing your URL'));
    };

    const createSubscription: FormEventHandler = (e) => {
        e.preventDefault();
        setIsLoading(true);
        clearError();

        axios
            .post(routes.api.createSubscription, {
                name,
                url,
                backfillSince: backfillSinceFor(backfill, since),
                tracksNewEpisodes,
            })
            .then(onSuccess)
            .catch(showError("Couldn't create that subscription"));
    };

    const options: { value: NewFeedType; label: string; hint: string }[] = [
        { value: 'custom', label: 'Custom', hint: 'Add clips by hand' },
        {
            value: 'subscription',
            label: 'Subscription',
            hint: 'Auto-pull a channel or feed',
        },
    ];

    const episodes = source
        ? episodesImplied(backfill, source.metadata.clipCount)
        : null;

    const errorPanel = errorTitle !== '' && (
        <Alert variant="destructive">
            <TriangleAlert />
            <AlertTitle>{errorTitle}</AlertTitle>
            <AlertDescription>{errorMessage}</AlertDescription>
        </Alert>
    );

    const confirmStep = source && (
        <div className="space-y-5">
            {errorPanel}

            <dl className="divide-y divide-border rounded-xl border-2 border-ink">
                {[
                    {
                        key: source.metadata.type.name,
                        value: source.metadata.name,
                    },
                    { key: 'By', value: source.metadata.authorName },
                    {
                        key: 'Episodes',
                        value:
                            source.metadata.clipCount === null
                                ? 'Unknown'
                                : source.metadata.clipCount.toLocaleString(),
                    },
                ].map((row) => (
                    <div
                        key={row.key}
                        className="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4"
                    >
                        <dt className="text-xs font-bold tracking-console text-muted-foreground uppercase">
                            {row.key}
                        </dt>
                        <dd className="text-sm break-words sm:col-span-2">
                            {row.value}
                        </dd>
                    </div>
                ))}
            </dl>

            <SubscriptionOptions
                backfill={backfill}
                onBackfillChange={setBackfill}
                since={since}
                onSinceChange={setSince}
                tracksNewEpisodes={tracksNewEpisodes}
                onTracksNewEpisodesChange={setTracksNewEpisodes}
            />

            {episodes !== null && episodes > LARGE_BACKFILL && (
                <Alert>
                    <TriangleAlert />
                    <AlertTitle>
                        That's {episodes.toLocaleString()} episodes
                    </AlertTitle>
                    <AlertDescription>
                        They're downloaded one at a time, so a back catalog
                        this size will take a while to fill in. Episodes appear
                        in the feed as they finish.
                    </AlertDescription>
                </Alert>
            )}

            <form
                onSubmit={createSubscription}
                className="flex flex-wrap gap-2"
            >
                <Button type="submit" disabled={isLoading} ref={confirmButtonRef}>
                    {isLoading && <Loader2 className="animate-spin" />}
                    Confirm & subscribe
                </Button>
                {/* Cancel keeps the name and URL, so a mistake in them can be corrected. */}
                <Button
                    type="button"
                    variant="outline"
                    disabled={isLoading}
                    onClick={cancel}
                >
                    Cancel
                </Button>
            </form>
        </div>
    );

    const formStep = (
        <form onSubmit={submit} className="space-y-5">
            {errorPanel}

            <div className="grid grid-cols-2 gap-2">
                {options.map((option) => {
                    const active = newFeedType === option.value;
                    return (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => setNewFeedType(option.value)}
                            className={cn(
                                'group flex cursor-pointer flex-col items-start gap-1 rounded-xl border-2 border-ink p-3 text-left transition-colors',
                                active ? 'bg-accent' : 'bg-card hover:bg-accent',
                            )}
                        >
                            <span className="flex items-center gap-2">
                                {option.value === 'custom' ? (
                                    <ListMusic
                                        className={cn(
                                            'size-4',
                                            active
                                                ? 'text-primary'
                                                : 'text-muted-foreground',
                                        )}
                                    />
                                ) : (
                                    <RadioWaves
                                        className={cn(
                                            'size-4',
                                            active
                                                ? 'text-primary'
                                                : 'text-muted-foreground',
                                        )}
                                    />
                                )}
                                <span
                                    className={cn(
                                        'text-sm font-bold',
                                        active
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {option.label}
                                </span>
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {option.hint}
                            </span>
                        </button>
                    );
                })}
            </div>

            <div className="space-y-2">
                <Label htmlFor="feed-name">Name</Label>
                <Input
                    id="feed-name"
                    name="name"
                    required
                    value={name}
                    placeholder="Lectures"
                    // Password managers treat a field called "name" as a
                    // person's name and offer to fill it in.
                    autoComplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    onChange={(e) => setName(e.target.value)}
                />
            </div>

            {newFeedType === 'subscription' && (
                <div className="space-y-2">
                    <Label htmlFor="feed-url">
                        Channel, profile, playlist, or RSS feed URL
                    </Label>
                    <div className="relative">
                        <Link2 className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="feed-url"
                            name="url"
                            required
                            value={url}
                            className="pl-9"
                            placeholder="https://www.youtube.com/@channel"
                            ref={urlInputRef}
                            onChange={(e) => setUrl(e.target.value)}
                        />
                    </div>
                </div>
            )}

            <Button type="submit" disabled={isLoading}>
                {isLoading && <Loader2 className="animate-spin" />}
                {newFeedType === 'subscription' ? 'Continue' : 'Create feed'}
            </Button>
        </form>
    );

    return (
        <Card className={cn('gap-2', className)}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <span className="grid size-7 place-items-center rounded-lg border-2 border-ink bg-accent text-foreground">
                        <Rss className="size-4" />
                    </span>
                    {onConfirmStep ? 'Confirm this subscription' : 'New feed'}
                </CardTitle>
            </CardHeader>
            {/* The card's 8px gap is tight above the bordered blocks both steps start with. */}
            <CardContent className="pt-1">
                {onConfirmStep ? confirmStep : formStep}
            </CardContent>
        </Card>
    );
}

/**
 * Episode count above which a full backfill shows a warning. Downloads run one
 * at a time so the platform doesn't block them, so a large backfill is slow.
 */
const LARGE_BACKFILL = 50;
