<script setup lang="ts">
import { Bell, Plus, SunMoon } from 'lucide-vue-next';
import { useThemeStore } from '../../stores/theme';
import BrandMark from './BrandMark.vue';

defineProps<{
  title: string;
  businessName?: string | null;
  canRecordSale?: boolean;
}>();

const theme = useThemeStore();
</script>

<template>
  <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-white/[0.07] dark:bg-gray-900/85 lg:hidden">
    <div class="flex items-center justify-between gap-3 px-4 py-3">
      <BrandMark :business-name="businessName" />
      <div class="flex items-center gap-1">
        <button class="grid h-10 w-10 place-items-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.06]" type="button" aria-label="Toggle dark mode" @click="theme.toggleDarkMode()">
          <SunMoon class="h-5 w-5" />
        </button>
        <button class="relative grid h-10 w-10 place-items-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.06]" type="button" aria-label="Notifications">
          <Bell class="h-5 w-5" />
          <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500" />
        </button>
      </div>
    </div>
    <div class="flex items-center justify-between gap-3 px-4 pb-3">
      <h1 class="text-lg font-semibold text-gray-950 dark:text-white">{{ title }}</h1>
      <RouterLink
        v-if="canRecordSale"
        :to="{ name: 'record-sale' }"
        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-3 text-sm font-semibold text-white shadow-soft transition hover:bg-primary-700"
      >
        <Plus class="h-4 w-4" />
        Sale
      </RouterLink>
    </div>
  </header>
</template>
