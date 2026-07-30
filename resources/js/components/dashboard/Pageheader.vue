<script setup lang="ts">
import type { Component } from 'vue';
import { Search } from 'lucide-vue-next';

interface Props {
  icon: Component;
  title: string;
  description?: string;
  count?: number;
  search?: string;
  searchPlaceholder?: string;
  showSearch?: boolean;
  actionLabel?: string;
  actionIcon?: Component;
  showAction?: boolean;
  actionDisabled?: boolean;
}

withDefaults(defineProps<Props>(), {
  description: '',
  count: undefined,
  search: '',
  searchPlaceholder: 'Search…',
  showSearch: true,
  actionLabel: '',
  actionIcon: undefined,
  showAction: true,
  actionDisabled: false,
});

const emit = defineEmits<{
  'update:search': [value: string];
  action: [];
}>();

function onSearchInput(event: Event) {
  emit('update:search', (event.target as HTMLInputElement).value);
}
</script>

<template>
  <div class="sticky top-0 z-20 border-b border-gray-200 bg-white/80 px-4 py-4 backdrop-blur-md dark:border-gray-700 dark:bg-gray-900/80 sm:px-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <div class="flex min-w-0 flex-1 items-center gap-3">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 shadow-sm">
          <component :is="icon" class="h-4 w-4 text-white" />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <h1 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ title }}</h1>
            <span
              v-if="count !== undefined"
              class="inline-flex h-5 items-center rounded-full bg-gray-100 px-2 text-xs font-medium text-gray-500 dark:bg-white/10 dark:text-gray-400"
            >
              {{ count }}
            </span>
          </div>
          <p v-if="description" class="truncate text-xs text-gray-500 dark:text-gray-500">{{ description }}</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <div v-if="showSearch" class="relative flex-1 sm:flex-none">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
          <input
            :value="search"
            type="text"
            :placeholder="searchPlaceholder"
            class="w-full rounded-lg border border-transparent bg-gray-100 py-2 pl-8 pr-3 text-xs text-gray-700 placeholder-gray-400 transition-all focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:bg-white/[0.06] dark:text-gray-300 dark:focus:bg-white/[0.08] sm:w-48"
            @input="onSearchInput"
          />
        </div>
        <button
          v-if="showAction"
          type="button"
          :disabled="actionDisabled"
          class="flex shrink-0 cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
          @click="emit('action')"
        >
          <component :is="actionIcon" v-if="actionIcon" class="h-3.5 w-3.5" />
          {{ actionLabel }}
        </button>
      </div>
    </div>
  </div>
</template>
