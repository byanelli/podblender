import { FormEventHandler, useRef } from 'react';
import { useForm } from '@inertiajs/react';

import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } =
        useForm({
            current_password: '',
            password: '',
            password_confirmation: '',
        });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errs) => {
                if (errs.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errs.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section>
            <header>
                <h2 className="font-display text-lg font-medium">Update password</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Use a long, random password to stay secure.
                </p>
            </header>

            <form onSubmit={updatePassword} className="mt-6 space-y-5">
                <div className="space-y-2">
                    <Label htmlFor="current_password">Current password</Label>
                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        type="password"
                        value={data.current_password}
                        autoComplete="current-password"
                        onChange={(e) => setData('current_password', e.target.value)}
                    />
                    <InputError message={errors.current_password} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password">New password</Label>
                    <Input
                        id="password"
                        ref={passwordInput}
                        type="password"
                        value={data.password}
                        autoComplete="new-password"
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
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

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
