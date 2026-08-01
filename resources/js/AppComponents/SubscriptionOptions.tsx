import { CalendarClock } from 'lucide-react';

import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';

/**
 * How far back a new subscription reaches. "Everything" is sent as the epoch,
 * which is what the backend reads as "the whole back catalogue".
 */
export type BackfillChoice = 'default' | 'everything' | 'since';

export const EPOCH = '1970-01-01T00:00:00+00:00';

/**
 * The value to send for a given choice: null leaves the backend to apply its
 * configured default window.
 */
export function backfillSinceFor(
    choice: BackfillChoice,
    since: string,
): string | null {
    if (choice === 'everything') return EPOCH;
    if (choice === 'since' && since !== '') return new Date(since).toISOString();

    return null;
}

/**
 * How many of a source's clips a choice would pull in, as far as we can tell.
 * Only "everything" has a knowable answer — for the others it depends on dates
 * we'd have to ask the platform about — and even that is unknown when the
 * platform didn't report a count.
 */
export function episodesImplied(
    choice: BackfillChoice,
    clipCount: number | null,
): number | null {
    return choice === 'everything' ? clipCount : null;
}

export default function SubscriptionOptions({
    backfill,
    onBackfillChange,
    since,
    onSinceChange,
    tracksNewEpisodes,
    onTracksNewEpisodesChange,
}: {
    backfill: BackfillChoice;
    onBackfillChange: (choice: BackfillChoice) => void;
    since: string;
    onSinceChange: (since: string) => void;
    tracksNewEpisodes: boolean;
    onTracksNewEpisodesChange: (tracks: boolean) => void;
}) {
    const choices: { value: BackfillChoice; label: string; hint: string }[] = [
        { value: 'default', label: 'Recent', hint: 'The last month' },
        { value: 'everything', label: 'Everything', hint: 'The whole history' },
        { value: 'since', label: 'Since…', hint: 'Pick a date' },
    ];

    return (
        <div className="space-y-5">
            <div className="space-y-2">
                <Label>How much history?</Label>

                <div className="grid grid-cols-3 gap-2">
                    {choices.map((choice) => {
                        const active = backfill === choice.value;

                        return (
                            <button
                                key={choice.value}
                                type="button"
                                onClick={() => onBackfillChange(choice.value)}
                                className={cn(
                                    'flex cursor-pointer flex-col items-start gap-1 rounded-xl border-2 border-ink p-3 text-left transition-colors',
                                    active ? 'bg-accent' : 'bg-card hover:bg-accent',
                                )}
                            >
                                <span
                                    className={cn(
                                        'text-sm font-bold',
                                        active
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {choice.label}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {choice.hint}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {backfill === 'since' && (
                <div className="space-y-2">
                    <Label htmlFor="backfill-since">Earliest episode date</Label>
                    <div className="relative">
                        <CalendarClock className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="backfill-since"
                            type="date"
                            required
                            value={since}
                            className="pl-9"
                            max={new Date().toISOString().slice(0, 10)}
                            onChange={(e) => onSinceChange(e.target.value)}
                        />
                    </div>
                </div>
            )}

            <label className="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-ink bg-card p-3 transition-colors hover:bg-accent">
                <input
                    type="checkbox"
                    checked={tracksNewEpisodes}
                    onChange={(e) => onTracksNewEpisodesChange(e.target.checked)}
                    className="mt-0.5 size-4 accent-primary"
                />
                <span className="space-y-1">
                    <span className="block text-sm font-bold">
                        Keep collecting new episodes
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        Uncheck to take what's there now and leave it at that.
                    </span>
                </span>
            </label>
        </div>
    );
}
