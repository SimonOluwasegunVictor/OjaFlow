<script setup lang="ts">
import { computed } from 'vue';
import { Boxes, Home, Settings, ShoppingCart, Users } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();

const links = computed(() => [
  { name: 'dashboard', label: 'Home', icon: Home, show: true },
  { name: 'record-sale', label: 'Sale', icon: ShoppingCart, show: auth.canUse('record_sales') },
  { name: 'branches', label: 'Branches', icon: Boxes, show: auth.isAdmin },
  { name: 'stock', label: 'Stock', icon: Boxes, show: auth.isStaff && auth.canUse(['view_stock', 'adjust_stock']) },
  { name: 'staff', label: 'Staff', icon: Users, show: auth.isAdmin },
  { name: 'profile', label: 'Settings', icon: Settings, show: true },
].filter((link) => link.show).slice(0, 5));
</script>

<template>
  <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-2 pb-[max(env(safe-area-inset-bottom),0.5rem)] pt-2 shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-white/[0.07] dark:bg-gray-900/95 lg:hidden">
    <div class="mx-auto grid max-w-md gap-1" :style="{ gridTemplateColumns: `repeat(${links.length}, minmax(0, 1fr))` }">
      <RouterLink
        v-for="link in links"
        :key="`${link.name}-${link.label}`"
        :to="{ name: link.name }"
        class="grid min-h-12 justify-items-center gap-1 rounded-lg px-1 py-1 text-[11px] font-semibold text-gray-500 dark:text-gray-500"
        active-class="bg-blue-50 text-primary dark:bg-blue-500/10 dark:text-blue-300"
      >
        <component :is="link.icon" class="h-5 w-5" />
        <span>{{ link.label }}</span>
      </RouterLink>
    </div>
  </nav>
</template>
