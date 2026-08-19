export type UserRole = 'admin' | 'staff';
export type UserStatus = 'active' | 'inactive';

export interface Business {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    logo: string | null;
}

export interface Branch {
    id: string;
    name: string;
    phone: string | null;
    address: string | null;
    status: UserStatus;
    is_main: boolean;
    created_at: string;
    updated_at: string;
}

export interface BranchResponse {
    branch: Branch;
}

export interface BranchesResponse {
    branches: Branch[];
}

export interface User {
    id: string;
    business_id: string | null;
    branch_id: string | null;
    first_name: string;
    last_name: string;
    username: string | null;
    email: string | null;
    phone: string | null;
    gender: 'male' | 'female' | 'other';
    address: string | null;
    role: UserRole;
    status: UserStatus;
    permissions: string[];
    business: Business | null;
    branch: Branch | null;
    created_at: string;
    updated_at: string;
}

export type ProductStatus = 'active' | 'archived';
export type StockMovementType = 'purchase' | 'sale' | 'return' | 'damage' | 'correction' | 'manual_adjustment';

export interface Product {
    id: string;
    business_id: string;
    name: string;
    sku: string | null;
    category: string | null;
    unit: string;
    cost_price: string;
    selling_price: string;
    status: ProductStatus;
    branch_id: string;
    quantity: number;
    reorder_level: number;
    is_low_stock: boolean;
    created_at: string;
    updated_at: string;
}

export interface ProductResponse {
    product: Product;
}

export interface ProductsResponse {
    branch: Pick<Branch, 'id' | 'name' | 'status' | 'is_main'>;
    products: Product[];
}

export interface StockResponse {
    product: Product;
    stock: {
        id: string;
        business_id: string;
        branch_id: string;
        product_id: string;
        quantity: number;
        reorder_level: number;
        created_at: string;
        updated_at: string;
    };
}

export interface StockMovement {
    id: string;
    type: StockMovementType;
    quantity: number;
    previous_quantity: number;
    new_quantity: number;
    reason: string | null;
    product: Pick<Product, 'id' | 'name' | 'unit'> | null;
    user: {
        id: string;
        name: string;
    } | null;
    created_at: string;
}

export interface StockMovementsResponse {
    branch: Pick<Branch, 'id' | 'name' | 'status' | 'is_main'>;
    movements: StockMovement[];
}

export interface Customer {
    id: string;
    business_id: string;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    total_purchases: string;
    outstanding_balance: string;
    last_purchase_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface CustomersResponse {
    customers: Customer[];
}

export interface CustomerResponse {
    customer: Customer;
}

export type SalePaymentMethod = 'cash' | 'transfer' | 'pos' | 'credit' | 'split';
export type SalePaymentStatus = 'paid' | 'partial' | 'outstanding';

export interface SaleItemPayload {
    product_id: string;
    quantity: number;
}

export interface SalePayload {
    cart_id: string;
    payment_method: SalePaymentMethod;
    amount_paid: number;
    due_date?: string | null;
}

export interface CartItem {
    id: string;
    product_id: string;
    product_name: string;
    unit: string;
    quantity: number;
    unit_price: string;
    line_total: string;
    available_stock: number | null;
}

export interface Cart {
    id: string;
    business_id: string;
    branch_id: string;
    user_id: string;
    customer_id: string | null;
    status: 'active' | 'checked_out' | 'cancelled';
    subtotal: string;
    discount: string;
    total: string;
    checked_out_at: string | null;
    customer: Pick<Customer, 'id' | 'name' | 'phone'> | null;
    items: CartItem[];
    created_at: string;
    updated_at: string;
}

export interface CartResponse {
    cart: Cart;
}

export interface Sale {
    id: string;
    order_number: string;
    cart_id: string | null;
    customer: Pick<Customer, 'id' | 'name' | 'phone'> | null;
    subtotal: string;
    discount: string;
    total: string;
    amount_paid: string;
    balance_due: string;
    payment_method: SalePaymentMethod;
    payment_status: SalePaymentStatus;
    due_date: string | null;
    paid_at: string | null;
    created_at: string;
    items: Array<{
        id: string;
        product_id: string;
        product_name: string;
        unit: string;
        quantity: number;
        unit_price: string;
        line_total: string;
    }>;
}

export interface SaleResponse {
    sale: Sale;
}

export interface Debt {
    id: string;
    order_number: string;
    customer: Pick<Customer, 'id' | 'name' | 'phone'> | null;
    original_amount: string;
    paid_amount: string;
    balance_due: string;
    payment_status: SalePaymentStatus;
    due_date: string | null;
    is_overdue: boolean;
    created_at: string;
}

export interface DebtsResponse {
    summary: {
        total_owed: string;
        overdue: string;
        customers: number;
    };
    debts: Debt[];
}

export interface DebtPaymentPayload {
    amount: number;
    payment_method: Exclude<SalePaymentMethod, 'credit' | 'split'>;
    note?: string | null;
}

export interface DebtResponse {
    debt: Debt;
}

export interface AuthResponse {
    user: User;
    token: string;
}

export interface StaffResponse {
    user: User;
    temporary_password?: string;
}

export interface ApiValidationError {
    message?: string;
    errors?: Record<string, string[]>;
}
