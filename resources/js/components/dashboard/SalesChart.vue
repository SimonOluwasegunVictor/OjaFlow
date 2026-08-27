<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ days: Array<{ label: string; value: number }> }>();
const maxValue = computed(() => Math.max(...props.days.map((day) => day.value), 1));
</script>

<template>
  <section class="dashboard-card">
    <h2 class="section-title">Sales This Week</h2>
    <div class="mt-6 flex h-40 items-end justify-between gap-4 px-3 sm:h-44 sm:px-8">
      <div v-for="day in props.days" :key="day.label" class="grid flex-1 justify-items-center gap-2">
        <div class="w-full max-w-5 rounded-t-md bg-primary" :style="{ height: `${Math.max((day.value / maxValue) * 100, day.value ? 5 : 0)}%` }" />
        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">{{ day.label }}</span>
      </div>
    </div>
  </section>
</template>
