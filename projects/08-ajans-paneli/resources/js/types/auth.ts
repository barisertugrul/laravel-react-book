export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    agency: CurrentAgency | null; // Bölüm 8: etkin ajans ve bu ajanstaki rolüm
    agencies: AgencyRef[]; // ajans değiştirici
};

export type AgencyRole = 'owner' | 'member';

export type AgencyRef = { id: number; name: string };

export type CurrentAgency = AgencyRef & { role: AgencyRole };

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
