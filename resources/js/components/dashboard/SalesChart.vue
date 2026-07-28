<script setup lang="ts">
const days = [
  { label: 'Mon', value: 24 },
  { label: 'Tue', value: 44 },
  { label: 'Wed', value: 32 },
  { label: 'Thu', value: 50 },
  { label: 'Fri', value: 70 },
  { label: 'Sat', value: 96 },
  { label: 'Sun', value: 22 },
];
</script>

<template>
  <section class="dashboard-card">
    <h2 class="section-title">Sales This Week</h2>
    <div class="mt-6 flex h-40 items-end justify-between gap-4 px-3 sm:h-44 sm:px-8">
      <div v-for="day in days" :key="day.label" class="grid flex-1 justify-items-center gap-2">
        <div class="w-full max-w-5 rounded-t-md bg-primary" :style="{ height: `${day.value}%` }" />
        <span class="text-xs font-semibold text-slate-400">{{ day.label }}</span>
      </div>
    </div>
  </section>
</template>
