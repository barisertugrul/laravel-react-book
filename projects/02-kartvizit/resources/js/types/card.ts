export type ThemeName = 'klasik' | 'gece' | 'okyanus';

export type CardLink = {
    id?: number;
    label: string;
    url: string;
};

export type Card = {
    id: number;
    slug: string;
    full_name: string;
    title: string | null;
    company: string | null;
    bio: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    address: string | null;
    avatar_url: string | null;
    public_url: string;
    theme: ThemeName;
    is_published: boolean;
    view_count: number;
    links?: CardLink[];
};

export type PlanInfo = {
    value: 'free' | 'pro';
    label: string;
    card_limit: number;
    themes: ThemeName[];
};
