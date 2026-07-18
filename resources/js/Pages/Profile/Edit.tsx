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
                    <p className="font-mono text-[0.6875rem] tracking-console text-muted-foreground uppercase">
                        Settings
                    </p>
                    <h1 className="mt-1 font-display text-3xl font-semibold tracking-tight">
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
