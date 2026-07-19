import { Head } from '@inertiajs/react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent } from '@/Components/ui/card';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail?: boolean;
    status?: string;
}) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <span className="inline-block rounded-full border-2 border-ink bg-accent px-3 py-1 text-xs font-bold text-foreground shadow-hard-sm">
                        Settings
                    </span>
                    <h1 className="mt-3 font-display text-4xl font-extrabold tracking-tight">
                        Profile
                    </h1>
                </div>
            }
        >
            <Head title="Profile" />

            <div className="mx-auto max-w-2xl space-y-6">
                <Card>
                    <CardContent>
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <UpdatePasswordForm />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <DeleteUserForm />
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
