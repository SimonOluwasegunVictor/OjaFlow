<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useStaffStore, type StaffForm } from '../stores/staff';
import type { User } from '../types';

const auth = useAuthStore();
const staffStore = useStaffStore();

const authMode = ref<'login' | 'register'>('login');
const activePanel = ref<'overview' | 'staff' | 'profile'>('overview');
const selectedStaffId = ref<string | null>(null);
const resetPasswordValue = ref('');
const successMessage = ref<string | null>(null);

const loginForm = reactive({
    login: '',
    password: '',
});

const registerForm = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    gender: 'male',
    business_name: '',
    business_email: '',
    business_phone: '',
    business_address: '',
    password: '',
});

const staffForm = reactive<StaffForm>({
    first_name: '',
    last_name: '',
    username: '',
    email: '',
    phone: '',
    gender: 'male',
    address: '',
    password: '',
    permissions: [],
});

const profileForm = reactive({
    first_name: '',
    last_name: '',
    username: '',
    email: '',
    phone: '',
    gender: 'male',
    address: '',
    current_password: '',
    new_password: '',
});

const selectedStaff = computed(() => staffStore.staff.find((staff) => staff.id === selectedStaffId.value) ?? null);
const activeStaffCount = computed(() => staffStore.staff.filter((staff) => staff.status === 'active').length);
const inactiveStaffCount = computed(() => staffStore.staff.filter((staff) => staff.status === 'inactive').length);
const canManageStaff = computed(() => auth.isAdmin);
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

onMounted(async () => {
    hydrateProfileForm();

    if (auth.isAuthenticated && auth.isAdmin) {
        await staffStore.bootstrap().catch(() => undefined);
    }
});

watch(() => auth.user, () => {
    hydrateProfileForm();
});

watch(selectedStaff, (staff) => {
    if (staff) {
        fillStaffForm(staff);
    } else {
        resetStaffForm();
    }
});

async function submitLogin() {
    successMessage.value = null;
    const success = await auth.login(loginForm);
    if (!success) {
        return;
    }

    await afterAuthenticated();
}

async function submitRegister() {
    successMessage.value = null;
    const success = await auth.register(registerForm);
    if (!success) {
        return;
    }

    await afterAuthenticated();
}

async function afterAuthenticated() {
    activePanel.value = 'overview';
    hydrateProfileForm();

    if (auth.isAdmin) {
        await staffStore.bootstrap().catch(() => undefined);
    }
}

async function submitProfile() {
    successMessage.value = null;
    const success = await auth.updateProfile({
        first_name: profileForm.first_name,
        last_name: profileForm.last_name,
        username: profileForm.username || null,
        email: profileForm.email || null,
        phone: profileForm.phone || null,
        gender: profileForm.gender,
        address: profileForm.address || null,
        current_password: profileForm.current_password || undefined,
        new_password: profileForm.new_password || undefined,
    });

    if (!success) {
        return;
    }

    profileForm.current_password = '';
    profileForm.new_password = '';
    successMessage.value = 'Profile updated successfully.';
}

async function submitStaff() {
    successMessage.value = null;

    if (selectedStaff.value) {
        const success = await staffStore.updateStaff(selectedStaff.value.id, staffForm);
        if (!success) {
            return;
        }

        successMessage.value = 'Staff details updated.';
        return;
    }

    const success = await staffStore.createStaff(staffForm);
    if (!success) {
        return;
    }

    successMessage.value = 'Staff account created.';
    resetStaffForm();
}

async function selectStaff(staffId: string) {
    selectedStaffId.value = staffId;
    await staffStore.fetchStaffMember(staffId).catch(() => undefined);
}

async function toggleStaffStatus(staff: User) {
    await staffStore.updateStatus(staff.id, staff.status === 'active' ? 'inactive' : 'active');
}

async function resetSelectedStaffPassword() {
    if (!selectedStaff.value) {
        return;
    }

    const success = await staffStore.resetPassword(selectedStaff.value.id, resetPasswordValue.value);
    if (!success) {
        return;
    }

    resetPasswordValue.value = '';
}

async function saveSelectedStaffPermissions() {
    if (!selectedStaff.value) {
        return;
    }

    const success = await staffStore.updatePermissions(selectedStaff.value.id, staffForm.permissions);
    if (!success) {
        return;
    }

    successMessage.value = 'Staff permissions updated.';
}

