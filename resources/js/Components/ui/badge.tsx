import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center justify-center gap-1.5 rounded-md border px-2 py-0.5 font-mono text-[0.6875rem] font-medium tracking-wide uppercase w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 [&>svg]:pointer-events-none transition-colors',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-primary/15 text-primary',
                secondary:
                    'border-border/60 bg-secondary/60 text-muted-foreground',
                success:
                    'border-transparent bg-success/15 text-success',
                warning:
                    'border-transparent bg-warning/15 text-warning',
                destructive:
                    'border-transparent bg-destructive/15 text-destructive',
                outline: 'border-border text-foreground',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Badge({
    className,
    variant,
    asChild = false,
    ...props
}: React.ComponentProps<'span'> &
    VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'span';

    return (
        <Comp
            data-slot="badge"
            className={cn(badgeVariants({ variant }), className)}
            {...props}
        />
    );
}

export { Badge, badgeVariants };
