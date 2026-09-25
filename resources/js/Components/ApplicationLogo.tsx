import { useId } from 'react';

import { cn } from '@/lib/utils';

const JUG = 'M17 18h30l-3.8 26.6q-.3 2.4-2.7 2.4H23.5q-2.4 0-2.7-2.4Z';
const BASE = 'M18 47h28l3.6 10.5q.8 2.5-1.8 2.5H16.2q-2.6 0-1.8-2.5Z';
const HANDLE = 'M45.5 23.5h5q2 0 2 2v9q0 2-2 2h-7';

/**
 * Podblender logo: a countertop blender with a hard offset shadow. Colors come
 * from theme tokens. public/favicon.svg is a hex-color copy; update both together.
 */
export default function ApplicationLogo({ className }: { className?: string }) {
    const clipId = useId();

    return (
        <svg
            viewBox="7 5 58 58"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={cn(className)}
            strokeLinejoin="round"
            strokeLinecap="round"
            aria-hidden="true"
        >
            <defs>
                <clipPath id={clipId}>
                    <path d={JUG} />
                </clipPath>
            </defs>

            {/* Offset shadow */}
            <g transform="translate(3 3)" className="fill-foreground">
                <path d={BASE} />
                <path d={JUG} />
                <rect x="14" y="12.5" width="36" height="5.5" rx="2.75" />
                <rect x="27" y="7" width="10" height="6" rx="3" />
                <path
                    d={HANDLE}
                    fill="none"
                    className="stroke-foreground"
                    strokeWidth="7"
                />
            </g>

            {/* Handle */}
            <path d={HANDLE} className="stroke-foreground" strokeWidth="7.5" />
            <path d={HANDLE} className="stroke-primary" strokeWidth="3" />

            {/* Base with dial */}
            <path
                d={BASE}
                className="fill-primary stroke-foreground"
                strokeWidth="3"
            />
            <circle
                cx="32"
                cy="53.5"
                r="3.4"
                className="fill-warning stroke-foreground"
                strokeWidth="2.5"
            />
            <path
                d="M32 53.5l1.7-1.9"
                className="stroke-foreground"
                strokeWidth="1.8"
            />

            {/* Jug and its blended contents */}
            <path d={JUG} className="fill-card" />
            <g clipPath={`url(#${clipId})`}>
                <path
                    d="M10 31q5.5-3 11 0t11 0 11 0 11 0V50H10Z"
                    className="fill-primary"
                />
                <path
                    d="M24 41.5q5-4.5 11-2.5M30 36q4-2 8.5-.5"
                    className="stroke-card"
                    strokeOpacity=".75"
                    strokeWidth="1.8"
                />
                <circle cx="26" cy="35.5" r="1.4" className="fill-card" />
                <circle cx="37.5" cy="42" r=".9" className="fill-card" />
            </g>
            <path
                d="M21.4 21.5l.9 5.5"
                className="stroke-card"
                strokeWidth="2.6"
            />
            <path
                d="M38.2 22.5h4.2M39.2 26.5h3"
                className="stroke-foreground"
                strokeWidth="2"
            />
            <path d={JUG} className="stroke-foreground" strokeWidth="3" />

            {/* Lid and knob */}
            <rect
                x="14"
                y="12.5"
                width="36"
                height="5.5"
                rx="2.75"
                className="fill-primary stroke-foreground"
                strokeWidth="3"
            />
            <path
                d="M18.5 14.6h7"
                className="stroke-card"
                strokeOpacity=".45"
                strokeWidth="1.6"
            />
            <rect
                x="27"
                y="7"
                width="10"
                height="6"
                rx="3"
                className="fill-primary stroke-foreground"
                strokeWidth="3"
            />
        </svg>
    );
}
