import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { EndOfDayResponse, SalesResponse } from '../types';

export const useSalesHistoryStore = defineStore('salesHistory', {
    state: () => ({
        sales: [] as SalesResponse['sales'],
        endOfDay: null as EndOfDayResponse | null,
        loading: false,
        error: null as string | null,
    }),

    actions: {
        async fetchSales(params: { date?: string; search?: string; payment_status?: string } = {}) {
            return this.withLoading(async () => {
                const { data } = await http.get<SalesResponse>('/sales', { params });
                this.sales = data.sales;
            });
        },

        async fetchEndOfDay(date?: string) {
            return this.withLoading(async () => {
                const { data } = await http.get<EndOfDayResponse>('/end-of-day', { params: { date } });
                this.endOfDay = data;
            });
        },

        async withLoading(callback: () => Promise<void>) {
            this.loading = true;
            this.error = null;
            try { await callback(); return true; } catch (error) { this.error = extractErrorMessage(error); return false; } finally { this.loading = false; }
        },
    },
});
