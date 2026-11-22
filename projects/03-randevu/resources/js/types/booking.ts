export type Service = {
    id: number;
    name: string;
    duration: number;
    price: string;
    is_active: boolean;
    appointments_count?: number;
};

export type AppointmentStatus = 'confirmed' | 'completed' | 'cancelled';

export type Appointment = {
    id: number;
    service_id: number;
    customer_name: string;
    customer_email: string;
    customer_phone: string;
    note: string | null;
    starts_at: string; // ISO 8601, ör. "2026-11-23T07:00:00.000000Z"
    ends_at: string;
    status: AppointmentStatus;
    service?: Pick<Service, 'id' | 'name' | 'duration'>;
};

export type BookingDay = { date: string; open: boolean };
