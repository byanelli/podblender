import { FormEventHandler, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { ArrowLeft, Link2, Loader2, TriangleAlert } from 'lucide-react';

import routes from '@/routes';
import { MetadataResponseBody } from '@/roma';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

type Display = 'form' | 'metadata';

export default function AddClipForm({
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
    const [hasError, setHasError] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');

    const onFailure = (error: any) => {
        setIsLoading(false);
        setHasError(true);
        setErrorMessage(error.response?.data?.message ?? error.response?.data?.error);
    };

    const fetchMetadata: FormEventHandler = (e) => {
        e.preventDefault();
        setIsLoading(true);
        setHasError(false);

        axios
            .post(routes.api.fetchMetadata, { url })
            .then((response: AxiosResponse<MetadataResponseBody>) => {
                setIsLoading(false);
                setMetadataResponse(response.data);
                setDisplay('metadata');
            })
            .catch(onFailure);
    };

    const addClipToFeed: FormEventHandler = (e) => {
        e.preventDefault();
        setIsLoading(true);
        setHasError(false);

        axios
            .post(routes.api.addClipToFeed(feedId), { url })
            .then(() => {
                setIsLoading(false);
                setDisplay('form');
                setUrl('');
                onAdded();
            })
            .catch(onFailure);
    };

    const metadataRows = metadataResponse
        ? [
              { key: 'URL', value: metadataResponse.metadata.canonicalUrl },
              { key: 'Platform', value: metadataResponse.platformType.name },
              { key: 'Title', value: metadataResponse.metadata.title },
              { key: 'Author', value: metadataResponse.metadata.source.name },
              { key: 'Description', value: metadataResponse.metadata.description },
          ]
        : [];

    if (display === 'metadata') {
        return (
            <div className="space-y-5">
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setDisplay('form')}
                        className="text-muted-foreground transition-colors hover:text-foreground"
                        aria-label="Back"
                    >
                        <ArrowLeft className="size-4" />
                    </button>
                    <h3 className="font-display text-base font-semibold">
                        Confirm this clip
                    </h3>
                </div>

                <dl className="divide-y divide-border/60 rounded-lg border border-border/60">
                    {metadataRows.map((row) => (
                        <div
                            key={row.key}
                            className="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4"
                        >
                            <dt className="font-mono text-[0.6875rem] tracking-console text-muted-foreground uppercase">
                                {row.key}
                            </dt>
                            <dd className="text-sm break-words sm:col-span-2">
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>

                <form onSubmit={addClipToFeed}>
                    <Button type="submit" disabled={isLoading}>
                        {isLoading && <Loader2 className="animate-spin" />}
                        Confirm & add
                    </Button>
                </form>
            </div>
        );
    }

    return (
        <form onSubmit={fetchMetadata} className="space-y-5">
            {hasError && (
                <Alert variant="destructive">
                    <TriangleAlert />
                    <AlertTitle>There was an error processing your URL</AlertTitle>
                    <AlertDescription>{errorMessage}</AlertDescription>
                </Alert>
            )}

            <div className="space-y-2">
                <Label htmlFor="clip-url">Add audio clip</Label>
                <div className="relative">
                    <Link2 className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        id="clip-url"
                        name="url"
                        required
                        value={url}
                        className="pl-9"
                        placeholder="https://www.youtube.com/watch?v=9ntPxdWAWq8"
                        onChange={(e) => setUrl(e.target.value)}
                    />
                </div>
            </div>

            <Button type="submit" disabled={isLoading}>
                {isLoading && <Loader2 className="animate-spin" />}
                Fetch metadata
            </Button>
        </form>
    );
}
