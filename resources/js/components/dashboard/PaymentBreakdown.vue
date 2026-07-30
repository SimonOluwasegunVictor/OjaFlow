<script setup lang="ts">
const items = [
  { label: 'Cash', value: '52%', color: 'bg-primary' },
  { label: 'Transfer', value: '28%', color: 'bg-violet-600' },
  { label: 'POS', value: '14%', color: 'bg-emerald-600' },
  { label: 'Credit', value: '6%', color: 'bg-amber-600' },
];
</script>

<template>
  <section class="dashboard-card">
    <h2 class="section-title">Payment Breakdown</h2>
    <div class="mt-6 flex items-center gap-7">
      <div class="h-32 w-32 shrink-0 rounded-full bg-[conic-gradient(#2563eb_0_52%,#7c3aed_52%_80%,#059669_80%_94%,#d97706_94%_100%)] p-5">
        <div class="h-full w-full rounded-full bg-white dark:bg-gray-800" />
      </div>
      <div class="grid gap-3 text-sm">
        <div v-for="item in items" :key="item.label" class="grid grid-cols-[auto_1fr_auto] items-center gap-3">
          <span class="h-3 w-3 rounded-full" :class="item.color" />
          <span class="font-medium text-gray-600 dark:text-gray-400">{{ item.label }}</span>
          <strong class="font-semibold text-gray-950 dark:text-white">{{ item.value }}</strong>
        </div>
      </div>
    </div>
  </section>
</template>
