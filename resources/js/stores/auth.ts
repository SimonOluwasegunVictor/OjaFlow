import { defineStore } from 'pinia';
import axios from 'axios';
import http from '../https';
import type { ApiValidationError, AuthResponse, User } from '../types';

interface RegisterPayload {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    gender: string;
    business_name: string;
    business_email: string;
    business_phone: string;
    business_address: string;
    password: string;
}

interface LoginPayload {
    login: string;
    password: string;
}

interface ProfilePayload {
    first_name?: string;
    last_name?: string;
    username?: string | null;
    email?: string | null;
    phone?: string | null;
    gender?: string;
    address?: string | null;
    current_password?: string;
    new_password?: string;
}

interface AuthState {
    user: User | null;
    token: string | null;
    loading: boolean;
    error: string | null;
}

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        user: JSON.parse(localStorage.getItem('ojaflow_user') ?? 'null') as User | null,
        token: localStorage.getItem('ojaflow_token'),
        loading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token && state.user),
        isAdmin: (state) => state.user?.role === 'admin',
        fullName: (state) => state.user ? `${state.user.first_name} ${state.user.last_name}` : '',
    },

    actions: {
        async register(payload: RegisterPayload) {
            return this.withLoading(async () => {
                const { data } = await http.post<AuthResponse>('/register', payload);
                this.setSession(data);
            });
        },

        async login(payload: LoginPayload) {
            return this.withLoading(async () => {
                const { data } = await http.post<AuthResponse>('/login', payload);
                this.setSession(data);
            });
        },

        async updateProfile(payload: ProfilePayload) {
            if (!this.user) {
                return;
            }

            return this.withLoading(async () => {
                const { data } = await http.put<{ user: User }>(`/users/${this.user?.id}`, payload);
                this.setUser(data.user);
            });
        },

        async logout() {
            try {
                await http.post('/logout');
            } finally {
                this.clearSession();
            }
        },

        setSession(payload: AuthResponse) {
            this.token = payload.token;
            this.setUser(payload.user);
            localStorage.setItem('ojaflow_token', payload.token);
        },

        setUser(user: User) {
            this.user = user;
            localStorage.setItem('ojaflow_user', JSON.stringify(user));
        },

        clearSession() {
            this.user = null;
            this.token = null;
            localStorage.removeItem('ojaflow_user');
            localStorage.removeItem('ojaflow_token');
        },

        async withLoading(callback: () => Promise<void>): Promise<boolean> {
            this.loading = true;
            this.error = null;

            try {
                await callback();
                return true;
            } catch (error) {
                this.error = extractErrorMessage(error);
                return false;
            } finally {
                this.loading = false;
            }
        },
    },
});

export function extractErrorMessage(error: unknown): string {
    if (!axios.isAxiosError<ApiValidationError>(error)) {
        return 'Something went wrong. Please try again.';
    }

    const payload = error.response?.data;
    const firstFieldError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
    const status = error.response?.status;
    const message = payload?.message ?? '';

    if (status && status >= 500) {
        return 'We could not complete that request right now. Please try again.';
    }

    if (message.includes('SQLSTATE') || message.includes('Exception') || message.includes('SQL:')) {
        return 'We could not complete that request right now. Please try again.';
    }

    return firstFieldError ?? message ?? 'Request failed. Please check your input.';
}
