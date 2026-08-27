<script setup lang="ts">
import { onMounted, reactive, ref, watch, type Component } from 'vue';
import { Building2, Monitor, Moon, Settings, ShieldCheck, Sun, UserRound } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';
import { useThemeStore, type ThemeMode } from '../../stores/theme';
import AppButton from '../../components/ui/AppButton.vue';
import PageHeader from '../../components/dashboard/Pageheader.vue';
import TextField from '../../components/ui/TextField.vue';
import { useBusinessStore, type BusinessSettingsPayload } from '../../stores/business';

const auth = useAuthStore();
const theme = useThemeStore();
const businessStore = useBusinessStore();
const message = ref('');
const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  gender: 'other',
  address: '',
  current_password: '',
  new_password: '',
});
const businessForm = reactive<Omit<BusinessSettingsPayload, 'email' | 'phone' | 'address' | 'receipt_footer'> & {
  email: string;
  phone: string;
  address: string;
  receipt_footer: string;
}>({
  name: '', email: '', phone: '', address: '', receipt_footer: '', receipt_size: '80mm',
  settings: { sms_enabled: false, whatsapp_enabled: false, low_stock_alerts: true },
});

const themeOptions: { label: string; value: ThemeMode; icon: Component }[] = [
  { label: 'Light', value: 'light', icon: Sun },
  { label: 'Dark', value: 'dark', icon: Moon },
  { label: 'System', value: 'system', icon: Monitor },
];

watch(() => auth.user, hydrate, { immediate: true });
onMounted(async () => {
  if (auth.isAdmin) {
    await businessStore.fetch();
    hydrate();
  }
});

function hydrate() {
  if (!auth.user) {
    return;
  }

  form.first_name = auth.user.first_name;
  form.last_name = auth.user.last_name;
  form.email = auth.user.email ?? '';
  form.phone = auth.user.phone ?? '';
  form.gender = auth.user.gender;
  form.address = auth.user.address ?? '';
  const business = businessStore.business ?? auth.user.business;
  businessForm.name = business?.name ?? '';
  businessForm.email = business?.email ?? null;
  businessForm.phone = business?.phone ?? null;
  businessForm.address = business?.address ?? null;
  businessForm.receipt_footer = business?.receipt_footer ?? null;
  businessForm.receipt_size = business?.receipt_size ?? '80mm';
  businessForm.settings = { ...businessForm.settings, ...(business?.settings ?? {}) };
}

async function submit() {
  message.value = '';
  const payload = {
    first_name: form.first_name,
    last_name: form.last_name,
    email: form.email || null,
    phone: form.phone || null,
    gender: form.gender,
    address: form.address || null,
  };

  const success = await auth.updateProfile(auth.isAdmin ? {
    ...payload,
    current_password: form.current_password || undefined,
    new_password: form.new_password || undefined,
  } : payload);

  if (success) {
    if (auth.isAdmin) {
      const businessSaved = await businessStore.update({
        ...businessForm,
        email: businessForm.email || null,
        phone: businessForm.phone || null,
        address: businessForm.address || null,
        receipt_footer: businessForm.receipt_footer || null,
      });

      if (!businessSaved) return;
    }

    form.current_password = '';
    form.new_password = '';
    message.value = 'Settings saved.';
  }
}
</script>

