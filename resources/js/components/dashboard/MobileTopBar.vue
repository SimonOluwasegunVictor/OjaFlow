<script setup lang="ts">
import { Bell, Plus } from 'lucide-vue-next';
import BrandMark from './BrandMark.vue';

defineProps<{
  title: string;
  businessName?: string | null;
  canRecordSale?: boolean;
}>();
</script>

<template>
  <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur lg:hidden">
    <div class="flex items-center justify-between gap-3 px-4 py-3">
      <BrandMark :business-name="businessName" />
      <button class="relative grid h-10 w-10 place-items-center rounded-lg text-slate-700 hover:bg-slate-100" type="button" aria-label="Notifications">
        <Bell class="h-5 w-5" />
        <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500" />
      </button>
    </div>
    <div class="flex items-center justify-between gap-3 px-4 pb-3">
      <h1 class="text-lg font-black text-slate-950">{{ title }}</h1>
      <RouterLink
        v-if="canRecordSale"
        :to="{ name: 'record-sale' }"
        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-3 text-sm font-bold text-white shadow-soft transition hover:bg-primary-700"
      >
        <Plus class="h-4 w-4" />
        Sale
      </RouterLink>
    </div>
  </header>
</template>
