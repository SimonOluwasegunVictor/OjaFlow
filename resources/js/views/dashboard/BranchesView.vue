<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Building2, Pencil, Plus, Power } from 'lucide-vue-next';
import { useBranchStore, type BranchForm } from '../../stores/branches';
import type { Branch } from '../../types';
import PageHeader from '../../components/dashboard/Pageheader.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';

const branchStore = useBranchStore();

const search = ref('');
const showModal = ref(false);
const selectedBranchId = ref<string | null>(null);
const statusTarget = ref<Branch | null>(null);

const form = reactive<BranchForm>({
  name: '',
  phone: '',
  address: '',
});

const selectedBranch = computed(() => branchStore.branches.find((branch) => branch.id === selectedBranchId.value) ?? null);

const filteredBranches = computed(() => {
  const query = search.value.trim().toLowerCase();
  if (!query) {
    return branchStore.branches;
  }

  return branchStore.branches.filter((branch) =>
    [branch.name, branch.phone, branch.address].some((field) => field?.toLowerCase().includes(query)),
  );
});

watch(selectedBranch, (branch) => {
  if (branch) {
    form.name = branch.name;
    form.phone = branch.phone ?? '';
    form.address = branch.address ?? '';
  }
});

function openModal(branch?: Branch) {
  selectedBranchId.value = branch?.id ?? null;
  if (!branch) {
    form.name = '';
    form.phone = '';
    form.address = '';
  }
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedBranchId.value = null;
}

async function submit() {
  const success = selectedBranch.value
    ? await branchStore.updateBranch(selectedBranch.value.id, form)
    : await branchStore.createBranch(form);

  if (success) {
    closeModal();
  }
}

async function toggleStatus() {
  if (!statusTarget.value) {
    return;
  }

  const branch = statusTarget.value;
  await branchStore.updateStatus(branch.id, branch.status === 'active' ? 'inactive' : 'active');
  statusTarget.value = null;
}
</script>

