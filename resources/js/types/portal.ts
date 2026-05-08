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
    customer_name: string;
    shipping_address: Record<string, string> | null;
    totals: { subtotal_cents?: number; currency?: string } | null;
    notes: string | null;
    items: OrderItem[];
    issues?: Issue[];
};

export type Issue = {
    id: number;
    order_id: number | null;
    order?: Order | null;
    type: 'ticket' | 'claim';
    status: string;
    request_type: string | null;
    reasons: string[] | null;
    description: string;
    contact: Record<string, string> | null;
    total_notes_count: number;
    unread_notes_count: number;
    last_activity_at: string | null;
    created_at: string;
};

export type RequiredAction = {
    id: number;
    order_id: number | null;
    order?: Order | null;
    status: string;
    type: string;
    title: string;
    description: string;
    payload: Record<string, string> | null;
    last_activity_at: string | null;
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
    productTypes: ProductType[];
    productMappings: ProductMapping[];
    issues: Issue[];
    requiredActions: RequiredAction[];
    notificationSubscriptions: NotificationSubscription[];
    users: User[];
    userInvites: UserInvite[];
};

export type AuthPayload = {
    tenant: Tenant | null;
    user: User | null;
    abilities: string[];
};

export type ImportPreview = {
    headers: string[];
    rows: Array<{
        row_number: number;
        status: string;
        payload: Record<string, string>;
        matched_mapping: ProductMapping | null;
    }>;
    errors: Record<string, string[]>;
    summary: {
        total: number;
        ready: number;
        needs_action: number;
    };
};
