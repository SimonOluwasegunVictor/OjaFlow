import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { StaffResponse, User, UserStatus } from '../types';

export interface StaffForm {
    first_name: string;
    last_name: string;
    username: string;
    email: string;
    phone: string;
    gender: string;
    address: string;
    password: string;
    permissions: string[];
}

interface StaffState {
    staff: User[];
    permissions: string[];
    temporaryPassword: string | null;
    loading: boolean;
    error: string | null;
}

export const useStaffStore = defineStore('staff', {
    state: (): StaffState => ({
        staff: [],
        permissions: [],
        temporaryPassword: null,
        loading: false,
        error: null,
    }),

    actions: {
        async bootstrap() {
            await Promise.all([
                this.fetchPermissions(),
                this.fetchStaff(),
            ]);
        },

        async fetchPermissions() {
            return this.withLoading(async () => {
                const { data } = await http.get<{ permissions: string[] }>('/staff-permissions');
                this.permissions = data.permissions;
            });
        },

        async fetchStaff() {
            return this.withLoading(async () => {
                const { data } = await http.get<{ staff: User[] }>('/staff');
                this.staff = data.staff;
            });
        },

        async fetchStaffMember(staffId: string) {
            return this.withLoading(async () => {
                const { data } = await http.get<StaffResponse>(`/staff/${staffId}`);
                this.replaceStaff(data.user);
            });
        },

        async createStaff(form: StaffForm) {
            return this.withLoading(async () => {
                const payload = staffPayload(form);
                const { data } = await http.post<StaffResponse>('/staff', payload);
                this.staff.unshift(data.user);
                this.temporaryPassword = data.temporary_password ?? null;
            });
        },

        async updateStaff(staffId: string, form: StaffForm) {
            return this.withLoading(async () => {
                const payload = staffPayload(form);
                delete payload.password;

                const { data } = await http.put<StaffResponse>(`/staff/${staffId}`, payload);
                this.replaceStaff(data.user);
            });
        },

        async updateStatus(staffId: string, status: UserStatus) {
            return this.withLoading(async () => {
                const { data } = await http.patch<StaffResponse>(`/staff/${staffId}/status`, { status });
                this.replaceStaff(data.user);
            });
        },

        async resetPassword(staffId: string, password = '') {
            return this.withLoading(async () => {
                const { data } = await http.patch<StaffResponse>(`/staff/${staffId}/password`, { password: password || null });
                this.replaceStaff(data.user);
                this.temporaryPassword = data.temporary_password ?? null;
            });
        },

        async updatePermissions(staffId: string, permissions: string[]) {
            return this.withLoading(async () => {
                const { data } = await http.patch<StaffResponse>(`/staff/${staffId}/permissions`, { permissions });
                this.replaceStaff(data.user);
            });
        },

        async deleteStaff(staffId: string) {
            return this.withLoading(async () => {
                await http.delete(`/staff/${staffId}`);
                this.staff = this.staff.filter((user) => user.id !== staffId);
            });
        },

        replaceStaff(user: User) {
            this.staff = this.staff.map((member) => member.id === user.id ? user : member);
        },

        clearTemporaryPassword() {
            this.temporaryPassword = null;
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

function staffPayload(form: StaffForm): Record<string, string | string[] | null> {
    return {
        first_name: form.first_name,
        last_name: form.last_name,
        username: form.username,
        email: form.email || null,
        phone: form.phone || null,
        gender: form.gender,
        address: form.address || null,
        password: form.password || null,
        permissions: form.permissions,
    };
}
