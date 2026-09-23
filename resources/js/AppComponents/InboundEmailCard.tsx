import { useRef, useState } from 'react';
import axios from 'axios';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { Check, Copy, Mail, RefreshCw } from 'lucide-react';

import routes from '@/routes';
import { InboundEmail } from '@/types';
import { copyToClipboard } from '@/lib/clipboard';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
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

const STATUS_VARIANT: Record<InboundEmail['status']['name'], 'warning' | 'success' | 'destructive'> = {
    Pending: 'warning',
    Added: 'success',
    Failed: 'destructive',
};

function timeAgo(value: string): string {
    try {
        return formatDistanceToNow(parseISO(value), { addSuffix: true });
    } catch {
        return value;
    }
}

export default function InboundEmailCard({
    feedId,
    address,
    emails,
    onChanged,
    onError,
}: {
    feedId: number;
    address: string;
    emails: InboundEmail[];
    onChanged: () => void;
    onError: (message: string) => void;
}) {
    const [copied, setCopied] = useState(false);
    const [isRegenerating, setIsRegenerating] = useState(false);
    const resetTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

    const copy = async () => {
        await copyToClipboard(address);
        setCopied(true);
        clearTimeout(resetTimer.current);
        resetTimer.current = setTimeout(() => setCopied(false), 1800);
    };

    const regenerate = () => {
        setIsRegenerating(true);

        axios
            .post(routes.api.regenerateInboundEmailAddress(feedId))
            .then(onChanged)
            .catch((error) =>
                onError(error.response?.data?.message ?? error.response?.data?.error),
            )
            .finally(() => setIsRegenerating(false));
    };

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>Add by email</CardTitle>
                <CardDescription>
                    Email a link to this address and it's added to the feed. The first link in the
                    subject or body is used.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex flex-wrap items-center gap-2">
                    <code className="min-w-0 flex-1 rounded-lg border-2 border-ink bg-muted px-3 py-2 text-sm break-all">
                        {address}
                    </code>
                    <Button variant="flat" size="sm" onClick={copy} aria-live="polite">
                        {copied ? <Check /> : <Copy />}
                        {copied ? 'Copied!' : 'Copy'}
                    </Button>
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button variant="ghost" size="sm" disabled={isRegenerating}>
                                <RefreshCw className={isRegenerating ? 'animate-spin' : undefined} />
                                New address
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Replace this address?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Mail sent to the current address will be ignored. Do this if the
                                    address has been shared with someone who shouldn't have it.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction onClick={regenerate}>
                                    Replace address
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>

                {emails.length > 0 && (
                    <ul className="divide-y divide-border rounded-xl border-2 border-ink">
                        {emails.map((email) => (
                            <li key={email.id} className="flex items-start gap-3 px-4 py-3">
                                <Mail className="mt-0.5 size-4 flex-none text-muted-foreground" />
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="min-w-0 truncate text-sm font-bold">
                                            {email.subject || '(no subject)'}
                                        </p>
                                        <Badge variant={STATUS_VARIANT[email.status.name]}>
                                            {email.status.name}
                                        </Badge>
                                    </div>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        From {email.sender}, {timeAgo(email.created_at)}
                                    </p>
                                    {email.status.name === 'Failed' && email.failure_reason && (
                                        <p className="mt-1 text-xs text-destructive">
                                            {email.failure_reason}
                                        </p>
                                    )}
                                    {email.url && (
                                        <p className="mt-1 truncate text-xs text-muted-foreground">
                                            {email.url}
                                        </p>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
