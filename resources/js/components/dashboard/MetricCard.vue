<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(defineProps<{
  label: string;
  value: string | number;
  meta?: string;
  tone?: 'blue' | 'green' | 'orange' | 'purple' | 'cyan' | 'red';
  icon: Component;
}>(), {
  meta: '',
  tone: 'blue',
});
</script>

<template>
  <article class="metric-card">
    <div class="flex items-start justify-between gap-3">
      <div
        class="grid h-11 w-11 place-items-center rounded-xl text-white"
        :class="{
          'bg-primary': tone === 'blue',
          'bg-emerald-600': tone === 'green',
          'bg-amber-500': tone === 'orange',
          'bg-violet-600': tone === 'purple',
          'bg-cyan-600': tone === 'cyan',
          'bg-rose-600': tone === 'red',
        }"
      >
        <component :is="icon" class="h-5 w-5" />
      </div>
      <span v-if="meta" class="pt-2 text-xs font-semibold text-slate-400">{{ meta }}</span>
    </div>
    <p class="mt-4 text-2xl font-black tracking-normal text-slate-950">{{ value }}</p>
    <p class="mt-1 text-xs font-medium text-slate-500">{{ label }}</p>
  </article>
</template>
