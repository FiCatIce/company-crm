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

export type InteractionOption = { value: string; label: string };

export type InteractionOptions = {
    types: InteractionOption[];
    directions: InteractionOption[];
    outcomes: InteractionOption[];
};
