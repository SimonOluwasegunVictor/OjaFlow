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
    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    :class="{
      'bg-primary text-white shadow-soft hover:bg-primary-700 focus:ring-primary/40': variant === 'primary',
      'border border-slate-200 bg-white text-slate-900 shadow-card hover:bg-slate-50 focus:ring-primary/25': variant === 'secondary',
      'bg-transparent text-slate-600 hover:bg-slate-100 focus:ring-primary/20': variant === 'ghost',
      'border border-rose-200 bg-white text-rose-700 hover:bg-rose-50 focus:ring-rose-200': variant === 'danger',
      'border border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 focus:ring-amber-200': variant === 'warning',
    }"
  >
    <slot />
  </button>
</template>
