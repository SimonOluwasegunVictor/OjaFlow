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
