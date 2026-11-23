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
};

export type Member = { id: number; name: string; email: string; role: AgencyRole; joined_at: string };

export type Invitation = { id: number; email: string; role: AgencyRole; expires_at: string; inviter: string | null };

export type RoleOption = { value: AgencyRole; label: string };
