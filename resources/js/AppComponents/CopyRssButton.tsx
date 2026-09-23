import { useRef, useState } from "react";
import { Check, Rss } from "lucide-react";

import { Button } from "@/Components/ui/button";
import { copyToClipboard } from "@/lib/clipboard";

/**
 * Copies a feed's RSS URL to the clipboard, since the URL is what gets pasted into a podcast app. On copy, action
 * lines animate around the button and the label reads "Copied!" briefly.
 */
export default function CopyRssButton({ url }: { url: string }) {
    const [copied, setCopied] = useState(false);
    const [burstKey, setBurstKey] = useState(0);
    const resetTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

    const copy = async () => {
        await copyToClipboard(url);

        // A new key remounts the burst, which restarts its animation on repeated clicks.
        setBurstKey((key) => key + 1);
        setCopied(true);
        clearTimeout(resetTimer.current);
        resetTimer.current = setTimeout(() => setCopied(false), 1800);
    };

    return (
        <div className="relative">
            <Button
                variant="flat"
                size="sm"
                onClick={copy}
                aria-live="polite"
            >
                {copied ? <Check /> : <Rss />}
                {copied ? "Copied!" : "RSS"}
            </Button>

            {copied && (
                <svg
                    key={burstKey}
                    viewBox="0 0 100 60"
                    aria-hidden="true"
                    className="animate-action-burst pointer-events-none absolute -top-4 -right-4 -bottom-4 -left-4 h-[calc(100%+2rem)] w-[calc(100%+2rem)] overflow-visible text-primary"
                >
                    <g
                        stroke="currentColor"
                        strokeWidth="3.5"
                        strokeLinecap="round"
                    >
                        <line x1="18" y1="8" x2="24" y2="16" />
                        <line x1="50" y1="2" x2="50" y2="11" />
                        <line x1="82" y1="8" x2="76" y2="16" />
                        <line x1="18" y1="52" x2="24" y2="44" />
                        <line x1="50" y1="58" x2="50" y2="49" />
                        <line x1="82" y1="52" x2="76" y2="44" />
                    </g>
                </svg>
            )}
        </div>
    );
}
