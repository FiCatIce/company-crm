export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

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

export type CustomerTransactionRow = {
    id: number;
    product: string | null;
    purchased_at: string;
    warranty_months: number | null;
    warranty_expires_at: string;
    is_under_warranty: boolean;
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
    totalSpend: number;
};
