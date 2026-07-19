import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/ui/button';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <h1 className="font-display text-2xl font-bold">Verify your email</h1>
            <p className="mt-1 mb-4 text-sm text-muted-foreground">
                Thanks for signing up! Click the link we just emailed you to verify
                your address. Didn't get it? We'll gladly send another.
            </p>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-success">
                    A new verification link has been sent to your email address.
                </div>
            )}

            <form onSubmit={submit} className="flex items-center justify-between gap-4">
                <Button type="submit" disabled={processing}>
                    Resend verification email
                </Button>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Log out
                </Link>
            </form>
        </GuestLayout>
    );
}
