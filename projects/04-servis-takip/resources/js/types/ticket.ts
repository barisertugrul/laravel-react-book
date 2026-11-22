export type TicketStatus = 'received' | 'diagnosing' | 'waiting_approval' | 'repairing' | 'ready' | 'delivered' | 'cancelled';

export type Option<T = string> = { value: T; label: string };

/** Kayıt ayrıntısında sunucunun gönderdiği izinli geçişler */
export type Transition = Option<TicketStatus> & { requires_note: boolean; confirm: boolean };

export type Customer = { id: number; name: string; phone: string; email: string | null };

export type TicketEvent = {
    id: number;
    from_status: TicketStatus | null;
    to_status: TicketStatus;
    note: string | null;
    is_public: boolean;
    reverts_event_id: number | null;
    created_at: string;
    user?: { id: number; name: string } | null;
};

export type Ticket = {
    id: number;
    code: string;
    tracking_code: string;
    tracking_url: string;
    device_type: string;
    brand: string;
    model: string | null;
    serial_number: string | null;
    problem: string;
    accessories: string | null;
    estimated_cost: string | null;
    final_cost: string | null;
    status: TicketStatus;
    assigned_to: number | null;
    created_at: string;
    customer?: Customer;
    technician?: { id: number; name: string } | null;
    events?: TicketEvent[];
};

/** Laravel'in paginate() çıktısının React tarafındaki karşılığı */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};
