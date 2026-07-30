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
      <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">OjaFlow</p>
      <p class="truncate text-xs font-medium text-gray-400 dark:text-gray-500">{{ businessName ?? 'Business workspace' }}</p>
    </div>
  </div>
</template>
