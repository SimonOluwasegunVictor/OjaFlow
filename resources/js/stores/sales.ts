import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { Debt, DebtPaymentPayload, DebtResponse, DebtsResponse, Sale, SalePayload, SaleResponse } from '../types';

interface SalesState {
    lastSale: Sale | null;
    debts: Debt[];
    debtSummary: DebtsResponse['summary'];
    loading: boolean;
    error: string | null;
}

export const useSalesStore = defineStore('sales', {
    state: (): SalesState => ({
        lastSale: null,
        debts: [],
        debtSummary: {
            total_owed: '0.00',
            overdue: '0.00',
            customers: 0,
        },
        loading: false,
        error: null,
    }),

    actions: {
        async completeSale(payload: SalePayload) {
            return this.withLoading(async () => {
                const { data } = await http.post<SaleResponse>('/sales', payload);
                this.lastSale = data.sale;
            });
        },

        async fetchDebts(params: { status?: 'outstanding' | 'partial' | 'overdue' | 'paid' } = {}) {
            return this.withLoading(async () => {
                const { data } = await http.get<DebtsResponse>('/debts', { params });
                this.debts = data.debts;
                this.debtSummary = data.summary;
            });
        },

        async recordDebtPayment(saleId: string, payload: DebtPaymentPayload) {
            return this.withLoading(async () => {
                const { data } = await http.post<DebtResponse>(`/debts/${saleId}/payments`, payload);
                this.debts = this.debts.map((debt) => debt.id === saleId ? data.debt : debt);
                this.debts = this.debts.filter((debt) => Number(debt.balance_due) > 0);
                await this.fetchDebts();
            });
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
