export type Category = {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
    products_count?: number;
    products?: Product[];
};

export type Product = {
    id: number;
    category_id: number;
    name: string;
    description: string | null;
    price: string;
    image_path: string | null;
    image_url: string | null;
    is_available: boolean;
    sort_order: number;
    category?: Pick<Category, 'id' | 'name'>;
};

export type CategoryOption = Pick<Category, 'id' | 'name'>;
