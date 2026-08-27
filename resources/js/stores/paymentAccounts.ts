import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { PaymentAccount, PaymentAccountsResponse } from '../types';

export interface PaymentAccountForm {
    type: PaymentAccount['type'];
    name: string;
    provider: string;
    account_name: string;
    account_number: string;
    terminal_id: string;
}

export const usePaymentAccountStore = defineStore('paymentAccounts', {
    state: () => ({
        accounts: [] as PaymentAccount[],
        loading: false,
        error: null as string | null,
    }),

    actions: {
        async fetch() {
            return this.withLoading(async () => {
                const { data } = await http.get<PaymentAccountsResponse>('/payment-accounts');
                this.accounts = data.accounts;
            });
        },

        async save(form: PaymentAccountForm, id?: string) {
            return this.withLoading(async () => {
                const { data } = id
                    ? await http.put<{ account: PaymentAccount }>(`/payment-accounts/${id}`, form)
                    : await http.post<{ account: PaymentAccount }>('/payment-accounts', form);
                this.accounts = id ? this.accounts.map((account) => account.id === id ? data.account : account) : [...this.accounts, data.account];
            });
        },

        async remove(id: string) {
            return this.withLoading(async () => {
                await http.delete(`/payment-accounts/${id}`);
                this.accounts = this.accounts.filter((account) => account.id !== id);
            });
        },

        async withLoading(callback: () => Promise<void>) {
            this.loading = true;
            this.error = null;
            try { await callback(); return true; } catch (error) { this.error = extractErrorMessage(error); return false; } finally { this.loading = false; }
        },
    },
});
