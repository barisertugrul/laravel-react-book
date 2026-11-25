import type { Auth } from '@/types/auth';
import type { BillingSummary } from '@/types/kumanda';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            billing: BillingSummary | null; // Bölüm 9: HandleInertiaRequests::billing()
            [key: string]: unknown;
        };
    }
}
