import { cn } from '@/lib/utils';

/**
 * tube2pod brand mark: a play triangle broadcasting outward — YouTube's play
 * button re-transmitted as a podcast signal. Uses currentColor so it inherits
 * the amber "signal" hue from its container.
 */
export default function ApplicationLogo({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={cn('text-primary', className)}
            aria-hidden="true"
        >
            <rect
                x="0.75"
                y="0.75"
                width="30.5"
                height="30.5"
                rx="8"
                className="fill-primary/10 stroke-primary/40"
                strokeWidth="1.5"
            />
            <path
                d="M13 11.4c0-.86.94-1.38 1.66-.92l6.1 3.9c.66.42.66 1.4 0 1.83l-6.1 3.9c-.72.46-1.66-.06-1.66-.92V11.4Z"
                className="fill-primary"
            />
            <path
                d="M8.6 12.4a6.5 6.5 0 0 0 0 7.2"
                className="stroke-primary"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
            <path
                d="M5.4 10a10.5 10.5 0 0 0 0 12"
                className="stroke-primary/50"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
        </svg>
    );
}
