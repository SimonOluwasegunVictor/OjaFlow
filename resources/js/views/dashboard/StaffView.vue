<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Clock3, KeyRound, Pencil, Plus, ShieldCheck, Trash2, Users } from 'lucide-vue-next';
import { useBranchStore } from '../../stores/branches';
import { useStaffStore, type StaffForm } from '../../stores/staff';
import type { User } from '../../types';
import BaseModal from '../../components/ui/BaseModal.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import TextField from '../../components/ui/TextField.vue';

const staffStore = useStaffStore();
const branchStore = useBranchStore();

const search = ref('');
const showModal = ref(false);
const selectedStaffId = ref<string | null>(null);
const statusTarget = ref<User | null>(null);
const confirmingPasswordReset = ref(false);
const confirmingDelete = ref(false);
const resetPasswordValue = ref('');

const form = reactive<StaffForm>({
  first_name: '',
  last_name: '',
  username: '',
  branch_id: '',
  email: '',
  phone: '',
  gender: 'other',
  address: '',
  password: '',
  permissions: [],
});

const selectedStaff = computed(() => staffStore.staff.find((staff) => staff.id === selectedStaffId.value) ?? null);

const filteredStaff = computed(() => {
  const query = search.value.trim().toLowerCase();
  if (!query) {
    return staffStore.staff;
  }

  return staffStore.staff.filter((member) =>
    [
      member.first_name,
      member.last_name,
      member.username,
      member.phone,
      member.email,
      member.branch?.name,
    ].some((field) => field?.toLowerCase().includes(query)),
  );
});

const permissionLabels: Record<string, string> = {
  view_dashboard: 'View dashboard',
  record_sales: 'Record sales',
  view_sales: 'View sales',
  cancel_sales: 'Cancel sales',
  manage_products: 'Manage products',
  view_stock: 'View stock',
  adjust_stock: 'Adjust stock',
  manage_customers: 'Manage customers',
  record_debt_payments: 'Record debt payments',
  view_reports: 'View reports',
  print_receipts: 'Print receipts',
  manage_staff: 'Manage staff',
  manage_settings: 'Manage settings',
};

watch(selectedStaff, (staff) => {
  if (!staff) {
    resetForm();
    return;
  }

  form.first_name = staff.first_name;
  form.last_name = staff.last_name;
  form.username = staff.username ?? '';
  form.branch_id = staff.branch_id ?? '';
  form.email = staff.email ?? '';
  form.phone = staff.phone ?? '';
  form.gender = staff.gender;
  form.address = staff.address ?? '';
  form.password = '';
  form.permissions = [...(staff.permissions ?? [])];
});

function fullName(member: User) {
  return `${member.first_name} ${member.last_name}`;
}

function initials(member: User) {
  return `${member.first_name.slice(0, 1)}${member.last_name.slice(0, 1)}`;
}

