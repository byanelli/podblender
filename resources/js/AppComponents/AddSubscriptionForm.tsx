import { FormEventHandler, useState } from 'react';
import axios from 'axios';
import { Link2, Loader2, Radio, Rss, TriangleAlert } from 'lucide-react';

import routes from '@/routes';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';

type NewFeedType = 'custom' | 'subscription';

export default function AddSubscriptionForm({
    onCreated,
}: {
    onCreated: () => void;
}) {
    const [newFeedType, setNewFeedType] = useState<NewFeedType>('custom');
    const [name, setName] = useState('');
    const [url, setUrl] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [hasError, setHasError] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');

    const resetForm = () => {
        setName('');
        setUrl('');
    };

    const onSuccess = () => {
        setIsLoading(false);
        resetForm();
        onCreated();
    };

    const onFailure = (error: any) => {
        setIsLoading(false);
        setHasError(true);
        setErrorMessage(error.response?.data?.message ?? error.response?.data?.error);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setIsLoading(true);
        setHasError(false);

        if (newFeedType === 'subscription') {
            axios
                .post(routes.api.createSubscription, { name, url })
                .then(onSuccess)
                .catch(onFailure);
        } else {
            axios
                .post(routes.api.createCustomFeed, { name })
                .then(onSuccess)
                .catch(onFailure);
        }
    };

    const options: { value: NewFeedType; label: string; hint: string }[] = [
        { value: 'custom', label: 'Custom', hint: 'Add clips by hand' },
        { value: 'subscription', label: 'Subscription', hint: 'Auto-pull a channel' },
    ];

    return (
        <form onSubmit={submit} className="space-y-5">
            {hasError && (
                <Alert variant="destructive">
                    <TriangleAlert />
                    <AlertTitle>Couldn't create that feed</AlertTitle>
                    <AlertDescription>{errorMessage}</AlertDescription>
                </Alert>
            )}

            <div className="grid grid-cols-2 gap-2">
                {options.map((option) => {
                    const active = newFeedType === option.value;
                    return (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => setNewFeedType(option.value)}
                            className={cn(
                                'group flex flex-col items-start gap-1 rounded-xl border-2 border-ink p-3 text-left transition-all',
                                active
                                    ? 'bg-accent shadow-hard-sm'
                                    : 'bg-card hover:-translate-x-px hover:-translate-y-px hover:shadow-hard-sm',
                            )}
                        >
                            <span className="flex items-center gap-2">
                                {option.value === 'custom' ? (
                                    <Rss
                                        className={cn(
                                            'size-4',
                                            active ? 'text-primary' : 'text-muted-foreground',
                                        )}
                                    />
                                ) : (
                                    <Radio
                                        className={cn(
                                            'size-4',
                                            active ? 'text-primary' : 'text-muted-foreground',
                                        )}
                                    />
                                )}
                                <span
                                    className={cn(
                                        'text-sm font-bold',
                                        active ? 'text-foreground' : 'text-muted-foreground',
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
                    placeholder="Weekend Listening"
                    onChange={(e) => setName(e.target.value)}
                />
            </div>

            {newFeedType === 'subscription' && (
                <div className="space-y-2">
                    <Label htmlFor="feed-url">Channel URL</Label>
                    <div className="relative">
                        <Link2 className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="feed-url"
                            name="url"
                            required
                            value={url}
                            className="pl-9"
                            placeholder="https://www.youtube.com/@channel"
                            onChange={(e) => setUrl(e.target.value)}
                        />
                    </div>
                </div>
            )}

            <Button type="submit" disabled={isLoading}>
                {isLoading && <Loader2 className="animate-spin" />}
                Create feed
            </Button>
        </form>
    );
}
