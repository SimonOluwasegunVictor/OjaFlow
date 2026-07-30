<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { Construction } from 'lucide-vue-next';

const route = useRoute();
const title = computed(() => route.meta.title?.toString() ?? 'Module');
</script>

<template>
  <section class="dashboard-card mx-auto max-w-2xl text-center">
    <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-blue-50 text-primary dark:bg-blue-500/10 dark:text-blue-300">
      <Construction class="h-7 w-7" />
    </div>
    <h2 class="mt-4 text-xl font-semibold text-gray-950 dark:text-white">{{ title }}</h2>
    <p class="mt-2 text-sm font-medium leading-6 text-gray-500 dark:text-gray-400">
      This section is ready in the navigation and layout, so the sales, stock, customer, and debt modules can plug into the same dashboard structure.
    </p>
  </section>
</template>