function lastLoginLabel(member: User, index: number) {
  const fallback = ['2 hrs ago', 'Today, 9:00 AM', '3 days ago'];
  const date = new Date(member.updated_at);

  if (Number.isNaN(date.getTime())) {
    return fallback[index % fallback.length];
  }

  const diffMs = Date.now() - date.getTime();
  const diffHours = Math.floor(diffMs / 3_600_000);

  if (diffHours < 1) {
    return 'Just now';
  }

  if (diffHours < 24) {
    return `${diffHours} hr${diffHours === 1 ? '' : 's'} ago`;
  }

  const diffDays = Math.floor(diffHours / 24);

  if (diffDays < 7) {
    return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
  }

  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function requestDelete(member: User) {
  selectedStaffId.value = member.id;
  confirmingDelete.value = true;
}

function resetForm() {
  form.first_name = '';
  form.last_name = '';
  form.username = '';
  form.branch_id = branchStore.mainBranch?.id ?? '';
  form.email = '';
  form.phone = '';
  form.gender = 'other';
  form.address = '';
  form.password = '';
  form.permissions = [];
  resetPasswordValue.value = '';
}

async function openModal(member?: User) {
  selectedStaffId.value = member?.id ?? null;
  if (!member) {
    resetForm();
  } else {
    await staffStore.fetchStaffMember(member.id).catch(() => undefined);
  }
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedStaffId.value = null;
  resetPasswordValue.value = '';
}

async function submit() {
  const success = selectedStaff.value
    ? await staffStore.updateStaff(selectedStaff.value.id, form)
    : await staffStore.createStaff(form);

  if (success) {
    closeModal();
  }
}

async function toggleStatus() {
  if (!statusTarget.value) {
    return;
  }

  const staff = statusTarget.value;
  await staffStore.updateStatus(staff.id, staff.status === 'active' ? 'inactive' : 'active');
  statusTarget.value = null;
}

async function resetPassword() {
  if (!selectedStaff.value) {
    return;
  }

  await staffStore.resetPassword(selectedStaff.value.id, resetPasswordValue.value);
  resetPasswordValue.value = '';
  confirmingPasswordReset.value = false;
}

async function deleteStaff() {
  if (!selectedStaff.value) {
    return;
  }

  const success = await staffStore.deleteStaff(selectedStaff.value.id);
  if (success) {
    confirmingDelete.value = false;
    closeModal();
  }
}

function togglePermission(permission: string) {
  form.permissions = form.permissions.includes(permission)
    ? form.permissions.filter((item) => item !== permission)
    : [...form.permissions, permission];
}
</script>

<template>
  <div class="flex h-full flex-col">
    <div class="flex-1 overflow-auto">
      <div class="mb-4 flex justify-end">
        <button
          type="button"
          class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-[10px] bg-blue-600 px-4 text-sm font-semibold text-white shadow-soft transition hover:bg-blue-700 sm:w-auto"
          @click="openModal()"
        >
          <Plus class="h-4 w-4" />
          Add Staff
        </button>
      </div>

      <div
        v-if="staffStore.temporaryPassword"
        class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-300">Temporary password</p>
            <p class="mt-2 truncate rounded-lg bg-white px-3 py-2 font-mono text-sm text-amber-900 dark:bg-gray-900 dark:text-amber-200">
              {{ staffStore.temporaryPassword }}
            </p>
            <p class="mt-2 text-xs font-medium text-amber-800 dark:text-amber-300">Share this with the staff member before hiding it.</p>
          </div>
          <button class="shrink-0 rounded-lg px-3 py-2 text-xs font-semibold text-amber-900 transition hover:bg-amber-100 dark:text-amber-300 dark:hover:bg-amber-500/10" type="button" @click="staffStore.clearTemporaryPassword()">
            Hide
          </button>
        </div>
      </div>

      <template v-if="filteredStaff.length">
        <div class="grid gap-3 lg:hidden">
          <article
            v-for="(member, index) in filteredStaff"
            :key="member.id"
            class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.12)] dark:border-white/[0.07] dark:bg-gray-800 sm:p-5"
          >
            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div class="flex min-w-0 gap-3">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-violet-100 text-sm font-semibold text-violet-700">
                  {{ initials(member) }}
                </div>
                <div class="min-w-0">
                  <h3 class="truncate text-base font-semibold text-slate-950 dark:text-white">{{ fullName(member) }}</h3>
                  <p class="mt-1 break-words text-xs font-medium leading-5 text-blue-300">{{ member.username ? `@${member.username}` : '@staff' }} - {{ member.phone ?? member.email ?? 'No contact' }}</p>
                </div>
              </div>
              <div class="flex shrink-0 items-start sm:justify-end">
                <span :class="['rounded-full border px-3 py-1 text-xs font-bold leading-none', member.status === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500']">{{ member.status === 'active' ? 'Active' : 'Inactive' }}</span>
              </div>
            </div>
            <div class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-white/[0.06] sm:flex-row sm:items-center sm:justify-between">
              <p class="flex min-w-0 items-center gap-1 text-xs font-medium text-blue-300">
                <Clock3 class="h-3.5 w-3.5" />
                <span class="truncate">Last login: {{ lastLoginLabel(member, index) }}</span>
              </p>
              <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                <button
                  type="button"
                  class="inline-flex h-9 min-w-0 items-center justify-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-800 transition hover:bg-slate-50"
                  @click="openModal(member)"
                >
                  <Pencil class="h-3.5 w-3.5" /> Edit
                </button>
                <button
                  type="button"
                  class="inline-flex h-9 min-w-0 items-center justify-center gap-1.5 rounded-lg border border-rose-200 px-3 text-xs font-semibold text-rose-600 transition hover:bg-rose-50"
                  @click="requestDelete(member)"
                >
                  <Trash2 class="h-3.5 w-3.5" /> Remove
                </button>
              </div>
            </div>
          </article>
        </div>

        <div class="hidden space-y-3 lg:block">
          <article
            v-for="(member, index) in filteredStaff"
            :key="member.id"
            class="rounded-2xl border border-slate-200 bg-white px-5 py-6 shadow-[0_1px_3px_rgba(15,23,42,0.16)] dark:border-white/[0.07] dark:bg-gray-800"
          >
            <div class="flex items-start justify-between gap-5">
              <div class="flex min-w-0 items-center gap-3">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-violet-100 text-sm font-semibold text-violet-700">
                  {{ initials(member) }}
                </div>
                <div class="min-w-0">
                  <h2 class="truncate text-base font-semibold leading-5 text-slate-950 dark:text-white">{{ fullName(member) }}</h2>
                  <p class="mt-1 text-xs font-medium text-blue-300">{{ member.username ? `@${member.username}` : '@staff' }} - {{ member.phone ?? member.email ?? 'No contact' }}</p>
                </div>
              </div>
              <div class="flex shrink-0 items-start">
                <span :class="['rounded-full border px-3 py-1 text-xs font-bold leading-none', member.status === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500']">{{ member.status === 'active' ? 'Active' : 'Inactive' }}</span>
              </div>
            </div>

            <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-white/[0.06]">
              <p class="flex items-center gap-1 text-xs font-medium text-blue-300">
                <Clock3 class="h-3.5 w-3.5" />
                Last login: {{ lastLoginLabel(member, index) }}
              </p>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 transition hover:bg-slate-50"
                  @click="openModal(member)"
                >
                  <Pencil class="h-3.5 w-3.5" /> Edit
                </button>
                <button
                  type="button"
                  class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 text-xs font-semibold text-rose-600 transition hover:bg-rose-50"
                  @click="requestDelete(member)"
                >
                  <Trash2 class="h-3.5 w-3.5" /> Remove
                </button>
              </div>
            </div>
          </article>
        </div>
      </template>

      <div v-else class="flex flex-col items-center justify-center gap-4 py-24">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-gray-200 bg-gray-100 dark:border-white/[0.08] dark:bg-white/[0.04]">
          <Users class="h-5 w-5 text-gray-400 dark:text-gray-600" />
        </div>
        <div class="text-center">
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ search ? 'No results found' : 'No staff yet' }}</p>
          <p class="mt-1 text-xs text-gray-400 dark:text-gray-600">{{ search ? 'Try a different search term' : 'Click New Staff to get started' }}</p>
        </div>
      </div>
    </div>

    <BaseModal :show="showModal" :title="selectedStaff ? 'Edit Staff' : 'New Staff'" @close="closeModal">
      <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
          <TextField v-model="form.first_name" label="First name" required />
          <TextField v-model="form.last_name" label="Last name" required />
          <TextField v-model="form.username" class="sm:col-span-2" label="Username" placeholder="staff_name" autocomplete="username" required />
          <label class="grid gap-2 text-sm font-semibold text-gray-900 dark:text-gray-200 sm:col-span-2">
            Branch
            <select v-model="form.branch_id" class="field">
              <option value="">Use main branch</option>
              <option v-for="branch in branchStore.activeBranches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
            </select>
          </label>
          <TextField v-model="form.phone" label="Phone" required />
          <TextField v-model="form.email" label="Email" type="email" />
          <label class="grid gap-2 text-sm font-semibold text-gray-900 dark:text-gray-200">
            Gender
            <select v-model="form.gender" class="field">
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </label>
          <label class="grid gap-2 text-sm font-semibold text-gray-900 dark:text-gray-200 sm:col-span-2">
            Address
            <textarea v-model="form.address" class="field min-h-20 resize-none py-3" />
          </label>
          <TextField v-if="!selectedStaff" v-model="form.password" class="sm:col-span-2" label="Password" type="password" placeholder="Leave empty to auto-generate from company name" />
        </div>

        <div>
          <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
            <ShieldCheck class="h-4 w-4 text-primary" />
            Permissions
          </h3>
          <div class="mt-3 grid max-h-56 gap-2 overflow-auto pr-1 sm:grid-cols-2">
            <label v-for="permission in staffStore.permissions" :key="permission" class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 text-sm font-semibold text-gray-700 dark:border-white/[0.08] dark:text-gray-300">
              <input class="h-4 w-4 rounded border-gray-300 text-primary dark:border-white/[0.12] dark:bg-white/[0.04]" type="checkbox" :checked="form.permissions.includes(permission)" @change="togglePermission(permission)">
              <span>{{ permissionLabels[permission] ?? permission }}</span>
            </label>
          </div>
        </div>

        <div v-if="selectedStaff" class="rounded-xl border border-gray-200 p-3 dark:border-white/[0.08]">
          <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Password and removal</h3>
          <TextField v-model="resetPasswordValue" class="mt-3" label="New password" type="password" placeholder="Leave empty to auto-generate from company name" />
          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <button type="button" class="flex min-h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-white/[0.08] dark:text-gray-400 dark:hover:bg-white/[0.04]" @click="confirmingPasswordReset = true">
              <KeyRound class="h-4 w-4" />
              Reset Password
            </button>
            <button type="button" class="flex min-h-10 items-center justify-center gap-2 rounded-lg border border-rose-200 px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/15" @click="confirmingDelete = true">
              <Trash2 class="h-4 w-4" />
              Delete Staff
            </button>
          </div>
        </div>

        <p v-if="staffStore.error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ staffStore.error }}</p>

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
            :disabled="staffStore.loading"
            class="cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50"
          >
            {{ staffStore.loading ? 'Saving...' : selectedStaff ? 'Update Staff' : 'Create Staff' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <ConfirmDialog
      :open="Boolean(statusTarget)"
      :title="`${statusTarget?.status === 'active' ? 'Deactivate' : 'Activate'} staff?`"
      :description="statusTarget ? `${fullName(statusTarget)} will ${statusTarget.status === 'active' ? 'lose access until reactivated' : 'be able to sign in again'}.` : ''"
      :confirm-label="statusTarget?.status === 'active' ? 'Deactivate' : 'Activate'"
      tone="warning"
      :loading="staffStore.loading"
      @update:open="(value) => { if (!value) statusTarget = null }"
      @confirm="toggleStatus"
    />
    <ConfirmDialog
      v-model:open="confirmingPasswordReset"
      title="Reset password?"
      description="The current staff password will stop working. If you leave the field empty, a temporary password will be generated from the company name."
      confirm-label="Reset Password"
      tone="warning"
      :loading="staffStore.loading"
      @confirm="resetPassword"
    />
    <ConfirmDialog
      v-model:open="confirmingDelete"
      title="Delete staff account?"
      description="This removes the staff account from this business. This action cannot be undone from the dashboard."
      confirm-label="Delete Staff"
      tone="danger"
      :loading="staffStore.loading"
      @confirm="deleteStaff"
    />
  </div>
</template>
