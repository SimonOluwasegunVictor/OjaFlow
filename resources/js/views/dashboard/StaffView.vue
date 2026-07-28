<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { KeyRound, Plus, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { useBranchStore } from '../../stores/branches';
import { useStaffStore, type StaffForm } from '../../stores/staff';
import type { User } from '../../types';
import AppButton from '../../components/ui/AppButton.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import TextField from '../../components/ui/TextField.vue';

const staffStore = useStaffStore();
const branchStore = useBranchStore();
const selectedStaffId = ref<string | null>(null);
const statusTarget = ref<User | null>(null);
const confirmingPasswordReset = ref(false);
const confirmingDelete = ref(false);
const resetPasswordValue = ref('');
const message = ref('');

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

async function selectStaff(staffId: string) {
  selectedStaffId.value = staffId;
  await staffStore.fetchStaffMember(staffId).catch(() => undefined);
}

async function submit() {
  message.value = '';
  const success = selectedStaff.value
    ? await staffStore.updateStaff(selectedStaff.value.id, form)
    : await staffStore.createStaff(form);

  if (success) {
    message.value = selectedStaff.value ? 'Staff details updated.' : 'Staff account created.';
    if (!selectedStaff.value) {
      resetForm();
    }
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
    selectedStaffId.value = null;
    confirmingDelete.value = false;
    message.value = 'Staff account removed.';
  }
}

function togglePermission(permission: string) {
  form.permissions = form.permissions.includes(permission)
    ? form.permissions.filter((item) => item !== permission)
    : [...form.permissions, permission];
}
</script>

<template>
  <section class="grid gap-5 xl:grid-cols-[1fr_440px]">
    <div class="dashboard-card overflow-hidden !p-0">
      <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-4 sm:p-5">
        <div>
          <h2 class="section-title">Staff accounts</h2>
          <p class="mt-1 text-sm font-medium text-slate-500">Create staff, set permissions, and control access.</p>
        </div>
        <AppButton class="!min-h-10 !px-3" @click="selectedStaffId = null">
          <Plus class="h-4 w-4" />
          New
        </AppButton>
      </div>

      <div class="divide-y divide-slate-100">
        <article v-for="member in staffStore.staff" :key="member.id" class="grid gap-3 p-4 sm:grid-cols-[1fr_auto] sm:items-center sm:p-5">
          <div class="flex min-w-0 gap-3">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-black text-slate-500">
              {{ member.first_name.slice(0, 1) }}{{ member.last_name.slice(0, 1) }}
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="truncate font-black">{{ member.first_name }} {{ member.last_name }}</h3>
                <span class="rounded-full px-2 py-1 text-xs font-bold capitalize" :class="member.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                  {{ member.status }}
                </span>
              </div>
              <p class="mt-1 text-sm font-medium text-slate-500">{{ member.username ?? member.phone ?? 'No username' }} - {{ member.permissions.length }} permissions</p>
            </div>
          </div>
          <div class="flex gap-2">
            <AppButton class="!min-h-10 !px-3" variant="secondary" @click="selectStaff(member.id)">Edit</AppButton>
            <AppButton class="!min-h-10 !px-3" variant="secondary" @click="statusTarget = member">
              {{ member.status === 'active' ? 'Deactivate' : 'Activate' }}
            </AppButton>
          </div>
        </article>
        <p v-if="staffStore.staff.length === 0" class="p-8 text-center text-sm font-medium text-slate-500">No staff created yet.</p>
      </div>
    </div>

    <div class="grid gap-5">
      <form class="dashboard-card" @submit.prevent="submit">
        <h2 class="section-title">{{ selectedStaff ? 'Edit staff' : 'Create staff' }}</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <TextField v-model="form.first_name" label="First name" required />
          <TextField v-model="form.last_name" label="Last name" required />
          <TextField v-model="form.username" class="sm:col-span-2" label="Username" placeholder="staff_name" autocomplete="username" required />
          <label class="grid gap-2 text-sm font-bold text-slate-900 sm:col-span-2">
            Branch
            <select v-model="form.branch_id" class="field">
              <option value="">Use main branch</option>
              <option v-for="branch in branchStore.activeBranches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
            </select>
          </label>
          <TextField v-model="form.phone" label="Phone" required />
          <TextField v-model="form.email" label="Email" type="email" />
          <label class="grid gap-2 text-sm font-bold text-slate-900">
            Gender
            <select v-model="form.gender" class="field">
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </label>
          <label class="grid gap-2 text-sm font-bold text-slate-900 sm:col-span-2">
            Address
            <textarea v-model="form.address" class="field min-h-20 resize-none" />
          </label>
          <TextField v-if="!selectedStaff" v-model="form.password" class="sm:col-span-2" label="Password" type="password" placeholder="Leave empty to auto-generate from company name" />
        </div>

        <div class="mt-5">
          <h3 class="flex items-center gap-2 text-sm font-black">
            <ShieldCheck class="h-4 w-4 text-primary" />
            Permissions
          </h3>
          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <label v-for="permission in staffStore.permissions" :key="permission" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm font-semibold">
              <input class="h-4 w-4 rounded border-slate-300 text-primary" type="checkbox" :checked="form.permissions.includes(permission)" @change="togglePermission(permission)">
              <span>{{ permissionLabels[permission] ?? permission }}</span>
            </label>
          </div>
        </div>

        <p v-if="message" class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">{{ message }}</p>
        <p v-if="staffStore.error" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ staffStore.error }}</p>
        <AppButton class="mt-5 w-full" type="submit" :disabled="staffStore.loading">
          {{ staffStore.loading ? 'Saving...' : selectedStaff ? 'Update Staff' : 'Create Staff' }}
        </AppButton>
      </form>

      <div v-if="staffStore.temporaryPassword" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
        <p class="text-sm font-black text-amber-900">Temporary password</p>
        <p class="mt-2 rounded-lg bg-white px-3 py-2 font-mono text-sm text-amber-900">{{ staffStore.temporaryPassword }}</p>
        <p class="mt-2 text-sm font-semibold text-amber-900">This password keeps working until an admin resets it.</p>
        <button class="mt-3 text-sm font-black text-amber-900 underline" type="button" @click="staffStore.clearTemporaryPassword()">Hide password</button>
      </div>

      <div v-if="selectedStaff" class="dashboard-card">
        <h3 class="section-title">Password and removal</h3>
        <TextField v-model="resetPasswordValue" class="mt-4" label="New password" type="password" placeholder="Leave empty to auto-generate from company name" />
        <div class="mt-4 grid gap-2 sm:grid-cols-2">
          <AppButton variant="secondary" @click="confirmingPasswordReset = true">
            <KeyRound class="h-4 w-4" />
            Reset Password
          </AppButton>
          <AppButton variant="danger" @click="confirmingDelete = true">
            <Trash2 class="h-4 w-4" />
            Delete Staff
          </AppButton>
        </div>
      </div>
    </div>
    <ConfirmDialog
      :open="Boolean(statusTarget)"
      :title="`${statusTarget?.status === 'active' ? 'Deactivate' : 'Activate'} staff?`"
      :description="statusTarget ? `${statusTarget.first_name} ${statusTarget.last_name} will ${statusTarget.status === 'active' ? 'lose access until reactivated' : 'be able to sign in again'}.` : ''"
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
  </section>
</template>
