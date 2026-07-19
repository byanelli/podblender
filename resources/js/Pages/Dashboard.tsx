import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import axios from "axios";
import { ListMusic, Rss, Trash2 } from "lucide-react";
import RadioWaves from "@/Components/RadioWaves";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AddSubscriptionForm from "@/AppComponents/AddSubscriptionForm";
import CopyRssButton from "@/AppComponents/CopyRssButton";
import ErrorPanel from "@/AppComponents/ErrorPanel";
import routes from "@/routes";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
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
    subscription_id: number | null;
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
                                        <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="min-w-0">
                                                <Link
                                                    href={routes.feed(feed.id)}
                                                    className="font-display text-lg font-bold transition-colors hover:text-primary after:absolute after:inset-0 after:content-['']"
                                                >
                                                    {feed.name}
                                                </Link>
                                                <div className="mt-1.5 flex items-center gap-2">
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
                                                    <span className="text-xs font-semibold text-muted-foreground">
                                                        {feed.audio_clips_count}{" "}
                                                        {feed.audio_clips_count ===
                                                        1
                                                            ? "clip"
                                                            : "clips"}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="relative z-10 flex flex-none items-center gap-2">
                                                <CopyRssButton
                                                    url={routes.rss(feed.uuid)}
                                                />

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
                    <Card className="lg:sticky lg:top-24">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <span className="grid size-7 place-items-center rounded-lg border-2 border-ink bg-accent text-foreground">
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
