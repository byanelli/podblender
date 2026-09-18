import { useState } from 'react';

import { cn } from '@/lib/utils';

/*
   A clip has no thumbnail while it is still processing, when the platform never offered one, and when the download
   failed. Those rows still get a square of the same size so titles line up down the list.
*/

// The gradients themselves live in app.css. Listing the class names in full (rather than building them from an
// index) keeps them greppable.
const PLACEHOLDER_CLASSES = [
    'clip-placeholder-1',
    'clip-placeholder-2',
    'clip-placeholder-3',
    'clip-placeholder-4',
    'clip-placeholder-5',
];

// The square, the 7px radius and the ink outline have to match on both variants or the rows stop lining up.
const BOX_CLASSES = 'size-12 flex-none rounded-[7px] border-2 border-ink';

export default function ClipThumbnail({
    url,
    clipId,
}: {
    url: string | null;
    clipId: number;
}) {
    // Remembering which URL failed, rather than a plain "it failed" flag, means a clip that finishes processing and
    // gets a new thumbnail will try to load it instead of staying on the placeholder.
    const [failedUrl, setFailedUrl] = useState<string | null>(null);

    if (url === null || url === failedUrl) {
        // The gradient comes from the clip id, so a clip keeps the same one on every render and every page load.
        const placeholder =
            PLACEHOLDER_CLASSES[clipId % PLACEHOLDER_CLASSES.length];

        return (
            <div
                aria-hidden="true"
                className={cn(BOX_CLASSES, 'clip-placeholder', placeholder)}
            />
        );
    }

    return (
        <img
            src={url}
            alt=""
            className={cn(BOX_CLASSES, 'object-cover')}
            onError={() => setFailedUrl(url)}
        />
    );
}
