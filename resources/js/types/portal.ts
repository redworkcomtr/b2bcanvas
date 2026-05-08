export type Tenant = {
    id: number;
    name: string;
    slug: string;
    support_email: string | null;
};

export type User = {
    id: number;
    tenant_id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'operations' | 'support' | 'viewer';
    active: boolean;
    invited_at: string | null;
    last_login_at: string | null;
};

export type UserInvite = {
    id: number;
    tenant_id: number;
    invited_by_id: number | null;
    email: string;
    role: User['role'];
    status: string;
    expires_at: string | null;
    accepted_at: string | null;
    created_at: string;
};

export type ProductType = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    image_url: string | null;
    variants: ProductVariant[];
    options: ProductOption[];
};

export type ProductVariant = {
    id: number;
    product_type_id: number;
    product_type?: ProductType;
    name: string;
    sku: string;
    layout: string | null;
    panel_count: number;
    price_cents: number;
    image_sizes: string[] | null;
    panel_sizes: string[] | null;
    template_url: string | null;
};

export type ProductOption = {
    id: number;
    product_type_id: number;
    group: string;
    name: string;
    code: string;
    price_cents: number;
};

export type MediaFile = {
    id: number;
    tenant_id: number;
    user_id: number | null;
    collection: string;
    disk: string;
    path: string;
    url: string;
    original_name: string;
    mime_type: string;
    size: number;
    checksum: string;
    scan_state: string;
    metadata: Record<string, string> | null;
};

export type SavedView = {
    id: number;
    tenant_id: number;
    user_id: number | null;
    scope: 'orders' | string;
    name: string;
    filters: Record<string, string> | null;
    sort: Record<string, string> | null;
    is_default: boolean;
    created_at: string;
};

export type MappingRule = {
    id?: number;
    field: 'sku' | 'name' | 'fulfillment_sku';
    operator: 'equals' | 'contains' | 'starts_with' | 'regex';
    value: string;
    priority?: number;
};

export type ProductMapping = {
    id: number;
    name: string;
    properties: Record<string, string> | null;
    variant: ProductVariant;
    rules: MappingRule[];
};

export type MappingMutationResult = {
    mapping: ProductMapping;
    resolved_actions: number;
    conflicts: unknown[];
};

export type MappingSimulationCandidate = {
    mapping: ProductMapping;
    matched: boolean;
    score: number;
    max_priority: number;
    rule_count: number;
};

export type MappingSimulation = {
    matched_mapping: ProductMapping | null;
    candidates: MappingSimulationCandidate[];
    conflicts: MappingSimulationCandidate[];
};

export type OrderItem = {
    id: number;
    product_variant_id: number | null;
    item_name: string;
    item_sku: string | null;
    quantity: number;
    product_code: string | null;
    product_type: string | null;
    panel_summary: string | null;
    design_images: string[] | null;
    print_images: string[] | null;
    options: Record<string, string> | null;
    variant?: ProductVariant | null;
};

export type Order = {
    id: number;
    uuid: string;
    order_number: string;
    status: string;
    order_date: string | null;
    submitted_at: string | null;
    shipped_at: string | null;
    shipping_service: string | null;
    tracking_number: string | null;
    tracking_url: string | null;
    customer_name: string;
    shipping_address: Record<string, string> | null;
    totals: { subtotal_cents?: number; currency?: string } | null;
    notes: string | null;
    items: OrderItem[];
    issues?: Issue[];
    required_actions?: RequiredAction[];
    requiredActions?: RequiredAction[];
    status_events?: OrderStatusEvent[];
    statusEvents?: OrderStatusEvent[];
    media_files?: MediaFile[];
    mediaFiles?: MediaFile[];
    audit_logs?: AuditLog[];
    auditLogs?: AuditLog[];
};

export type Issue = {
    id: number;
    order_id: number | null;
    assigned_to_id: number | null;
    order?: Order | null;
    assigned_to?: User | null;
    assignedTo?: User | null;
    type: 'ticket' | 'claim';
    status: string;
    priority: string;
    request_type: string | null;
    reasons: string[] | null;
    description: string;
    contact: Record<string, string> | null;
    total_notes_count: number;
    unread_notes_count: number;
    last_activity_at: string | null;
    last_read_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    created_at: string;
    comments?: IssueComment[];
};

export type IssueComment = {
    id: number;
    issue_id: number;
    user_id: number | null;
    body: string;
    attachments: unknown[] | null;
    internal: boolean;
    created_at: string;
    user?: User | null;
};

export type OrderStatusEvent = {
    id: number;
    order_id: number;
    user_id: number | null;
    from_status: string | null;
    to_status: string;
    note: string | null;
    metadata: Record<string, string | null> | null;
    created_at: string;
    user?: User | null;
};

export type AuditLog = {
    id: number;
    tenant_id: number | null;
    user_id: number | null;
    event: string;
    auditable_type: string | null;
    auditable_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    user?: User | null;
};

export type RequiredAction = {
    id: number;
    order_id: number | null;
    assigned_to_id: number | null;
    order?: Order | null;
    assigned_to?: User | null;
    assignedTo?: User | null;
    status: string;
    priority: string;
    type: string;
    title: string;
    description: string;
    payload: Record<string, unknown> | null;
    resolution_payload: Record<string, unknown> | null;
    last_activity_at: string | null;
    resolved_at: string | null;
    escalated_at: string | null;
    comments?: RequiredActionComment[];
};

export type RequiredActionComment = {
    id: number;
    required_action_id: number;
    user_id: number | null;
    body: string;
    attachments: unknown[] | null;
    internal: boolean;
    created_at: string;
    user?: User | null;
};

export type NotificationSubscription = {
    id: number;
    user_id: number;
    event: string;
    email: string;
    is_subscribed: boolean;
};

export type PortalPayload = {
    tenant: Tenant | null;
    user: User | null;
    abilities: string[];
    metrics: Record<string, number>;
    orders: Order[];
    savedViews: SavedView[];
    productTypes: ProductType[];
    productMappings: ProductMapping[];
    issues: Issue[];
    requiredActions: RequiredAction[];
    notificationSubscriptions: NotificationSubscription[];
    users: User[];
    userInvites: UserInvite[];
};

export type OrdersResponse = {
    data: Order[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
    links: {
        next: string | null;
        prev: string | null;
    };
    summary: Record<string, number>;
};

export type AuthPayload = {
    tenant: Tenant | null;
    user: User | null;
    abilities: string[];
};

export type ImportPreview = {
    import_id: number;
    headers: string[];
    rows: Array<{
        row_number: number;
        status: string;
        payload: Record<string, string>;
        matched_mapping: ProductMapping | null;
        errors?: string[];
    }>;
    errors: Record<string, string[]>;
    summary: {
        total: number;
        ready: number;
        needs_action: number;
    };
};

export type ImportBatch = {
    id: number;
    tenant_id: number;
    filename: string;
    status: string;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    summary: Record<string, unknown> | null;
    rows_count?: number;
    created_at: string;
};