<template>
  <div class="flex h-full flex-col">
    <PageHeader
      :icon="Settings"
      title="Settings"
      description="Account, workspace, and display preferences."
      :show-search="false"
      :show-action="false"
    />

    <div class="flex-1 overflow-auto px-4 py-5 sm:px-6">
      <div class="mx-auto grid max-w-5xl gap-4 lg:grid-cols-[1fr_360px]">
        <form class="dashboard-card order-2 lg:order-1" @submit.prevent="submit">
          <div class="flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-white/[0.06]">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-primary dark:bg-blue-500/10 dark:text-blue-300">
              <UserRound class="h-5 w-5" />
            </div>
            <div>
              <h2 class="section-title">Profile</h2>
              <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ auth.fullName }}</p>
            </div>
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <TextField v-model="form.first_name" label="First name" />
            <TextField v-model="form.last_name" label="Last name" />
            <TextField v-model="form.email" label="Email" type="email" />
            <TextField v-model="form.phone" label="Phone" />
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
              <textarea v-model="form.address" class="field min-h-24 resize-none py-3" />
            </label>
            <TextField v-if="auth.isAdmin" v-model="form.current_password" label="Current password" type="password" autocomplete="current-password" />
            <TextField v-if="auth.isAdmin" v-model="form.new_password" label="New password" type="password" autocomplete="new-password" />
          </div>

          <p v-if="message" class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ message }}</p>
          <p v-if="auth.error" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ auth.error }}</p>
          <AppButton class="mt-5 w-full" type="submit" :disabled="auth.loading">
            {{ auth.loading ? 'Saving...' : 'Save Settings' }}
          </AppButton>
        </form>

        <div class="order-1 grid gap-4 lg:order-2">
          <section v-if="auth.isAdmin" class="dashboard-card">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">
                <Sun class="h-5 w-5" />
              </div>
              <div>
                <h2 class="section-title">Appearance</h2>
                <p class="mt-1 text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ theme.mode }} mode</p>
              </div>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-gray-100 p-1 dark:bg-white/[0.05]">
              <button
                v-for="option in themeOptions"
                :key="option.value"
                type="button"
                :class="[
                  'flex min-h-12 flex-col items-center justify-center gap-1 rounded-lg text-xs font-semibold transition',
                  theme.mode === option.value
                    ? 'bg-white text-primary shadow-sm dark:bg-gray-800 dark:text-blue-300'
                    : 'text-gray-500 hover:bg-white/60 dark:text-gray-400 dark:hover:bg-white/[0.06]',
                ]"
                @click="theme.setMode(option.value)"
              >
                <component :is="option.icon" class="h-4 w-4" />
                <span>{{ option.label }}</span>
              </button>
            </div>
          </section>

          <section class="dashboard-card">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-primary dark:bg-blue-500/10 dark:text-blue-300">
                <Building2 class="h-5 w-5" />
              </div>
              <div class="min-w-0">
                <h2 class="section-title">Business</h2>
                <p class="mt-1 truncate text-xs font-medium text-gray-500 dark:text-gray-400">{{ auth.user?.business?.name ?? 'Business workspace' }}</p>
              </div>
            </div>
            <div class="mt-4 grid gap-3">
              <TextField v-model="businessForm.name" label="Business name" />
              <TextField v-model="businessForm.email" label="Business email" type="email" />
              <TextField v-model="businessForm.phone" label="Business phone" />
              <TextField v-model="businessForm.address" label="Business address" />
              <label class="grid gap-2 text-sm font-semibold text-gray-900 dark:text-gray-200">
                Receipt size
                <select v-model="businessForm.receipt_size" class="field">
                  <option value="58mm">58mm</option>
                  <option value="80mm">80mm</option>
                  <option value="a4">A4</option>
                </select>
              </label>
              <label class="grid gap-2 text-sm font-semibold text-gray-900 dark:text-gray-200">
                Receipt footer
                <textarea v-model="businessForm.receipt_footer" class="field min-h-20 resize-none py-3" placeholder="Thank you for your patronage" />
              </label>
              <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                <input v-model="businessForm.settings.low_stock_alerts" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary" />
                Show low-stock alerts
              </label>
              <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                <input v-model="businessForm.settings.sms_enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary" />
                Enable SMS reminders
              </label>
              <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                <input v-model="businessForm.settings.whatsapp_enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary" />
                Enable WhatsApp sharing
              </label>
            </div>
          </section>

          <section class="dashboard-card">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                <ShieldCheck class="h-5 w-5" />
              </div>
              <div>
                <h2 class="section-title">Access</h2>
                <p class="mt-1 text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ auth.user?.role }} / {{ auth.user?.status }}</p>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>
