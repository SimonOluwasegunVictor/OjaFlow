<script setup lang="ts">
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowLeft } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';
import AuthShell from '../../components/auth/AuthShell.vue';
import AppButton from '../../components/ui/AppButton.vue';
import TextField from '../../components/ui/TextField.vue';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({
  full_name: '',
  phone: '',
  email: '',
  user_address: '',
  branch_address: '',
  password: '',
  confirm_password: '',
  business_name: '',
});

async function submit() {
  const [firstName, ...lastNameParts] = form.full_name.trim().split(/\s+/);
  const success = await auth.register({
    first_name: firstName || form.full_name,
    last_name: lastNameParts.join(' ') || 'Owner',
    email: form.email,
    phone: form.phone,
    gender: 'other',
    address: form.user_address,
    business_name: form.business_name || 'Alabi Building Materials',
    business_email: form.email,
    business_phone: form.phone,
    business_address: form.branch_address,
    password: form.password,
  });

  if (success) {
    await router.push({ name: 'dashboard' });
  }
}
</script>

<template>
  <AuthShell>
    <template #back>
      <RouterLink :to="{ name: 'login' }" class="inline-flex items-center gap-2 self-start text-sm font-bold text-blue-100 hover:text-white">
        <ArrowLeft class="h-4 w-4" />
        Back to Login
      </RouterLink>
    </template>

    <form class="auth-card" @submit.prevent="submit">
      <div>
        <h2 class="text-2xl font-semibold text-gray-950 dark:text-white">Create your account</h2>
        <p class="mt-2 text-sm font-medium text-gray-500 dark:text-gray-400">Get started with TradeNest for free</p>
      </div>

      <div class="mt-7 grid gap-5">
        <TextField v-model="form.full_name" label="Full Name" placeholder="e.g. Alabi Oluwaseun" required autocomplete="name" />
        <TextField v-model="form.phone" label="Phone Number" placeholder="080 1234 5678" required autocomplete="tel" />
        <TextField v-model="form.email" label="Email Address (optional)" type="email" placeholder="you@example.com" autocomplete="email" />
        <TextField v-model="form.business_name" label="Business Name" placeholder="Alabi Building Materials" required />
        <label class="grid gap-2 text-sm font-semibold text-gray-900 dark:text-gray-200">
          User Address
          <textarea v-model="form.user_address" class="field min-h-20 resize-none py-3" placeholder="Your personal address" />
        </label>
        <label class="grid gap-2 text-sm font-semibold text-gray-900 dark:text-gray-200">
          Branch Address
          <textarea v-model="form.branch_address" class="field min-h-20 resize-none py-3" placeholder="Main shop or branch address" />
        </label>
        <TextField v-model="form.password" label="Password" type="password" placeholder="Create a strong password" required autocomplete="new-password" />
        <TextField v-model="form.confirm_password" label="Confirm Password" type="password" placeholder="Repeat your password" required autocomplete="new-password" />
      </div>

      <p v-if="form.password && form.confirm_password && form.password !== form.confirm_password" class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
        Passwords do not match yet.
      </p>
      <p v-if="auth.error" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ auth.error }}</p>

      <AppButton class="mt-5 w-full" type="submit" :disabled="auth.loading || form.password !== form.confirm_password">
        {{ auth.loading ? 'Creating Account...' : 'Create Account' }}
      </AppButton>
      <p class="mt-5 text-center text-xs font-medium text-gray-400 dark:text-gray-500">By signing up, you agree to our Terms of Service</p>
    </form>
  </AuthShell>
</template>
