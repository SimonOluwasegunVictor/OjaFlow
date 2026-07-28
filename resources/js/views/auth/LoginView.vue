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
        <h2 class="text-2xl font-black text-slate-950">Welcome back</h2>
      </div>

      <div class="mt-7 grid gap-5">
        <TextField v-model="form.login" label="Phone Number" placeholder="080 1234 5678" autocomplete="tel" required />
        <TextField v-model="form.password" label="Password" type="password" placeholder="Enter your password" autocomplete="current-password" required />
      </div>

      <p v-if="auth.error" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ auth.error }}</p>

      <AppButton class="mt-5 w-full" type="submit" :disabled="auth.loading">
        {{ auth.loading ? 'Signing In...' : 'Sign In' }}
      </AppButton>

      <div class="mt-10 grid gap-4">
        <RouterLink
          :to="{ name: 'staff-login' }"
          class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900 shadow-card transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-primary/25 focus:ring-offset-2"
        >
          <UserRoundCog class="h-4 w-4 text-slate-500" />
          Staff Login
        </RouterLink>
        <p class="text-center text-sm font-medium text-slate-500">
          New here?
          <RouterLink class="font-black text-primary" :to="{ name: 'register' }">Create an account</RouterLink>
        </p>
      </div>
    </form>
  </AuthShell>
</template>