async function deleteSelectedStaff() {
    if (!selectedStaff.value) {
        return;
    }

    const success = await staffStore.deleteStaff(selectedStaff.value.id);
    if (!success) {
        return;
    }

    selectedStaffId.value = null;
    successMessage.value = 'Staff account removed.';
}

async function logout() {
    await auth.logout();
    selectedStaffId.value = null;
    activePanel.value = 'overview';
    staffStore.$reset();
}

function hydrateProfileForm() {
    if (!auth.user) {
        return;
    }

    profileForm.first_name = auth.user.first_name;
    profileForm.last_name = auth.user.last_name;
    profileForm.username = auth.user.username ?? '';
    profileForm.email = auth.user.email ?? '';
    profileForm.phone = auth.user.phone ?? '';
    profileForm.gender = auth.user.gender;
    profileForm.address = auth.user.address ?? '';
}

function fillStaffForm(staff: User) {
    staffForm.first_name = staff.first_name;
    staffForm.last_name = staff.last_name;
    staffForm.username = staff.username ?? '';
    staffForm.email = staff.email ?? '';
    staffForm.phone = staff.phone ?? '';
    staffForm.gender = staff.gender;
    staffForm.address = staff.address ?? '';
    staffForm.password = '';
    staffForm.permissions = [...(staff.permissions ?? [])];
}

function resetStaffForm() {
    staffForm.first_name = '';
    staffForm.last_name = '';
    staffForm.username = '';
    staffForm.email = '';
    staffForm.phone = '';
    staffForm.gender = 'male';
    staffForm.address = '';
    staffForm.password = '';
    staffForm.permissions = [];
    resetPasswordValue.value = '';
}

