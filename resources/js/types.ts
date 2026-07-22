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

export interface User {
    id: string;
    business_id: string | null;
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
