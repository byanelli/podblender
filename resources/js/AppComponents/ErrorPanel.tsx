import { TriangleAlert } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';

export default function ErrorPanel({
    message,
    operation,
}: {
    message: string;
    operation: string;
}) {
    return (
        <Alert variant="destructive">
            <TriangleAlert />
            <AlertTitle>There was an error {operation}</AlertTitle>
            <AlertDescription>{message}</AlertDescription>
        </Alert>
    );
}
