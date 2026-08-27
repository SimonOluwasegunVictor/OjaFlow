import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { Business } from '../types';

export interface BusinessSettingsPayload {
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    receipt_footer: string | null;
    receipt_size: '58mm' | '80mm' | 'a4';
    settings: {
        sms_enabled: boolean;
        whatsapp_enabled: boolean;
        low_stock_alerts: boolean;
    };
}

export const useBusinessStore = defineStore('business', {
    state: () => ({
        business: null as Business | null,
        loading: false,
        error: null as string | null,
    }),

    actions: {
        async fetch() {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await http.get<{ business: Business }>('/business');
                this.business = data.business;
            } catch (error) {
                this.error = extractErrorMessage(error);
            } finally {
                this.loading = false;
            }
        },

        async update(payload: BusinessSettingsPayload) {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await http.put<{ business: Business }>('/business', payload);
                this.business = data.business;
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
