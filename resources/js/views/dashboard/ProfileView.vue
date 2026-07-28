<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { useAuthStore } from '../../stores/auth';
import AppButton from '../../components/ui/AppButton.vue';
import TextField from '../../components/ui/TextField.vue';

const auth = useAuthStore();
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

watch(() => auth.user, hydrate, { immediate: true });

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
    form.current_password = '';
    form.new_password = '';
    message.value = 'Profile updated successfully.';
  }
}
</script>

<template>
  <section class="mx-auto max-w-3xl">
    <form class="dashboard-card" @submit.prevent="submit">
      <h2 class="section-title">Profile</h2>
      <p class="mt-1 text-sm font-medium text-slate-500">{{ auth.isAdmin ? 'Update your personal account details and password.' : 'Update your personal account details.' }}</p>

      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <TextField v-model="form.first_name" label="First name" />
        <TextField v-model="form.last_name" label="Last name" />
        <TextField v-model="form.email" label="Email" type="email" />
        <TextField v-model="form.phone" label="Phone" />
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
          <textarea v-model="form.address" class="field min-h-24 resize-none" />
        </label>
        <TextField v-if="auth.isAdmin" v-model="form.current_password" label="Current password" type="password" />
        <TextField v-if="auth.isAdmin" v-model="form.new_password" label="New password" type="password" />
      </div>

      <p v-if="message" class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">{{ message }}</p>
      <p v-if="auth.error" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ auth.error }}</p>
      <AppButton class="mt-5 w-full" type="submit" :disabled="auth.loading">
        {{ auth.loading ? 'Saving...' : 'Save Profile' }}
      </AppButton>
    </form>
  </section>
</template>
