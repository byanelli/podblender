import { useState } from 'react';

import { cn } from '@/lib/utils';

/*
   A clip has no thumbnail while it is processing, when the platform has none, or when the download failed. Those rows
   get a placeholder of the same size so titles stay aligned.
*/

// The gradients are defined in app.css. The class names are written out in full so they can be found with grep.
const PLACEHOLDER_CLASSES = [
    'clip-placeholder-1',
    'clip-placeholder-2',
    'clip-placeholder-3',
    'clip-placeholder-4',
    'clip-placeholder-5',
];

// Shared by the image and the placeholder so rows stay aligned.
const BOX_CLASSES = 'size-12 flex-none rounded-[7px] border-2 border-ink';

export default function ClipThumbnail({
    url,
    clipId,
}: {
    url: string | null;
    clipId: number;
}) {
    // Stores the failed URL so that a clip whose thumbnail URL later changes loads the new one.
    const [failedUrl, setFailedUrl] = useState<string | null>(null);

    if (url === null || url === failedUrl) {
        // Chosen by clip id so a clip's gradient is the same on every render and page load.
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
