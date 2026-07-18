import { FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function ResetPassword({
    email,
    token,
}: {
    email: string;
    token: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <h1 className="font-display text-xl font-semibold">Choose a new password</h1>
            <p className="mt-1 mb-6 text-sm text-muted-foreground">
                Pick a long, random password to stay secure.
            </p>

            <form onSubmit={submit} className="space-y-5">
                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        autoComplete="username"
                        required
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        autoFocus
                        autoComplete="new-password"
                        required
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        required
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        Reset password
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
