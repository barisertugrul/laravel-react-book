/** Bütün tutarlar kuruş cinsinden tam sayı (Bölüm 5) */
export type Cents = number;

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export type Product = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    price: Cents;
    stock: number;
    is_active?: boolean;
};

export type CartLine = {
    product: Product;
    quantity: number;
    total: Cents;
    in_stock: boolean;
};

export type Totals = { subtotal: Cents; shipping: Cents; total: Cents };

export type OrderStatus = 'pending' | 'paid' | 'shipped' | 'cancelled';

export type OrderItem = {
    id: number;
    product_id: number | null;
    product_name: string;
    unit_price: Cents;
    quantity: number;
    total: Cents;
};

export type Order = {
    id: number;
    number: string;
    status: OrderStatus;
    subtotal: Cents;
    shipping: Cents;
    total: Cents;
    shipping_name: string;
    shipping_phone: string;
    shipping_address: string;
    shipping_city: string;
    payment_provider: string;
    payment_reference: string | null;
    paid_at: string | null;
    shipped_at: string | null;
    tracking_number: string | null;
    created_at: string;
    items?: OrderItem[];
    items_count?: number;
    user?: { id: number; name: string; email?: string };
};
