import { apiRequest } from "../../../lib/api";

export type OrderStatus = "open" | "completed" | "cancelled" | "archived";
export type PaymentStatus =
    | "pending"
    | "authorized"
    | "paid"
    | "partially_paid"
    | "refunded"
    | "partially_refunded"
    | "failed"
    | "voided";
export type FulfillmentStatus =
    "unfulfilled" | "partial" | "fulfilled" | "returned";
export type OperationalFulfillmentStatus =
    | "unfulfilled"
    | "processing"
    | "picking"
    | "packing"
    | "ready_to_ship"
    | "shipped"
    | "in_transit"
    | "out_for_delivery"
    | "delivered"
    | "cancelled"
    | "failed"
    | "return_to_sender"
    | "returned";
export type ShipmentStatus =
    | "pending"
    | "created"
    | "label_created"
    | "picked_up"
    | "in_transit"
    | "out_for_delivery"
    | "delivered"
    | "failed"
    | "cancelled"
    | "returned";

export interface AdminOrderSummary {
    id: string;
    number: string;
    status: OrderStatus;
    payment_status: PaymentStatus;
    fulfillment_status: FulfillmentStatus;
    customer: { id?: string; name: string | null; email: string | null };
    currency: string;
    grand_total: string;
    placed_at: string | null;
}

export interface AdminOrderDetail extends AdminOrderSummary {
    customer_phone: string | null;
    subtotal: string;
    discount_total: string;
    tax_total: string;
    shipping_total: string;
    notes: string | null;
    items: Array<{
        id: string;
        product_title: string;
        variant_title: string | null;
        sku: string | null;
        quantity: number;
        unit_price: string;
        discount_total: string;
        tax_total: string;
        line_total: string;
    }>;
    addresses: Array<{
        type: "shipping" | "billing";
        first_name: string;
        last_name: string;
        company: string | null;
        phone: string | null;
        country_code: string;
        province: string | null;
        district: string | null;
        address_line_1: string;
        address_line_2: string | null;
        postal_code: string | null;
    }>;
    tax_lines: Array<{ name: string; rate: string; amount: string }>;
    payments: Array<{
        id: string;
        provider: string;
        status: PaymentStatus;
        amount: string;
        currency: string;
        payment_method_type: string | null;
        paid_at: string | null;
        refunded_amount: string;
        refundable_amount: string;
    }>;
    fulfillments: FulfillmentRecord[];
    shipments: ShipmentRecord[];
    returns: ReturnRecord[];
    refunds: RefundRecord[];
    timeline: Array<{
        id: string;
        type: string;
        message: string;
        created_at: string | null;
    }>;
}

export interface FulfillmentRecord {
    id: string;
    status: OperationalFulfillmentStatus;
    order?: { id: string; number: string; customer: string | null };
    location: { id: string; name: string; code: string | null } | null;
    package: Record<string, string | null> | null;
    started_at: string | null;
    picked_at?: string | null;
    packed_at: string | null;
    fulfilled_at: string | null;
    items: Array<{
        id: string;
        order_item_id: string;
        title: string;
        variant_title: string | null;
        sku: string | null;
        barcode: string | null;
        quantity: number;
        picked_quantity: number;
        status: string;
    }>;
    shipments: ShipmentRecord[];
}

export interface ShipmentRecord {
    id: string;
    fulfillment_id?: string | null;
    provider: string;
    tracking_number: string | null;
    tracking_url: string | null;
    status: ShipmentStatus;
    service_code: string | null;
    package_weight: string | null;
    package_dimensions: Record<string, string | null> | null;
    shipped_at: string | null;
    delivered_at: string | null;
    events: Array<{ id: string; status: ShipmentStatus; location: string | null; message: string; occurred_at: string | null }>;
}

export interface ReturnRecord {
    id: string;
    number: string;
    status: string;
    reason: string | null;
    customer_note: string | null;
    internal_note?: string | null;
    return_tracking_number: string | null;
    requested_at: string | null;
    approved_at: string | null;
    received_at: string | null;
    completed_at: string | null;
    order: { id: string; number: string; currency: string; grand_total: string };
    items: Array<{ id: string; order_item_id: string; title: string; variant_title?: string | null; quantity: number; reason_code: string; condition: string | null; resolution: string; restock: boolean }>;
    refunds: RefundRecord[];
}

export interface RefundRecord {
    id: string;
    provider?: string;
    status: string;
    amount: string;
    currency: string;
    reason?: string | null;
    requested_at?: string | null;
    completed_at: string | null;
}

export interface FulfillmentCenterResponse {
    summary: {
        unfulfilled: number;
        processing: number;
        ready_to_ship: number;
        shipped: number;
        in_transit: number;
        delivered: number;
        return_requests: number;
    };
    fulfillments: FulfillmentRecord[];
}

