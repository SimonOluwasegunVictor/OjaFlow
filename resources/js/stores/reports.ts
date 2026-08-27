import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { DashboardReport } from '../types';

interface ReportState {
    dashboard: DashboardReport | null;
    loading: boolean;
    error: string | null;
}

export const useReportStore = defineStore('reports', {
    state: (): ReportState => ({ dashboard: null, loading: false, error: null }),

    actions: {
        async fetchDashboard() {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await http.get<DashboardReport>('/dashboard-report');
                this.dashboard = data;
            } catch (error) {
                this.error = extractErrorMessage(error);
            } finally {
                this.loading = false;
            }
        },
    },
});
