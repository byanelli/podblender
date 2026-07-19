import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-bold transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 cursor-pointer",
    {
        variants: {
            variant: {
                default:
                    'border-2 border-ink bg-primary text-primary-foreground shadow-hard-sm hover:translate-x-px hover:translate-y-px hover:shadow-hard-xs active:translate-x-0.5 active:translate-y-0.5 active:shadow-none',
                destructive:
                    'border-2 border-ink bg-destructive text-destructive-foreground shadow-hard-sm hover:translate-x-px hover:translate-y-px hover:shadow-hard-xs active:translate-x-0.5 active:translate-y-0.5 active:shadow-none focus-visible:ring-destructive/40',
                outline:
                    'border-2 border-ink bg-card text-foreground shadow-hard-sm hover:translate-x-px hover:translate-y-px hover:shadow-hard-xs hover:bg-accent active:translate-x-0.5 active:translate-y-0.5 active:shadow-none',
                secondary:
                    'border-2 border-ink bg-secondary text-secondary-foreground shadow-hard-sm hover:translate-x-px hover:translate-y-px hover:shadow-hard-xs active:translate-x-0.5 active:translate-y-0.5 active:shadow-none',
                flat: 'border-2 border-ink bg-card text-foreground transition-colors hover:bg-accent',
                ghost: 'rounded-full hover:bg-accent hover:text-accent-foreground',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-9 px-4 py-2 has-[>svg]:px-3',
                sm: 'h-8 gap-1.5 px-3.5 has-[>svg]:px-2.5',
                lg: 'h-11 px-6 has-[>svg]:px-4',
                icon: 'size-9',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    }) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="button"
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    );
}

export { Button, buttonVariants };