export interface ReturnsCenterResponse {
    summary: {
        requested: number;
        under_review: number;
        return_shipping: number;
        received: number;
        refund_pending: number;
    };
    returns: ReturnRecord[];
}

export interface FinanceResponse {
    currency: string;
    gross_sales: string;
    refunds: string;
    platform_fees: string;
    provider_fees: string;
    net_sales: string;
    payouts: { pending: string; processing: string; paid: string };
    settlements: Array<{ id: string; provider: string; gross: string; fees: string; refunds: string; net: string; expected_net: string | null; difference: string | null; status: string; period_start: string | null; period_end: string | null; expected_at: string | null; paid_at: string | null }>;
}

export interface CustomerSummary {
    id: string;
    first_name: string | null;
    last_name: string | null;
    name: string;
    email: string;
    phone: string | null;
    status: "active" | "disabled" | "blocked";
    total_orders: number;
    total_spent: string;
    last_order_at: string | null;
}

export interface CustomerDetail extends CustomerSummary {
    accepts_marketing: boolean;
    average_order_value: string;
    addresses: Array<{
        id: string;
        type: "shipping" | "billing";
        first_name: string;
        last_name: string;
        country_code: string;
        province: string | null;
        district: string | null;
        address_line_1: string;
        address_line_2: string | null;
        postal_code: string | null;
        is_default: boolean;
    }>;
    orders: AdminOrderSummary[];
    timeline: Array<{
        type: string;
        created_at: string | null;
        metadata: Record<string, unknown> | null;
    }>;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface DashboardMetrics {
    currency: string;
    range: "today" | "7d" | "30d";
    sales: string;
    refunds: string;
    orders: number;
    average_order: string;
    customers: number;
    recent_orders: AdminOrderSummary[];
    sales_series: Array<{ date: string; sales: string; orders: number }>;
    order_status: {
        unfulfilled: number;
        shipping: number;
        payment_pending: number;
        returns: number;
        failed_payments: number;
    };
    inventory: { available: number; low: number; out: number };
    top_products: Array<{ title: string; quantity: number; revenue: string }>;
    period: {
        from: string;
        to: string;
        previous_from: string;
        previous_to: string;
        timezone: string;
    };
    previous_period: {
        sales: string;
        refunds: string;
        orders: number;
        average_order: string;
        customers: number;
    };
    changes: {
        sales: number | null;
        orders: number | null;
        average_order: number | null;
        customers: number | null;
    };
    customer_summary: {
        total_customers: number;
        new_customers: number;
        previous_new_customers: number;
        new_customers_change: number | null;
        purchasing_customers: number;
        returning_customers: number;
        returning_rate: number;
        recent_customers: Array<{
            id: string;
            name: string | null;
            email: string;
            total_orders: number;
            total_spent: string;
            last_order_at: string | null;
            created_at: string | null;
        }>;
    };
}

interface DataResponse<T> {
    data: T;
}

interface PaginatedResponse<T> {
    data: T[];
    meta: PaginationMeta;
}

function queryString(filters: Record<string, string | undefined>): string {
    const query = new URLSearchParams(
        Object.entries(filters).filter((entry): entry is [string, string] =>
            Boolean(entry[1]),
        ),
    );

    return query.size === 0 ? "" : `?${query.toString()}`;
}

export function getDashboardMetrics(
    range: DashboardMetrics["range"],
): Promise<DataResponse<DashboardMetrics>> {
    return apiRequest(`/api/v1/dashboard${queryString({ range })}`);
}

export function listOrders(filters: {
    q?: string;
    status?: string;
    payment_status?: string;
    fulfillment_status?: string;
    page?: string;
}): Promise<PaginatedResponse<AdminOrderSummary>> {
    return apiRequest(`/api/v1/orders${queryString(filters)}`);
}

export function getOrder(
    orderId: string,
): Promise<DataResponse<AdminOrderDetail>> {
    return apiRequest(`/api/v1/orders/${orderId}`);
}

export interface ManualOrderOptions {
    currency: string;
    variants: Array<{
        id: string;
        title: string;
        variant_title: string;
        sku: string | null;
        price: string;
    }>;
    customers: Array<{ id: string; name: string; email: string }>;
}
export function getManualOrderOptions(): Promise<
    DataResponse<ManualOrderOptions>
> {
    return apiRequest("/api/v1/orders/create-options");
}
export function createManualOrder(payload: {
    customer_id: string | null;
    items: Array<{ variant_id: string; quantity: number }>;
    shipping_total: string;
    notes: string | null;
}): Promise<DataResponse<AdminOrderDetail>> {
    return apiRequest("/api/v1/orders", { method: "POST", body: payload });
}

export function cancelOrder(
    orderId: string,
): Promise<DataResponse<AdminOrderDetail>> {
    return apiRequest(`/api/v1/orders/${orderId}/cancel`, { method: "POST" });
}
export function updateOrderFulfillment(
    orderId: string,
    status: FulfillmentStatus,
): Promise<DataResponse<AdminOrderDetail>> {
    return apiRequest(`/api/v1/orders/${orderId}/fulfillment`, {
        method: "PATCH",
        body: { status },
    });
}

export function refundOrderPayment(
    orderId: string,
    paymentId: string,
    amount: string,
): Promise<DataResponse<AdminOrderDetail>> {
    return apiRequest(
        `/api/v1/orders/${orderId}/payments/${paymentId}/refund`,
        { method: "POST", body: { amount } },
    );
}

export function getFulfillmentCenter(): Promise<DataResponse<FulfillmentCenterResponse>> {
    return apiRequest("/api/v1/fulfillment");
}

export function createFulfillmentFromOrder(orderId: string): Promise<DataResponse<FulfillmentRecord>> {
    return apiRequest(`/api/v1/orders/${orderId}/fulfillments`, { method: "POST", body: {} });
}

export function startFulfillment(fulfillmentId: string): Promise<DataResponse<FulfillmentRecord>> {
    return apiRequest(`/api/v1/fulfillments/${fulfillmentId}/start`, { method: "POST" });
}

export function scanFulfillmentBarcode(fulfillmentId: string, barcode: string): Promise<DataResponse<FulfillmentRecord>> {
    return apiRequest(`/api/v1/fulfillments/${fulfillmentId}/scan`, { method: "POST", body: { barcode } });
}

export function packFulfillment(fulfillmentId: string, payload: { type: string; weight?: string; width?: string; height?: string; length?: string }): Promise<DataResponse<FulfillmentRecord>> {
    return apiRequest(`/api/v1/fulfillments/${fulfillmentId}/pack`, { method: "POST", body: payload });
}

export function createShipment(fulfillmentId: string, payload: { provider: string; service_code: string; package?: Record<string, string> }): Promise<DataResponse<ShipmentRecord>> {
    return apiRequest(`/api/v1/fulfillments/${fulfillmentId}/shipments`, { method: "POST", body: payload });
}

export function updateShipmentStatus(shipmentId: string, status: ShipmentStatus): Promise<DataResponse<ShipmentRecord>> {
    return apiRequest(`/api/v1/shipments/${shipmentId}/status`, { method: "PATCH", body: { status } });
}

export function getReturnsCenter(): Promise<DataResponse<ReturnsCenterResponse>> {
    return apiRequest("/api/v1/returns");
}

export function createReturnRequest(payload: { order_id: string; reason?: string; customer_note?: string; items: Array<{ order_item_id: string; quantity: number; reason_code?: string; resolution?: string }> }): Promise<DataResponse<ReturnRecord>> {
    return apiRequest("/api/v1/returns", { method: "POST", body: payload });
}

export function approveReturn(returnId: string): Promise<DataResponse<ReturnRecord>> {
    return apiRequest(`/api/v1/returns/${returnId}/approve`, { method: "POST" });
}

export function receiveReturn(returnId: string): Promise<DataResponse<ReturnRecord>> {
    return apiRequest(`/api/v1/returns/${returnId}/receive`, { method: "POST" });
}

export function inspectReturn(returnId: string, items: Array<{ return_item_id: string; condition: string; restock: boolean }>): Promise<DataResponse<ReturnRecord>> {
    return apiRequest(`/api/v1/returns/${returnId}/inspect`, { method: "POST", body: { items } });
}

export function refundReturn(returnId: string, amount: string, idempotencyKey: string): Promise<DataResponse<ReturnRecord>> {
    return apiRequest(`/api/v1/returns/${returnId}/refund`, { method: "POST", body: { amount, idempotency_key: idempotencyKey } });
}

export function getFinance(): Promise<DataResponse<FinanceResponse>> {
    return apiRequest("/api/v1/finance");
}

export function listCustomers(filters: {
    q?: string;
    page?: string;
}): Promise<PaginatedResponse<CustomerSummary>> {
    return apiRequest(`/api/v1/customers${queryString(filters)}`);
}

export function getCustomer(
    customerId: string,
): Promise<DataResponse<CustomerDetail>> {
    return apiRequest(`/api/v1/customers/${customerId}`);
}

export function createCustomer(payload: {
    email: string;
    first_name: string;
    last_name: string;
    phone?: string;
    accepts_marketing: boolean;
}): Promise<DataResponse<CustomerSummary>> {
    return apiRequest("/api/v1/customers", { method: "POST", body: payload });
}

export function updateCustomer(
    customerId: string,
    payload: Partial<{
        email: string;
        first_name: string | null;
        last_name: string | null;
        phone: string | null;
        accepts_marketing: boolean;
        status: CustomerSummary["status"];
    }>,
): Promise<DataResponse<CustomerSummary>> {
    return apiRequest(`/api/v1/customers/${customerId}`, {
        method: "PATCH",
        body: payload,
    });
}
