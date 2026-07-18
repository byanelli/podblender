import { PropsWithChildren } from 'react';
import { Link } from '@inertiajs/react';

import ApplicationLogo from '@/Components/ApplicationLogo';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <div className="w-full max-w-md">
                <div className="mb-8 flex flex-col items-center gap-3 text-center">
                    <Link href="/" className="flex items-center gap-3">
                        <ApplicationLogo className="size-11" />
                        <span className="font-display text-2xl font-semibold tracking-tight">
                            tube<span className="text-primary">2</span>pod
                        </span>
                    </Link>
                    <p className="font-mono text-[0.6875rem] tracking-console text-muted-foreground uppercase">
                        Your private broadcast booth
                    </p>
                </div>

                <div className="rounded-xl border border-border/70 bg-card/80 p-7 shadow-2xl shadow-black/30 backdrop-blur-sm">
                    {children}
                </div>
            </div>
        </div>
    );
}
