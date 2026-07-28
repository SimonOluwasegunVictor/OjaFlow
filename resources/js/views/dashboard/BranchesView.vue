<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Plus } from 'lucide-vue-next';
import { useBranchStore, type BranchForm } from '../../stores/branches';
import type { Branch } from '../../types';
import AppButton from '../../components/ui/AppButton.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import TextField from '../../components/ui/TextField.vue';

const branchStore = useBranchStore();
const selectedBranchId = ref<string | null>(null);
const statusTarget = ref<Branch | null>(null);
const message = ref('');

const form = reactive<BranchForm>({
  name: '',
  phone: '',
  address: '',
});

const selectedBranch = computed(() => branchStore.branches.find((branch) => branch.id === selectedBranchId.value) ?? null);

watch(selectedBranch, (branch) => {
  if (branch) {
    form.name = branch.name;
    form.phone = branch.phone ?? '';
    form.address = branch.address ?? '';
  } else {
    resetForm();
  }
});

function resetForm() {
  form.name = '';
  form.phone = '';
  form.address = '';
}

async function submit() {
  message.value = '';
  const success = selectedBranch.value
    ? await branchStore.updateBranch(selectedBranch.value.id, form)
    : await branchStore.createBranch(form);

  if (success) {
    message.value = selectedBranch.value ? 'Branch updated.' : 'Branch created.';
    selectedBranchId.value = null;
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
  <section class="grid gap-5 xl:grid-cols-[1fr_420px]">
    <div class="dashboard-card overflow-hidden !p-0">
      <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-4 sm:p-5">
        <div>
          <h2 class="section-title">Branches</h2>
          <p class="mt-1 text-sm font-medium text-slate-500">Manage business outlets and stock locations.</p>
        </div>
        <AppButton class="!min-h-10 !px-3" @click="selectedBranchId = null">
          <Plus class="h-4 w-4" />
          New
        </AppButton>
      </div>

      <div class="divide-y divide-slate-100">
        <article v-for="branch in branchStore.branches" :key="branch.id" class="grid gap-3 p-4 sm:grid-cols-[1fr_auto] sm:items-center sm:p-5">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-black">{{ branch.name }}</h3>
              <span class="rounded-full px-2 py-1 text-xs font-bold capitalize" :class="branch.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                {{ branch.status }}
              </span>
              <span v-if="branch.is_main" class="rounded-full bg-blue-50 px-2 py-1 text-xs font-bold text-primary">Main</span>
            </div>
            <p class="mt-1 text-sm font-medium text-slate-500">{{ branch.phone ?? 'No phone' }} · {{ branch.address ?? 'No address' }}</p>
          </div>
          <div class="flex gap-2">
            <AppButton class="!min-h-10 !px-3" variant="secondary" @click="selectedBranchId = branch.id">Edit</AppButton>
            <AppButton class="!min-h-10 !px-3" variant="secondary" :disabled="branch.is_main" @click="statusTarget = branch">
              {{ branch.status === 'active' ? 'Deactivate' : 'Activate' }}
            </AppButton>
          </div>
        </article>
        <p v-if="branchStore.branches.length === 0" class="p-8 text-center text-sm font-medium text-slate-500">No branches created yet.</p>
      </div>
    </div>

    <form class="dashboard-card" @submit.prevent="submit">
      <h2 class="section-title">{{ selectedBranch ? 'Edit branch' : 'Create branch' }}</h2>
      <div class="mt-5 grid gap-4">
        <TextField v-model="form.name" label="Branch name" required />
        <TextField v-model="form.phone" label="Phone" />
        <label class="grid gap-2 text-sm font-bold text-slate-900">
          Address
          <textarea v-model="form.address" class="field min-h-24 resize-none" />
        </label>
      </div>
      <p v-if="message" class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">{{ message }}</p>
      <p v-if="branchStore.error" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ branchStore.error }}</p>
      <AppButton class="mt-5 w-full" type="submit" :disabled="branchStore.loading">
        {{ branchStore.loading ? 'Saving...' : selectedBranch ? 'Update Branch' : 'Create Branch' }}
      </AppButton>
    </form>
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
  </section>
</template>
