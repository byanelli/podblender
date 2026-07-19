import { PropsWithChildren, ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, User as UserIcon } from 'lucide-react';

import ApplicationLogo from '@/Components/ApplicationLogo';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export default function AuthenticatedLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    const onDashboard = route().current('dashboard');

    return (
        <div className="min-h-screen">
            <nav className="sticky top-0 z-40 border-b-2 border-ink bg-card">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-8">
                        <Link
                            href={route('dashboard')}
                            className="flex items-center gap-2.5"
                        >
                            <ApplicationLogo className="size-8" />
                            <span className="font-display text-xl font-extrabold tracking-tight">
                                Pod<span className="text-primary">blender</span>
                            </span>
                        </Link>

                        <div className="hidden items-center gap-1 sm:flex">
                            <Link
                                href={route('dashboard')}
                                className={cn(
                                    'flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-bold transition-colors',
                                    onDashboard
                                        ? 'border-2 border-ink bg-accent text-foreground shadow-hard-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'size-1.5 rounded-full',
                                        onDashboard
                                            ? 'bg-primary animate-signal'
                                            : 'bg-muted-foreground/40',
                                    )}
                                />
                                Feeds
                            </Link>
                        </div>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="gap-2 text-muted-foreground hover:text-foreground"
                            >
                                <span className="grid size-6 place-items-center rounded-full border-2 border-ink bg-primary text-xs font-bold text-primary-foreground">
                                    {user.name.charAt(0).toUpperCase()}
                                </span>
                                <span className="hidden max-w-[10rem] truncate sm:inline">
                                    {user.name}
                                </span>
                                <ChevronDown className="size-4 opacity-60" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel className="flex flex-col gap-0.5">
                                <span className="truncate font-medium">{user.name}</span>
                                <span className="truncate text-xs font-normal text-muted-foreground">
                                    {user.email}
                                </span>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href={route('profile.edit')}>
                                    <UserIcon />
                                    Profile
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild variant="destructive">
                                <Link href={route('logout')} method="post" as="button">
                                    <LogOut />
                                    Log out
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </nav>

            {header && (
                <header>
                    <div className="mx-auto max-w-6xl px-4 pt-8 pb-2 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                {children}
            </main>
        </div>
    );
}
