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
  username: '',
  password: '',
});

async function submit() {
  const success = await auth.login({
    login: form.username,
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
        <div class="mb-4 grid h-12 w-12 place-items-center rounded-lg bg-blue-50 text-primary">
          <UserRoundCog class="h-6 w-6" />
        </div>
        <h2 class="text-2xl font-black text-slate-950">Staff login</h2>
        <p class="mt-2 text-sm font-medium leading-6 text-slate-500">Sign in with the username and password from your admin.</p>
      </div>

      <div class="mt-7 grid gap-5">
        <TextField v-model="form.username" label="Username" placeholder="staff_name" autocomplete="username" required />
        <TextField v-model="form.password" label="Password" type="password" placeholder="Enter your password" autocomplete="current-password" required />
      </div>

      <p v-if="auth.error" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ auth.error }}</p>

      <AppButton class="mt-5 w-full" type="submit" :disabled="auth.loading">
        {{ auth.loading ? 'Signing In...' : 'Sign In' }}
      </AppButton>

      <p class="mt-8 text-center text-sm font-medium text-slate-500">
        Business owner?
        <RouterLink class="font-black text-primary" :to="{ name: 'login' }">Use admin login</RouterLink>
      </p>
    </form>
  </AuthShell>
</template>
