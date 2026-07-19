import { PropsWithChildren } from 'react';
import { Link } from '@inertiajs/react';

import ApplicationLogo from '@/Components/ApplicationLogo';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <div className="w-full max-w-md">
                <div className="mb-8 flex flex-col items-center gap-3 text-center">
                    <Link href="/" className="flex items-center gap-3">
                        <ApplicationLogo className="size-[66px]" />
                        <span className="font-display text-[45px] leading-tight font-extrabold tracking-tight">
                            Pod<span className="text-primary">blender</span>
                        </span>
                    </Link>
                    <p className="text-sm font-semibold text-muted-foreground">
                        Blend the web into your own podcast feed
                    </p>
                </div>

                <div className="rounded-2xl border-2 border-ink bg-card p-7 shadow-hard-lg">
                    {children}
                </div>
            </div>
        </div>
    );
}
