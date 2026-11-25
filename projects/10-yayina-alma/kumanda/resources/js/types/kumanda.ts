import type { AgencyRole } from './auth';

export type HealthStatus = 'unknown' | 'ok' | 'warning' | 'critical';

export type Issue = { level: 'warning' | 'critical'; code: string; message: string };

/** Panodaki satır: son kontrolün özeti sitenin kendisinde durur */
export type SiteSummary = {
    id: number;
    name: string;
    url: string;
    health_status: HealthStatus;
    last_checked_at: string | null;
    wp_version: string | null;
    php_version: string | null;
    pending_updates: number;
    response_ms: number | null;
    ssl_expires_at: string | null;
};

export type SiteDetail = SiteSummary & {
    status_changed_at: string | null;
    agent_version: string | null;
    created_at: string;
};

export type SiteCheck = {
    id: number;
    status: HealthStatus;
    http_status: number | null;
    response_ms: number | null;
    ssl_expires_at: string | null;
    issues: Issue[];
    error: string | null;
    checked_at: string;
};

export type ReportPlugin = { file: string; name: string; version: string; active: boolean; update: string | null };
export type ReportTheme = { slug: string; name: string; version: string; active: boolean; update: string | null };

/** Ajans Asistanı'nın /health yanıtı */
export type AgentReport = {
    site_name: string;
    wp_version: string;
    php_version: string;
    agent_version: string;
    core_update: string | null;
    plugins: ReportPlugin[];
    themes: ReportTheme[];
    debug: boolean;
    debug_display: boolean;
    file_edit_disabled: boolean;
    admin_count: number;
    license?: AgentLicense; // Bölüm 9 (eklenti 1.2.0+)
};

/** Ajans_Asistani_License::status(): anahtarın ve token'ın kendisi gelmez */
export type AgentLicense =
    | { state: 'none' }
    | {
          state: 'active' | 'invalid';
          plan: string | null;
          domain: string;
          max_sites: number | null;
          sites_used: number | null;
          updates_until: string | null;
          updates_available: boolean;
          key_hint: string;
          checked_at: string;
          error: string | null;
      };

export type Member = { id: number; name: string; email: string; role: AgencyRole; joined_at: string };

export type Invitation = { id: number; email: string; role: AgencyRole; expires_at: string; inviter: string | null };

export type RoleOption = { value: AgencyRole; label: string };

// Bölüm 9: toplu işlemler ve paket kütüphanesi

export type OperationType = 'install' | 'update' | 'license';

export type TargetStatus = 'pending' | 'running' | 'succeeded' | 'failed' | 'cancelled';

/** İlerleme ekranındaki satır (OperationTarget::toRow) */
export type OperationTarget = {
    id: number;
    site_id: number;
    site_name: string;
    site_url: string;
    status: TargetStatus;
    message: string | null;
    started_at: string | null;
    finished_at: string | null;
};

export type OperationSummary = Record<TargetStatus, number> & { total: number };

export type OperationDetail = {
    id: number;
    label: string;
    type: OperationType;
    user: string | null;
    created_at: string;
    finished_at: string | null;
};

export type OperationListItem = Omit<OperationDetail, 'user'> & { user: string | null; total: number; finished: number; failed: number };

/** Kanal olayı: App\Events\OperationProgressed::broadcastWith() */
export type OperationProgressedEvent = {
    operation_id: number;
    finished_at: string | null;
    summary: OperationSummary;
    target: OperationTarget | null;
};

export type PackageItem = { id: number; slug: string; name: string; version: string; sha256?: string; size?: number; created_at?: string };

export type OperationSite = {
    id: number;
    name: string;
    url: string;
    health_status: HealthStatus;
    plugins: { file: string; name: string; version: string; update: string | null }[];
};

// Bölüm 9: abonelik

export type Plan = { key: string; name: string; price: number; max_sites: number };

export type SubscriptionStatus = 'active' | 'past_due' | 'ended';

export type SubscriptionInfo = {
    plan: string;
    status: SubscriptionStatus;
    status_label: string;
    card_last4: string | null;
    current_period_ends_at: string;
    grace_ends_at: string | null;
    canceled_at: string | null;
};

export type SubscriptionPayment = {
    id: number;
    plan: string;
    amount: number;
    status: 'paid' | 'failed';
    reference: string | null;
    failure_message: string | null;
    created_at: string;
};

/** Her sayfada paylaşılan özet (HandleInertiaRequests::billing) */
export type BillingSummary = { plan: string; sites: number; max_sites: number; past_due: boolean; grace_ends_at: string | null };
