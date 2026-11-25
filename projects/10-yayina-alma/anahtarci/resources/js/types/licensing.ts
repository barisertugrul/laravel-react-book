/** Bütün tutarlar kuruş cinsinden tam sayı (Bölüm 5) */
export type Cents = number;

export type Plan = {
    id: number;
    product_id: number;
    name: string;
    price: Cents;
    max_sites: number | null; // null: sınırsız
    badge?: string | null; // vitrinde vurgu etiketi; null: vurgu yok
    sort?: number;
    orders_count?: number; // yönetim paneli: sipariş almış plan silinemez
    licenses_count?: number;
};

export type Product = {
    id: number;
    name: string;
    slug: string;
    summary: string;
    description: string | null;
    is_active?: boolean;
    plans?: Plan[];
    plans_count?: number;
    licenses_count?: number; // lisans satıldıysa slug kilitlenir
};

export type OrderStatus = 'pending' | 'paid' | 'cancelled';

export type LicenseStatus = 'active' | 'revoked';

export type License = {
    id: number;
    user_id: number;
    plan_id: number;
    status: LicenseStatus;
    max_sites: number | null;
    updates_until: string;
    revoked_at: string | null;
    revoke_reason: string | null;
    created_at: string;
    key?: string; // yalnızca sahibine, detay sayfasında gelir
    has_updates?: boolean;
    activations_count?: number;
    plan: Plan & { product: Pick<Product, 'id' | 'name' | 'slug'> };
    user?: { id: number; name: string; email: string };
    order?: { id: number; number: string; total: Cents; paid_at: string | null; payment_reference?: string | null } | null;
};

export type Activation = {
    id: number;
    domain: string;
    ip?: string | null;
    last_seen_at: string | null;
    created_at: string;
};

export type LicenseEventType = 'issued' | 'activated' | 'reactivated' | 'deactivated' | 'removed' | 'rejected' | 'revoked' | 'extended';

export type LicenseEvent = {
    id: number;
    type: LicenseEventType;
    domain: string | null;
    ip: string | null;
    meta: Record<string, string> | null;
    created_at: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};
