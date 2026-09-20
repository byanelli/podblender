import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import axios from "axios";
import { ExternalLink, ListMusic, Rss, Trash2 } from "lucide-react";
import RadioWaves from "@/Components/RadioWaves";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import NewFeedCard from "@/AppComponents/NewFeedCard";
import CopyRssButton from "@/AppComponents/CopyRssButton";
import ErrorPanel from "@/AppComponents/ErrorPanel";
import MetadataSeparator from "@/AppComponents/MetadataSeparator";
import routes from "@/routes";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
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
} from "@/Components/ui/alert-dialog";

type Feed = {
    id: number;
    uuid: string;
    name: string;
    description: string;
    cover_url: string | null;
    subscription_id: number | null;
    subscription: { name: string; platform_url: string } | null;
    audio_clips_count: number;
};

type User = {
    feeds: Feed[];
};

export default function Dashboard({ user }: { user: User }) {
    const [errorMessage, setErrorMessage] = useState("");
    const [isLoading, setIsLoading] = useState(false);

    const reloadUser = () => router.reload({ only: ["user"] });

    const deleteFeed = (feed: Feed) => {
        setErrorMessage("");
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
                    error.response?.data?.message ??
                        error.response?.data?.error,
                );
            });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-end justify-between gap-4">
                    <h1 className="font-display text-4xl font-extrabold tracking-tight sm:text-5xl">
                        Your <span className="accent-underline">feeds</span>
                    </h1>
                    <span className="hidden shrink-0 rounded-full border-2 border-ink bg-secondary px-3 py-1 text-sm font-bold text-secondary-foreground shadow-hard-sm sm:inline-block">
                        {user.feeds.length}{" "}
                        {user.feeds.length === 1 ? "feed" : "feeds"}
                    </span>
                </div>
            }
        >
            <Head title="Feeds" />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    {errorMessage !== "" && (
                        <ErrorPanel
                            message={errorMessage}
                            operation="deleting your feed"
                        />
                    )}

                    {user.feeds.length === 0 ? (
                        <Card className="border-dashed">
                            <CardContent className="py-12 text-center">
                                <Rss className="mx-auto size-8 text-muted-foreground/50" />
                                <p className="mt-3 font-display text-lg font-bold">
                                    No feeds yet
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Create your first feed to start collecting
                                    clips.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <ul className="space-y-3">
                            {user.feeds.map((feed) => (
                                <li key={feed.id}>
                                    <Card className="relative gap-0 py-0 transition-all hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-hard-lg">
                                        {/* items-start keeps the buttons beside the feed name when the name wraps. */}
                                        <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="flex min-w-0 items-start gap-4">
                                                {feed.cover_url && (
                                                    <img
                                                        src={feed.cover_url}
                                                        alt=""
                                                        className="size-12 flex-none rounded-[7px] border-2 border-ink object-cover"
                                                    />
                                                )}
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Link
                                                            href={routes.feed(
                                                                feed.id,
                                                            )}
                                                            className="font-display text-lg leading-tight font-bold transition-colors hover:text-primary after:absolute after:inset-0 after:content-['']"
                                                        >
                                                            {feed.name}
                                                        </Link>
                                                        <Badge
                                                            variant={
                                                                feed.subscription_id ==
                                                                null
                                                                    ? "secondary"
                                                                    : "default"
                                                            }
                                                        >
                                                            {feed.subscription_id ==
                                                            null ? (
                                                                <ListMusic />
                                                            ) : (
                                                                <RadioWaves />
                                                            )}
                                                            {feed.subscription_id ==
                                                            null
                                                                ? "Custom"
                                                                : "Subscription"}
                                                        </Badge>
                                                    </div>
                                                    <div className="mt-1.5 text-xs text-muted-foreground">
                                                        {feed.subscription !=
                                                            null && (
                                                            <>
                                                                <span className="block sm:inline">
                                                                    From{" "}
                                                                    {
                                                                        feed
                                                                            .subscription
                                                                            .name
                                                                    }
                                                                </span>
                                                                <MetadataSeparator />
                                                            </>
                                                        )}
                                                        <span className="block sm:inline">
                                                            {feed.audio_clips_count}{" "}
                                                            {feed.audio_clips_count ===
                                                            1
                                                                ? "clip"
                                                                : "clips"}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="relative z-10 flex flex-none items-center gap-2">
                                                <CopyRssButton
                                                    url={routes.rss(feed.uuid)}
                                                />

                                                {feed.subscription != null && (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground hover:text-foreground"
                                                    >
                                                        <a
                                                            href={
                                                                feed.subscription
                                                                    .platform_url
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            aria-label={`Open source for ${feed.name}`}
                                                        >
                                                            <ExternalLink />
                                                        </a>
                                                    </Button>
                                                )}

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
                                                                Delete{" "}
                                                                {feed.name}?
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This permanently
                                                                removes the feed
                                                                and its RSS
                                                                link. This can't
                                                                be undone.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>
                                                                Cancel
                                                            </AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() =>
                                                                    deleteFeed(
                                                                        feed,
                                                                    )
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
                    <NewFeedCard
                        className="lg:sticky lg:top-24"
                        onCreated={reloadUser}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
