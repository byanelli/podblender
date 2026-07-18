import { FormEventHandler } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';

import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function UpdateProfileInformationForm({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail?: boolean;
    status?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section>
            <header>
                <h2 className="font-display text-lg font-medium">Profile information</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Update your account's name and email address.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <div className="space-y-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={data.name}
                        autoFocus
                        autoComplete="name"
                        required
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    <InputError message={errors.name} />
                </div>

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

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Your email address is unverified.{' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="text-foreground underline underline-offset-4 hover:text-primary"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-success">
                                A new verification link has been sent to your email
                                address.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                    {recentlySuccessful && (
                        <p className="text-sm text-muted-foreground">Saved.</p>
                    )}
                </div>
            </form>
        </section>
    );
}
