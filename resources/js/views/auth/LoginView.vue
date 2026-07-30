<script setup lang="ts">
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { UserRoundCog } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';
import AuthShell from '../../components/auth/AuthShell.vue';
import AppButton from '../../components/ui/AppButton.vue';
import TextField from '../../components/ui/TextField.vue';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({
  login: '',
  password: '',
});

async function submit() {
  const success = await auth.login({
    login: form.login,
    password: form.password,
  });

  if (success) {
    await router.push({ name: 'dashboard' });
  }
}
</script>

<template>
  <AuthShell>
    <form class="auth-card" @submit.prevent="submit">
      <div>
        <h2 class="text-2xl font-semibold text-gray-950 dark:text-white">Welcome back</h2>
      </div>

      <div class="mt-7 grid gap-5">
        <TextField v-model="form.login" label="Phone Number" placeholder="080 1234 5678" autocomplete="tel" required />
        <TextField v-model="form.password" label="Password" type="password" placeholder="Enter your password" autocomplete="current-password" required />
      </div>

      <p v-if="auth.error" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ auth.error }}</p>

      <AppButton class="mt-5 w-full" type="submit" :disabled="auth.loading">
        {{ auth.loading ? 'Signing In...' : 'Sign In' }}
      </AppButton>

      <div class="mt-10 grid gap-4">
        <RouterLink
          :to="{ name: 'staff-login' }"
          class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 shadow-card transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/25 focus:ring-offset-2 focus:ring-offset-white dark:border-white/[0.08] dark:bg-white/[0.04] dark:text-gray-300 dark:hover:bg-white/[0.08] dark:focus:ring-offset-gray-900"
        >
          <UserRoundCog class="h-4 w-4 text-gray-500 dark:text-gray-400" />
          Staff Login
        </RouterLink>
        <p class="text-center text-sm font-medium text-gray-500 dark:text-gray-400">
          New here?
          <RouterLink class="font-semibold text-primary dark:text-blue-300" :to="{ name: 'register' }">Create an account</RouterLink>
        </p>
      </div>
    </form>
  </AuthShell>
</template>
