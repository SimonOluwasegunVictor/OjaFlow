<script setup lang="ts">
import { Store } from 'lucide-vue-next';

defineProps<{
  businessName?: string | null;
}>();
</script>

<template>
  <div class="flex min-w-0 items-center gap-3">
    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary text-white shadow-soft">
      <Store class="h-5 w-5" />
    </div>
    <div class="min-w-0">
      <p class="truncate text-sm font-black text-slate-950">OjaFlow</p>
      <p class="truncate text-xs font-medium text-slate-400">{{ businessName ?? 'Business workspace' }}</p>
    </div>
  </div>
</template>
