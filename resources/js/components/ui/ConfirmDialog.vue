<script setup lang="ts">
import { AlertTriangle, X } from 'lucide-vue-next';
import AppButton from './AppButton.vue';

withDefaults(defineProps<{
  open: boolean;
  title: string;
  description: string;
  confirmLabel?: string;
  cancelLabel?: string;
  tone?: 'danger' | 'warning' | 'primary';
  loading?: boolean;
}>(), {
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  tone: 'primary',
  loading: false,
});

const emit = defineEmits<{
  'update:open': [value: boolean];
  confirm: [];
}>();

function close() {
  emit('update:open', false);
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-50 grid place-items-end bg-gray-950/40 px-4 py-6 backdrop-blur-sm sm:place-items-center" role="presentation" @click.self="close">
        <section class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 text-gray-950 shadow-auth dark:border-white/[0.08] dark:bg-gray-900 dark:text-white" role="dialog" aria-modal="true" :aria-label="title">
          <div class="flex items-start justify-between gap-4">
            <div
              class="grid h-11 w-11 shrink-0 place-items-center rounded-xl"
              :class="{
                'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300': tone === 'danger',
                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300': tone === 'warning',
                'bg-blue-50 text-primary dark:bg-blue-500/10 dark:text-blue-300': tone === 'primary',
              }"
            >
              <AlertTriangle class="h-5 w-5" />
            </div>
            <button class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/[0.06] dark:hover:text-gray-200" type="button" aria-label="Close dialog" @click="close">
              <X class="h-5 w-5" />
            </button>
          </div>

          <h2 class="mt-4 text-lg font-semibold">{{ title }}</h2>
          <p class="mt-2 text-sm font-medium leading-6 text-gray-500 dark:text-gray-400">{{ description }}</p>

          <div class="mt-6 grid gap-2 sm:grid-cols-2">
            <AppButton variant="secondary" @click="close">{{ cancelLabel }}</AppButton>
            <AppButton :variant="tone === 'danger' ? 'danger' : tone === 'warning' ? 'warning' : 'primary'" :disabled="loading" @click="emit('confirm')">
              {{ loading ? 'Please wait...' : confirmLabel }}
            </AppButton>
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>
