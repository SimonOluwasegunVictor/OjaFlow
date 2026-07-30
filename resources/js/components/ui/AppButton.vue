<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'warning';
  type?: 'button' | 'submit' | 'reset';
  disabled?: boolean;
}>(), {
  variant: 'primary',
  type: 'button',
  disabled: false,
});
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-offset-gray-900"
    :class="{
      'bg-primary text-white shadow-soft hover:bg-primary-700 focus:ring-primary/40': variant === 'primary',
      'border border-gray-200 bg-white text-gray-700 shadow-card hover:bg-gray-50 focus:ring-primary/25 dark:border-white/[0.08] dark:bg-white/[0.04] dark:text-gray-300 dark:hover:bg-white/[0.08]': variant === 'secondary',
      'bg-transparent text-gray-600 hover:bg-gray-100 focus:ring-primary/20 dark:text-gray-400 dark:hover:bg-white/[0.06]': variant === 'ghost',
      'border border-rose-200 bg-white text-rose-700 hover:bg-rose-50 focus:ring-rose-200 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/15': variant === 'danger',
      'border border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 focus:ring-amber-200 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/15': variant === 'warning',
    }"
  >
    <slot />
  </button>
</template>
