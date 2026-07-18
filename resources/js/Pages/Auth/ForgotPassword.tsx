import { FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <h1 className="font-display text-xl font-semibold">Reset password</h1>
            <p className="mt-1 mb-6 text-sm text-muted-foreground">
                Tell us your email and we'll send a reset link.
            </p>

            {status && (
                <div className="mb-4 text-sm font-medium text-success">{status}</div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        autoFocus
                        autoComplete="username"
                        required
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        Email reset link
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
