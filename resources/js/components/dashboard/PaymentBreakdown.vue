<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ items: Array<{ label: string; value: number }> }>();
const total = computed(() => props.items.reduce((sum, item) => sum + item.value, 0));
const colors = ['bg-primary', 'bg-violet-600', 'bg-emerald-600', 'bg-amber-600', 'bg-rose-600'];
const chartColors = ['#2563eb', '#7c3aed', '#059669', '#d97706', '#e11d48'];
const percentages = computed(() => props.items.map((item) => Math.round((item.value / (total.value || 1)) * 100)));
const chartGradient = computed(() => {
  let start = 0;
  const stops = props.items.map((item, index) => {
    const end = start + ((item.value / (total.value || 1)) * 100);
    const stop = `${chartColors[index]} ${start}% ${end}%`;
    start = end;
    return stop;
  });

  return `conic-gradient(${stops.join(',') || '#e5e7eb 0% 100%'})`;
});
</script>

<template>
  <section class="dashboard-card">
    <h2 class="section-title">Payment Breakdown</h2>
    <div class="mt-6 flex items-center gap-7">
      <div class="h-32 w-32 shrink-0 rounded-full p-5" :style="{ background: chartGradient }">
        <div class="h-full w-full rounded-full bg-white dark:bg-gray-800" />
      </div>
      <div class="grid gap-3 text-sm">
        <div v-for="(item, index) in props.items" :key="item.label" class="grid grid-cols-[auto_1fr_auto] items-center gap-3">
          <span class="h-3 w-3 rounded-full" :class="colors[index]" />
          <span class="font-medium text-gray-600 dark:text-gray-400">{{ item.label }}</span>
          <strong class="font-semibold text-gray-950 dark:text-white">{{ percentages[index] }}%</strong>
        </div>
      </div>
    </div>
  </section>
</template>
