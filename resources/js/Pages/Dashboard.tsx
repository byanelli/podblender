import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { Radio, Rss, Trash2 } from 'lucide-react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AddSubscriptionForm from '@/AppComponents/AddSubscriptionForm';
import ErrorPanel from '@/AppComponents/ErrorPanel';
import routes from '@/routes';
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

type Feed = {
    id: number;
    uuid: string;
    name: string;
    description: string;
    subscription_id: number | null;
    audio_clips_count: number;
};

type User = {
    feeds: Feed[];
};

export default function Dashboard({ user }: { user: User }) {
    const [errorMessage, setErrorMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    const reloadUser = () => router.reload({ only: ['user'] });

    const deleteFeed = (feed: Feed) => {
        setErrorMessage('');
        setIsLoading(true);

        axios
            .delete(routes.api.deleteFeed(feed.id))
            .then(() => {
                reloadUser();
                setIsLoading(false);
            })
            .catch((error) => {
                setIsLoading(false);
                setErrorMessage(
                    error.response?.data?.message ?? error.response?.data?.error,
                );
            });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <p className="font-mono text-[0.6875rem] tracking-console text-muted-foreground uppercase">
                            On air
                        </p>
                        <h1 className="mt-1 font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                            Your feeds
                        </h1>
                    </div>
                    <div className="hidden font-mono text-xs text-muted-foreground sm:block">
                        {user.feeds.length}{' '}
                        {user.feeds.length === 1 ? 'feed' : 'feeds'}
                    </div>
                </div>
            }
        >
            <Head title="Feeds" />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    {errorMessage !== '' && (
                        <ErrorPanel
                            message={errorMessage}
                            operation="deleting your feed"
                        />
                    )}

                    {user.feeds.length === 0 ? (
                        <Card className="border-dashed">
                            <CardContent className="py-12 text-center">
                                <Rss className="mx-auto size-8 text-muted-foreground/50" />
                                <p className="mt-3 font-display text-lg">
                                    No feeds yet
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Create your first feed to start collecting clips.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <ul className="space-y-3">
                            {user.feeds.map((feed) => (
                                <li key={feed.id}>
                                    <Card className="gap-0 py-0 transition-colors hover:border-border">
                                        <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="min-w-0">
                                                <Link
                                                    href={routes.feed(feed.id)}
                                                    className="font-display text-lg font-medium transition-colors hover:text-primary"
                                                >
                                                    {feed.name}
                                                </Link>
                                                <div className="mt-1.5 flex items-center gap-2">
                                                    <Badge
                                                        variant={
                                                            feed.subscription_id == null
                                                                ? 'secondary'
                                                                : 'default'
                                                        }
                                                    >
                                                        {feed.subscription_id == null ? (
                                                            <Rss />
                                                        ) : (
                                                            <Radio />
                                                        )}
                                                        {feed.subscription_id == null
                                                            ? 'Custom'
                                                            : 'Subscription'}
                                                    </Badge>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {feed.audio_clips_count}{' '}
                                                        {feed.audio_clips_count === 1
                                                            ? 'clip'
                                                            : 'clips'}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="flex flex-none items-center gap-2">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <a
                                                        href={routes.rss(feed.uuid)}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <Rss />
                                                        RSS
                                                    </a>
                                                </Button>

                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-destructive"
                                                            disabled={isLoading}
                                                            aria-label={`Delete ${feed.name}`}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>
                                                                Delete {feed.name}?
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This permanently removes
                                                                the feed and its RSS
                                                                link. This can't be
                                                                undone.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>
                                                                Cancel
                                                            </AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() =>
                                                                    deleteFeed(feed)
                                                                }
                                                                className="bg-destructive text-destructive-foreground hover:brightness-110"
                                                            >
                                                                Delete feed
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

                <div className="lg:col-span-1">
                    <Card className="lg:sticky lg:top-24">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <span className="grid size-7 place-items-center rounded-md bg-primary/15 text-primary">
                                    <Rss className="size-4" />
                                </span>
                                New feed
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AddSubscriptionForm onCreated={reloadUser} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