function togglePermission(permission: string) {
    staffForm.permissions = staffForm.permissions.includes(permission)
        ? staffForm.permissions.filter((item) => item !== permission)
        : [...staffForm.permissions, permission];
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 text-slate-950">
    <section v-if="!auth.isAuthenticated" class="grid min-h-screen lg:grid-cols-[0.92fr_1.08fr]">
      <div class="flex flex-col justify-between bg-slate-950 px-6 py-8 text-white sm:px-10 lg:px-14">
        <div>
          <p class="text-sm font-semibold uppercase tracking-wide text-emerald-300">OjaFlow</p>
          <h1 class="mt-16 max-w-xl text-4xl font-bold leading-tight sm:text-5xl">
            Sales, stock, receipts, and customer debt in one daily dashboard.
          </h1>
          <p class="mt-6 max-w-lg text-base leading-7 text-slate-300">
            Built for Nigerian shop owners, wholesalers, and staff who need a simple business book that stays accurate.
          </p>
        </div>

        <div class="mt-12 grid gap-3 text-sm text-slate-300 sm:grid-cols-2">
          <div class="rounded border border-white/10 bg-white/5 p-4">Admin creates business and staff</div>
          <div class="rounded border border-white/10 bg-white/5 p-4">Staff login with username</div>
          <div class="rounded border border-white/10 bg-white/5 p-4">Permissions control the dashboard</div>
          <div class="rounded border border-white/10 bg-white/5 p-4">Ready for sales and stock next</div>
        </div>
      </div>

      <div class="flex items-center justify-center px-5 py-10">
        <div class="w-full max-w-2xl">
          <div class="mb-6 inline-flex rounded border border-slate-200 bg-white p-1">
            <button
              class="rounded px-4 py-2 text-sm font-semibold"
              :class="authMode === 'login' ? 'bg-slate-950 text-white' : 'text-slate-600'"
              type="button"
              @click="authMode = 'login'"
            >
              Login
            </button>
            <button
              class="rounded px-4 py-2 text-sm font-semibold"
              :class="authMode === 'register' ? 'bg-slate-950 text-white' : 'text-slate-600'"
              type="button"
              @click="authMode = 'register'"
            >
              Register business
            </button>
          </div>

          <form v-if="authMode === 'login'" class="rounded border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submitLogin">
            <h2 class="text-2xl font-bold">Welcome back</h2>
            <p class="mt-1 text-sm text-slate-500">Admins use email. Staff use the username created by the admin.</p>

            <div class="mt-6 grid gap-4">
              <label class="grid gap-2 text-sm font-medium">
                Email or username
                <input v-model="loginForm.login" class="field" required autocomplete="username">
              </label>
              <label class="grid gap-2 text-sm font-medium">
                Password
                <input v-model="loginForm.password" type="password" class="field" required autocomplete="current-password">
              </label>
            </div>

            <p v-if="auth.error" class="mt-4 rounded bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ auth.error }}</p>

            <button class="mt-6 w-full rounded bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700" :disabled="auth.loading">
              {{ auth.loading ? 'Signing in...' : 'Sign in' }}
            </button>
          </form>

          <form v-else class="rounded border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submitRegister">
            <h2 class="text-2xl font-bold">Create owner account</h2>
            <p class="mt-1 text-sm text-slate-500">This creates the admin and the first business workspace.</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2 text-sm font-medium">First name<input v-model="registerForm.first_name" class="field" required></label>
              <label class="grid gap-2 text-sm font-medium">Last name<input v-model="registerForm.last_name" class="field" required></label>
              <label class="grid gap-2 text-sm font-medium">Email<input v-model="registerForm.email" type="email" class="field" required></label>
              <label class="grid gap-2 text-sm font-medium">Phone<input v-model="registerForm.phone" class="field"></label>
              <label class="grid gap-2 text-sm font-medium">
                Gender
                <select v-model="registerForm.gender" class="field">
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </label>
              <label class="grid gap-2 text-sm font-medium">Password<input v-model="registerForm.password" type="password" class="field" required></label>
              <label class="grid gap-2 text-sm font-medium sm:col-span-2">Business name<input v-model="registerForm.business_name" class="field" required></label>
              <label class="grid gap-2 text-sm font-medium">Business email<input v-model="registerForm.business_email" type="email" class="field"></label>
              <label class="grid gap-2 text-sm font-medium">Business phone<input v-model="registerForm.business_phone" class="field"></label>
              <label class="grid gap-2 text-sm font-medium sm:col-span-2">Business address<textarea v-model="registerForm.business_address" class="field min-h-20"></textarea></label>
            </div>

            <p v-if="auth.error" class="mt-4 rounded bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ auth.error }}</p>

            <button class="mt-6 w-full rounded bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700" :disabled="auth.loading">
              {{ auth.loading ? 'Creating account...' : 'Create business dashboard' }}
            </button>
          </form>
        </div>
      </div>
    </section>

    <section v-else class="min-h-screen">
      <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-xs font-bold uppercase tracking-wide text-emerald-600">OjaFlow Dashboard</p>
            <h1 class="text-xl font-bold">{{ auth.user?.business?.name ?? 'Business workspace' }}</h1>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button class="nav-button" :class="{ 'nav-button-active': activePanel === 'overview' }" @click="activePanel = 'overview'">Overview</button>
            <button v-if="canManageStaff" class="nav-button" :class="{ 'nav-button-active': activePanel === 'staff' }" @click="activePanel = 'staff'">Staff</button>
            <button class="nav-button" :class="{ 'nav-button-active': activePanel === 'profile' }" @click="activePanel = 'profile'">Profile</button>
            <button class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" @click="logout">Logout</button>
          </div>
        </div>
      </header>

      <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[260px_1fr]">
        <aside class="rounded border border-slate-200 bg-white p-4">
          <div class="flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded bg-emerald-100 text-sm font-bold text-emerald-700">
              {{ auth.user?.first_name.slice(0, 1) }}{{ auth.user?.last_name.slice(0, 1) }}
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-bold">{{ auth.fullName }}</p>
              <p class="text-xs capitalize text-slate-500">{{ auth.user?.role }} account</p>
            </div>
          </div>

          <dl class="mt-5 grid gap-3 text-sm">
            <div class="rounded bg-slate-50 p-3">
              <dt class="text-slate-500">Status</dt>
              <dd class="mt-1 font-bold capitalize">{{ auth.user?.status }}</dd>
            </div>
            <div class="rounded bg-slate-50 p-3">
              <dt class="text-slate-500">Staff</dt>
              <dd class="mt-1 font-bold">{{ staffStore.staff.length }}</dd>
            </div>
            <div class="rounded bg-slate-50 p-3">
              <dt class="text-slate-500">Allowed actions</dt>
              <dd class="mt-1 font-bold">{{ auth.user?.role === 'admin' ? 'All' : auth.user?.permissions.length }}</dd>
            </div>
          </dl>
        </aside>

        <div class="space-y-6">
          <p v-if="successMessage" class="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ successMessage }}
          </p>

          <section v-if="activePanel === 'overview'" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-3">
              <div class="metric"><span>Active staff</span><strong>{{ activeStaffCount }}</strong></div>
              <div class="metric"><span>Inactive staff</span><strong>{{ inactiveStaffCount }}</strong></div>
              <div class="metric"><span>Business status</span><strong>Active</strong></div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-5">
              <h2 class="text-lg font-bold">Foundation ready</h2>
              <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Auth, business scoping, admin staff management, and dashboard permissions are wired. Products and stock are the next backend module.
              </p>
              <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div class="rounded border border-slate-200 p-4">
                  <p class="text-sm font-bold">Staff login</p>
                  <p class="mt-1 text-sm text-slate-500">Staff sign in with the username and password created by the admin.</p>
                </div>
                <div class="rounded border border-slate-200 p-4">
                  <p class="text-sm font-bold">Permission-driven UI</p>
                  <p class="mt-1 text-sm text-slate-500">The staff response includes permissions for showing or hiding dashboard modules.</p>
                </div>
              </div>
            </div>
          </section>

          <section v-if="activePanel === 'staff' && canManageStaff" class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <div class="rounded border border-slate-200 bg-white">
              <div class="flex items-center justify-between border-b border-slate-200 p-4">
                <div>
                  <h2 class="font-bold">Staff accounts</h2>
                  <p class="text-sm text-slate-500">Create staff, set permissions, reset passwords, and control access.</p>
                </div>
                <button class="rounded bg-slate-950 px-3 py-2 text-sm font-bold text-white" @click="selectedStaffId = null">New staff</button>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                  <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                      <th class="px-4 py-3">Name</th>
                      <th class="px-4 py-3">Username</th>
                      <th class="px-4 py-3">Status</th>
                      <th class="px-4 py-3">Permissions</th>
                      <th class="px-4 py-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="staff in staffStore.staff" :key="staff.id">
                      <td class="px-4 py-3 font-semibold">{{ staff.first_name }} {{ staff.last_name }}</td>
                      <td class="px-4 py-3 text-slate-600">{{ staff.username }}</td>
                      <td class="px-4 py-3">
                        <span class="rounded px-2 py-1 text-xs font-bold capitalize" :class="staff.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                          {{ staff.status }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-slate-600">{{ staff.permissions.length }}</td>
                      <td class="px-4 py-3">
                        <div class="flex gap-2">
                          <button class="table-button" @click="selectStaff(staff.id)">Edit</button>
                          <button class="table-button" @click="toggleStaffStatus(staff)">{{ staff.status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="staffStore.staff.length === 0">
                      <td class="px-4 py-8 text-center text-slate-500" colspan="5">No staff created yet.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="space-y-6">
              <form class="rounded border border-slate-200 bg-white p-5" @submit.prevent="submitStaff">
                <h2 class="text-lg font-bold">{{ selectedStaff ? 'Edit staff' : 'Create staff' }}</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                  <label class="grid gap-1 text-sm font-medium">First name<input v-model="staffForm.first_name" class="field" required></label>
                  <label class="grid gap-1 text-sm font-medium">Last name<input v-model="staffForm.last_name" class="field" required></label>
                  <label class="grid gap-1 text-sm font-medium">Username<input v-model="staffForm.username" class="field" required></label>
                  <label class="grid gap-1 text-sm font-medium">Phone<input v-model="staffForm.phone" class="field"></label>
                  <label class="grid gap-1 text-sm font-medium">Email<input v-model="staffForm.email" type="email" class="field"></label>
                  <label class="grid gap-1 text-sm font-medium">
                    Gender
                    <select v-model="staffForm.gender" class="field">
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                      <option value="other">Other</option>
                    </select>
                  </label>
                  <label class="grid gap-1 text-sm font-medium sm:col-span-2">Address<textarea v-model="staffForm.address" class="field min-h-16"></textarea></label>
                  <label v-if="!selectedStaff" class="grid gap-1 text-sm font-medium sm:col-span-2">Password<input v-model="staffForm.password" type="password" class="field" placeholder="Leave empty to auto-generate"></label>
                </div>

                <div class="mt-5">
                  <p class="text-sm font-bold">Permissions</p>
                  <div class="mt-3 grid gap-2">
                    <label v-for="permission in staffStore.permissions" :key="permission" class="flex items-center gap-3 rounded border border-slate-200 p-3 text-sm">
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-emerald-600"
                        :checked="staffForm.permissions.includes(permission)"
                        @change="togglePermission(permission)"
                      >
                      <span>{{ permissionLabels[permission] ?? permission }}</span>
                    </label>
                  </div>
                </div>

                <p v-if="staffStore.error" class="mt-4 rounded bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ staffStore.error }}</p>

                <button class="mt-5 w-full rounded bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700" :disabled="staffStore.loading">
                  {{ staffStore.loading ? 'Saving...' : selectedStaff ? 'Update staff' : 'Create staff' }}
                </button>
              </form>

              <div v-if="staffStore.temporaryPassword" class="rounded border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-bold text-amber-900">Temporary password</p>
                <p class="mt-2 rounded bg-white px-3 py-2 font-mono text-sm text-amber-900">{{ staffStore.temporaryPassword }}</p>
                <button class="mt-3 text-sm font-bold text-amber-900 underline" @click="staffStore.clearTemporaryPassword()">Hide password</button>
              </div>

              <div v-if="selectedStaff" class="rounded border border-slate-200 bg-white p-5">
                <h3 class="font-bold">Password and removal</h3>
                <label class="mt-4 grid gap-2 text-sm font-medium">
                  New password
                  <input v-model="resetPasswordValue" type="password" class="field" placeholder="Leave empty to auto-generate">
                </label>
                <div class="mt-4 flex flex-wrap gap-2">
                  <button class="rounded bg-slate-950 px-3 py-2 text-sm font-bold text-white" @click="resetSelectedStaffPassword">Reset password</button>
                  <button class="rounded border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700" @click="saveSelectedStaffPermissions">Save permissions only</button>
                  <button class="rounded border border-rose-200 px-3 py-2 text-sm font-bold text-rose-700" @click="deleteSelectedStaff">Delete staff</button>
                </div>
              </div>
            </div>
          </section>

          <section v-if="activePanel === 'profile'" class="rounded border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold">Profile</h2>
            <p class="mt-1 text-sm text-slate-500">Update your personal account details and password.</p>

            <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="submitProfile">
              <label class="grid gap-2 text-sm font-medium">First name<input v-model="profileForm.first_name" class="field"></label>
              <label class="grid gap-2 text-sm font-medium">Last name<input v-model="profileForm.last_name" class="field"></label>
              <label class="grid gap-2 text-sm font-medium">Username<input v-model="profileForm.username" class="field"></label>
              <label class="grid gap-2 text-sm font-medium">Email<input v-model="profileForm.email" type="email" class="field"></label>
              <label class="grid gap-2 text-sm font-medium">Phone<input v-model="profileForm.phone" class="field"></label>
              <label class="grid gap-2 text-sm font-medium">
                Gender
                <select v-model="profileForm.gender" class="field">
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </label>
              <label class="grid gap-2 text-sm font-medium sm:col-span-2">Address<textarea v-model="profileForm.address" class="field min-h-20"></textarea></label>
              <label class="grid gap-2 text-sm font-medium">Current password<input v-model="profileForm.current_password" type="password" class="field"></label>
              <label class="grid gap-2 text-sm font-medium">New password<input v-model="profileForm.new_password" type="password" class="field"></label>

              <p v-if="auth.error" class="rounded bg-rose-50 px-3 py-2 text-sm text-rose-700 sm:col-span-2">{{ auth.error }}</p>
              <button class="rounded bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 sm:col-span-2" :disabled="auth.loading">
                {{ auth.loading ? 'Saving...' : 'Save profile' }}
              </button>
            </form>
          </section>
        </div>
      </div>
    </section>
  </main>
</template>
