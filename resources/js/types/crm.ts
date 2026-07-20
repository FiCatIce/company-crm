export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

export type SelectOption = { value: string; label: string };

export type InteractionRow = {
    id: number;
    type: string;
    type_label: string;
    direction: 'in' | 'out' | null;
    subject: string | null;
    body: string | null;
    outcome: string | null;
    outcome_label: string | null;
    duration_sec: number | null;
    occurred_at: string;
    source: string;
    user: { id: number; name: string } | null;
    can_edit: boolean;
    can_delete: boolean;
};

// A money-free purchased-product line (product + date + warranty). Shown to
// viewers with customer.view.products but no money permission — DESIGN_RBAC.md §4.3.
export type PurchasedProductRow = {
    id: number;
    product: string | null;
    purchased_at: string;
    warranty_months: number | null;
    warranty_expires_at: string;
    is_under_warranty: boolean;
};

// A full transaction row (money viewers): a purchased-product line plus the price.
export type CustomerTransactionRow = PurchasedProductRow & {
    amount: string | null;
};

export type CustomerDetail = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    status: string;
    status_label: string;
    source: string | null;
    source_label: string | null;
    reseller: { id: number; name: string } | null;
    owner: { id: number; name: string } | null;
    created_at: string | null;
};

export type CustomerStats = {
    interactionsCount: number;
    lastContactedAt: string | null;
    transactionsCount: number;
    // Omitted (absent) when the viewer may not see money.
    totalSpend?: number;
};

export type MyInteractionRow = {
    id: number;
    customer: { id: number; name: string } | null;
    type: string;
    type_label: string;
    direction: 'in' | 'out' | null;
    occurred_at: string;
    subject: string | null;
};

export type RecentCallRow = {
    id: number;
    customer: { id: number; name: string } | null;
    direction: 'in' | 'out' | null;
    outcome: string | null;
    outcome_label: string | null;
    duration_sec: number | null;
    occurred_at: string;
    source: string;
    user: { id: number; name: string } | null;
    is_cti_lead: boolean;
};

// Admin user management (RBAC B5).
export type RoleOption = { value: string; label: string };

export type PermissionItem = {
    name: string;
    label: string;
    sensitive: boolean;
};

export type PermissionGroup = {
    group: string;
    permissions: PermissionItem[];
};

export type UserRow = {
    id: number;
    name: string;
    email: string;
    extension: string | null;
    role: RoleOption | null;
    is_self: boolean;
    can_delete: boolean;
};

// Delegated team-member management (hierarchy H4). A manager's scoped view of the
// members they created / lead — type + join date, and whether they may reset it.
export type TeamMemberRow = {
    id: number;
    name: string;
    email: string;
    extension: string | null;
    type: RoleOption | null;
    created_at: string | null;
    can_reset: boolean;
};

// Support assignment (hierarchy H5, DH5). A sales user's CS/Maintenance agents —
// the pivot is read live by the customer scope, so this list IS who can see their book.
export type SupportAssigneeRow = {
    id: number;
    name: string;
    email: string;
    type: RoleOption | null;
    assigned_at: string | null;
};

// A same-team agent the sales user may still assign (not yet wired to them).
export type SupportCandidateRow = {
    id: number;
    name: string;
    email: string;
    type: RoleOption | null;
};

// "Tim Saya" overview (hierarchy H6) — read-only, shape depends on the viewer.
export type TeamAgentRow = {
    id: number;
    name: string;
    email: string;
    type: RoleOption | null;
};

// A rep as the manager sees them: book size + who supports them.
export type TeamRepRow = TeamAgentRow & {
    customers_count: number;
    assignees: TeamAgentRow[];
};

// Dashboard hierarchy band. The customer figure is NOT here — it lives in
// `me.myCustomers` (the same Customer::visibleTo count), relabelled per kind.
export type TeamSummary =
    | { kind: 'manager'; repCount: number; supportCount: number }
    | { kind: 'sales'; supportCount: number }
    | { kind: 'support'; repCount: number };

// Admin role builder. `is_system` roles are locked (preset defined in code).
export type RoleRow = {
    id: number;
    name: string;
    label: string;
    is_system: boolean;
    is_locked: boolean;
    users_count: number;
    permissions_count: number;
};

export type InteractionOption = { value: string; label: string };

export type InteractionOptions = {
    types: InteractionOption[];
    directions: InteractionOption[];
    outcomes: InteractionOption[];
};