<template>
  <div class="flex h-full flex-col">
    <PageHeader
      :icon="Building2"
      title="Branches"
      :count="branchStore.branches.length"
      description="Manage business outlets and stock locations."
      :search="search"
      search-placeholder="Search branches…"
      action-label="New Branch"
      :action-icon="Plus"
      @update:search="search = $event"
      @action="openModal()"
    />

    <div class="flex-1 overflow-auto px-4 py-5 sm:px-6">
      <template v-if="filteredBranches.length">
        <!-- Mobile cards -->
        <div class="grid gap-3 md:hidden">
          <article
            v-for="branch in filteredBranches"
            :key="branch.id"
            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/[0.07] dark:bg-gray-800"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ branch.name }}</h3>
                  <span
                    v-if="branch.is_main"
                    class="inline-flex h-5 shrink-0 items-center rounded-full bg-blue-50 px-2 text-[11px] font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-400"
                  >
                    Main
                  </span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">{{ branch.phone ?? 'No phone' }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-500">{{ branch.address ?? 'No address' }}</p>
              </div>
              <div class="flex shrink-0 items-center gap-1.5">
                <div :class="['h-1.5 w-1.5 rounded-full', branch.status === 'active' ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600']" />
                <span class="text-[11px] text-gray-500 dark:text-gray-500">{{ branch.status === 'active' ? 'Active' : 'Inactive' }}</span>
              </div>
            </div>
            <div class="mt-3 flex gap-2 border-t border-gray-100 pt-3 dark:border-white/[0.06]">
              <button
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-white/[0.08] dark:text-gray-400 dark:hover:bg-white/[0.04]"
                @click="openModal(branch)"
              >
                <Pencil class="h-3.5 w-3.5" /> Edit
              </button>
              <button
                type="button"
                :disabled="branch.is_main"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/[0.08] dark:text-gray-400 dark:hover:bg-white/[0.04]"
                @click="statusTarget = branch"
              >
                <Power class="h-3.5 w-3.5" /> {{ branch.status === 'active' ? 'Deactivate' : 'Activate' }}
              </button>
            </div>
          </article>
        </div>

        <!-- Desktop table -->
        <div class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/[0.07] dark:bg-gray-800 md:block">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/80 dark:border-white/[0.06] dark:bg-white/[0.02]">
                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Branch</th>
                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Phone</th>
                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Address</th>
                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Status</th>
                <th class="py-3 pl-3 pr-4 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
              <tr v-for="branch in filteredBranches" :key="branch.id" class="group transition-colors hover:bg-gray-50/60 dark:hover:bg-white/[0.02]">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ branch.name }}</span>
                    <span
                      v-if="branch.is_main"
                      class="inline-flex h-5 items-center rounded-full bg-blue-50 px-2 text-[11px] font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-400"
                    >
                      Main
                    </span>
                  </div>
                </td>
                <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400">{{ branch.phone ?? '—' }}</td>
                <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400">{{ branch.address ?? '—' }}</td>
                <td class="px-3 py-3">
                  <div class="flex items-center gap-1.5">
                    <div :class="['h-1.5 w-1.5 rounded-full', branch.status === 'active' ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600']" />
                    <span class="text-xs text-gray-500 dark:text-gray-500">{{ branch.status === 'active' ? 'Active' : 'Inactive' }}</span>
                  </div>
                </td>
                <td class="py-3 pl-3 pr-4 text-right">
                  <div class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                    <button
                      type="button"
                      class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/[0.06] dark:hover:text-gray-200"
                      @click="openModal(branch)"
                    >
                      <Pencil class="h-3.5 w-3.5" />
                    </button>
                    <button
                      type="button"
                      :disabled="branch.is_main"
                      class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-white/[0.06] dark:hover:text-gray-200"
                      @click="statusTarget = branch"
                    >
                      <Power class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <div v-else class="flex flex-col items-center justify-center gap-4 py-24">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-gray-200 bg-gray-100 dark:border-white/[0.08] dark:bg-white/[0.04]">
          <Building2 class="h-5 w-5 text-gray-400 dark:text-gray-600" />
        </div>
        <div class="text-center">
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ search ? 'No results found' : 'No branches yet' }}</p>
          <p class="mt-1 text-xs text-gray-400 dark:text-gray-600">{{ search ? 'Try a different search term' : 'Click New Branch to get started' }}</p>
        </div>
      </div>
    </div>

    <BaseModal :show="showModal" :title="selectedBranch ? 'Edit Branch' : 'New Branch'" @close="closeModal">
      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-500">Branch name</label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Lekki Phase 1"
            class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-blue-500/40 dark:border-white/[0.08] dark:bg-white/[0.04] dark:text-white"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-500">Phone</label>
          <input
            v-model="form.phone"
            type="text"
            placeholder="080..."
            class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-blue-500/40 dark:border-white/[0.08] dark:bg-white/[0.04] dark:text-white"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-500">Address</label>
          <textarea
            v-model="form.address"
            rows="3"
            placeholder="Street, area, city"
            class="w-full resize-none rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-blue-500/40 dark:border-white/[0.08] dark:bg-white/[0.04] dark:text-white"
          />
        </div>

        <p v-if="branchStore.error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
          {{ branchStore.error }}
        </p>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-white/[0.06]">
          <button
            type="button"
            class="cursor-pointer rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-white/[0.08] dark:text-gray-400 dark:hover:bg-white/[0.04]"
            @click="closeModal"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="branchStore.loading"
            class="cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50"
          >
            {{ branchStore.loading ? 'Saving…' : selectedBranch ? 'Update Branch' : 'Create Branch' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <ConfirmDialog
      :open="Boolean(statusTarget)"
      :title="`${statusTarget?.status === 'active' ? 'Deactivate' : 'Activate'} branch?`"
      :description="statusTarget ? `${statusTarget.name} will be ${statusTarget.status === 'active' ? 'hidden from active branch lists' : 'available for new activity again'}.` : ''"
      :confirm-label="statusTarget?.status === 'active' ? 'Deactivate' : 'Activate'"
      tone="warning"
      :loading="branchStore.loading"
      @update:open="(value) => { if (!value) statusTarget = null }"
      @confirm="toggleStatus"
    />
  </div>
</template>
