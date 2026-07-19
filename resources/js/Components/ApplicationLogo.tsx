import { cn } from '@/lib/utils';

/**
 * Podblender brand mark: a chunky, friendly countertop blender — cream jug with
 * measurement ticks, a teal lid and base, an amber power button, and a swirl of
 * teal "blend" inside. Filled cartoon style with thick ink outlines; colors come
 * from theme tokens (fill-primary / fill-card / stroke-foreground) so it stays on
 * palette and works down to 32px in the nav or as a favicon.
 */
export default function ApplicationLogo({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={cn(className)}
            strokeLinejoin="round"
            strokeLinecap="round"
            aria-hidden="true"
        >
            {/* Base plinth */}
            <rect
                x="8.5"
                y="23"
                width="15"
                height="4.6"
                rx="1.9"
                className="fill-primary stroke-foreground"
                strokeWidth="1.5"
            />
            {/* Power button on the base */}
            <circle
                cx="19.4"
                cy="25.3"
                r="1.15"
                className="fill-warning stroke-foreground"
                strokeWidth="1"
            />

            {/* Jug body */}
            <path
                d="M9 10q0-.6.6-.6h12.8q.6 0 .5.6l-1.6 12q-.1.8-.9.8h-8.8q-.8 0-.9-.8l-1.7-12Z"
                className="fill-card stroke-foreground"
                strokeWidth="1.5"
            />

            {/* Blended contents with a wavy surface */}
            <path
                d="M10.85 16.7q2.6-1.4 5.15-.2 2.55 1.2 5.15.2l-.75 5.7q-.1.7-.8.7h-7.4q-.7 0-.8-.7l-.75-5.7Z"
                className="fill-primary"
            />
            {/* Bubbles */}
            <circle cx="14" cy="20.1" r="0.95" className="fill-card" />
            <circle cx="17.6" cy="19.4" r="0.65" className="fill-card" />

            {/* Measurement ticks in the cream upper wall */}
            <path
                d="M11.7 12.6h2M11.85 14.5h1.7"
                className="stroke-foreground"
                strokeWidth="1"
            />

            {/* Lid */}
            <rect
                x="6.6"
                y="6"
                width="18.8"
                height="3.4"
                rx="1.7"
                className="fill-primary stroke-foreground"
                strokeWidth="1.5"
            />
            {/* Lid knob */}
            <rect
                x="13.7"
                y="3.7"
                width="4.6"
                height="2.8"
                rx="1.4"
                className="fill-primary stroke-foreground"
                strokeWidth="1.5"
            />
        </svg>
    );
}
