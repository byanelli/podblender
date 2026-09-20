import { FormEventHandler, useEffect, useRef, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { Link2, Loader2, TriangleAlert } from 'lucide-react';

import routes from '@/routes';
import { MetadataResponseBody } from '@/roma';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';

type Display = 'form' | 'metadata';

export default function AddClipCard({
    feedId,
    onAdded,
}: {
    feedId: number;
    onAdded: () => void;
}) {
    const [display, setDisplay] = useState<Display>('form');
    const [url, setUrl] = useState('');
    const [metadataResponse, setMetadataResponse] =
        useState<MetadataResponseBody | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [errorTitle, setErrorTitle] = useState('');
    const [errorMessage, setErrorMessage] = useState('');

    const urlInputRef = useRef<HTMLInputElement>(null);
    const confirmButtonRef = useRef<HTMLButtonElement>(null);
    const isFirstRender = useRef(true);

    // Focus follows the step, because the control that was pressed is no longer on the page.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        if (display === 'metadata') {
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
        setErrorMessage(error.response?.data?.message ?? error.response?.data?.error);
    };

    const fetchMetadata: FormEventHandler = (e) => {
        e.preventDefault();
        setIsLoading(true);
        clearError();

        axios
            .post(routes.api.fetchMetadata, { url })
            .then((response: AxiosResponse<MetadataResponseBody>) => {
                setIsLoading(false);
                setMetadataResponse(response.data);
                setDisplay('metadata');
            })
            .catch(showError('There was an error processing your URL'));
    };

    const addClipToFeed: FormEventHandler = (e) => {
        e.preventDefault();
        setIsLoading(true);
        clearError();

        axios
            .post(routes.api.addClipToFeed(feedId), { url })
            .then(() => {
                setIsLoading(false);
                setDisplay('form');
                setUrl('');
                onAdded();
            })
            .catch(showError("Couldn't add that clip"));
    };

    const cancel = () => {
        clearError();
        setDisplay('form');
    };

    const errorPanel = errorTitle !== '' && (
        <Alert variant="destructive">
            <TriangleAlert />
            <AlertTitle>{errorTitle}</AlertTitle>
            <AlertDescription>{errorMessage}</AlertDescription>
        </Alert>
    );

    const metadataRows = metadataResponse
        ? [
              { key: 'URL', value: metadataResponse.metadata.canonicalUrl },
              { key: 'Platform', value: metadataResponse.platformType.name },
              { key: 'Title', value: metadataResponse.metadata.title },
              { key: 'Author', value: metadataResponse.metadata.source.name },
              { key: 'Description', value: metadataResponse.metadata.description },
          ]
        : [];

    const metadataStep = (
        // The card's 8px gap is tight above a bordered table.
        <div className="space-y-5 pt-1">
            {errorPanel}

            <dl className="divide-y divide-border rounded-xl border-2 border-ink">
                {metadataRows.map((row) => (
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

            <form onSubmit={addClipToFeed} className="flex flex-wrap gap-2">
                <Button type="submit" disabled={isLoading} ref={confirmButtonRef}>
                    {isLoading && <Loader2 className="animate-spin" />}
                    Confirm & add
                </Button>
                {/* Cancel keeps the typed URL, so a mistake in it can be corrected. */}
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

    const urlStep = (
        <form onSubmit={fetchMetadata} className="space-y-5">
            {errorPanel}

            <div className="relative">
                <Link2 className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id="clip-url"
                    name="url"
                    required
                    value={url}
                    className="pl-9"
                    placeholder="https://www.youtube.com/watch?v=9ntPxdWAWq8"
                    aria-label="URL of the clip to add"
                    ref={urlInputRef}
                    onChange={(e) => setUrl(e.target.value)}
                />
            </div>

            <Button type="submit" disabled={isLoading}>
                {isLoading && <Loader2 className="animate-spin" />}
                Fetch metadata
            </Button>
        </form>
    );

    return (
        <Card className="gap-2">
            <CardHeader>
                <CardTitle>
                    {display === 'metadata' ? 'Confirm this clip' : 'Add a clip'}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {display === 'metadata' ? metadataStep : urlStep}
            </CardContent>
        </Card>
    );
}
