import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { Customer, CustomerResponse, CustomersResponse } from '../types';

export interface CustomerForm {
    name: string;
    phone: string;
    email: string;
    address: string;
}

interface CustomerState {
    customers: Customer[];
    loading: boolean;
    error: string | null;
}

export const useCustomerStore = defineStore('customers', {
    state: (): CustomerState => ({
        customers: [],
        loading: false,
        error: null,
    }),

    actions: {
        async fetchCustomers(params: { search?: string } = {}) {
            return this.withLoading(async () => {
                const { data } = await http.get<CustomersResponse>('/customers', { params });
                this.customers = data.customers;
            });
        },

        async createCustomer(form: CustomerForm) {
            return this.withLoading(async () => {
                const { data } = await http.post<CustomerResponse>('/customers', customerPayload(form));
                this.customers.push(data.customer);
                this.sortCustomers();
            });
        },

        async updateCustomer(customerId: string, form: CustomerForm) {
            return this.withLoading(async () => {
                const { data } = await http.put<CustomerResponse>(`/customers/${customerId}`, customerPayload(form));
                this.customers = this.customers.map((customer) => customer.id === customerId ? data.customer : customer);
                this.sortCustomers();
            });
        },

        sortCustomers() {
            this.customers = [...this.customers].sort((a, b) => a.name.localeCompare(b.name));
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

function customerPayload(form: CustomerForm): Record<string, string | null> {
    return {
        name: form.name,
        phone: form.phone || null,
        email: form.email || null,
        address: form.address || null,
    };
}
