import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { Branch, BranchResponse, BranchesResponse, UserStatus } from '../types';

export interface BranchForm {
    name: string;
    phone: string;
    address: string;
}

interface BranchState {
    branches: Branch[];
    loading: boolean;
    error: string | null;
}

export const useBranchStore = defineStore('branches', {
    state: (): BranchState => ({
        branches: [],
        loading: false,
        error: null,
    }),

    getters: {
        activeBranches: (state) => state.branches.filter((branch) => branch.status === 'active'),
        mainBranch: (state) => state.branches.find((branch) => branch.is_main) ?? null,
    },

    actions: {
        async fetchBranches() {
            return this.withLoading(async () => {
                const { data } = await http.get<BranchesResponse>('/branches');
                this.branches = data.branches;
            });
        },

        async createBranch(form: BranchForm) {
            return this.withLoading(async () => {
                const { data } = await http.post<BranchResponse>('/branches', branchPayload(form));
                this.branches.push(data.branch);
                this.sortBranches();
            });
        },

        async updateBranch(branchId: string, form: BranchForm) {
            return this.withLoading(async () => {
                const { data } = await http.put<BranchResponse>(`/branches/${branchId}`, branchPayload(form));
                this.replaceBranch(data.branch);
            });
        },

        async updateStatus(branchId: string, status: UserStatus) {
            return this.withLoading(async () => {
                const { data } = await http.patch<BranchResponse>(`/branches/${branchId}/status`, { status });
                this.replaceBranch(data.branch);
            });
        },

        replaceBranch(branch: Branch) {
            this.branches = this.branches.map((item) => item.id === branch.id ? branch : item);
            this.sortBranches();
        },

        sortBranches() {
            this.branches = [...this.branches].sort((a, b) => {
                if (a.is_main !== b.is_main) {
                    return a.is_main ? -1 : 1;
                }

                return a.name.localeCompare(b.name);
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

function branchPayload(form: BranchForm): Record<string, string | null> {
    return {
        name: form.name,
        phone: form.phone || null,
        address: form.address || null,
    };
}
