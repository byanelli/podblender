import * as React from 'react';

import { cn } from '@/lib/utils';

function Input({ className, type, ...props }: React.ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'flex h-10 w-full min-w-0 rounded-xl border-2 border-ink bg-card px-3.5 py-1 text-sm font-medium transition-[color,box-shadow] outline-none',
                'file:inline-flex file:border-0 file:bg-transparent file:text-sm file:font-medium',
                'placeholder:font-normal placeholder:text-muted-foreground/70 selection:bg-primary selection:text-primary-foreground',
                'focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/30',
                'disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
