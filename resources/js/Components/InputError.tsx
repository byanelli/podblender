import { cn } from '@/lib/utils';

export default function InputError({
    message,
    className,
    ...props
}: { message?: string } & React.HTMLAttributes<HTMLParagraphElement>) {
    return message ? (
        <p
            {...props}
            className={cn('text-destructive text-sm', className)}
        >
            {message}
        </p>
    ) : null;
}
