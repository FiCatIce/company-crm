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

/** Derived capability flags no single permission expresses (UI gating only). */
export type AuthCapabilities = {
    /** May use the delegated team-members area — a manager, never the admin. */
    manageTeamMembers: boolean;
};

export type Auth = {
    user: User;
    /** Effective permission names for the current user (UI gating only). */
    permissions: string[];
    /** Derived capabilities — see AuthCapabilities. Server-enforced by policy. */
    can: AuthCapabilities;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
