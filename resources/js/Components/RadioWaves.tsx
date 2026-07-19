import { SVGProps } from 'react';

/**
 * A broadcast mark for subscription feeds: a filled center dot with widely spaced wave arcs. Replaces lucide's Radio,
 * whose small hollow circle and tightly packed arcs looked unbalanced next to ListMusic in the feed-type chips.
 */
export default function RadioWaves(props: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            {...props}
        >
            <circle cx="12" cy="12" r="2.6" fill="currentColor" stroke="none" />
            <path d="M6.8 8.4a6.9 6.9 0 0 0 0 7.2" />
            <path d="M2.8 6a11.6 11.6 0 0 0 0 12" />
            <path d="M17.2 8.4a6.9 6.9 0 0 1 0 7.2" />
            <path d="M21.2 6a11.6 11.6 0 0 1 0 12" />
        </svg>
    );
}
