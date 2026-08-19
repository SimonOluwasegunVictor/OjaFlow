<script setup lang="ts">
import { computed } from 'vue';
import { Boxes, Clock3, FileText, Home, LogOut, MoreHorizontal, Package, Settings, ShoppingCart, UserRound, Users, X } from 'lucide-vue-next';
import { RouterLink, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const route = useRoute();

const emit = defineEmits<{
  signOut: [];
}>();

const showMore = defineModel<boolean>('showMore', { default: false });

const primaryLinks = computed(() => [
  { name: 'dashboard', label: 'Home', icon: Home, show: true },
  { name: 'record-sale', label: 'Sale', icon: ShoppingCart, show: auth.canUse('record_sales') },
  { name: 'products', label: 'Products', icon: Package, show: auth.canUse('manage_products') },
  { name: 'stock', label: 'Stock', icon: Boxes, show: auth.canUse(['view_stock', 'adjust_stock', 'manage_products']) },
].filter((link) => link.show).slice(0, 4));

const moreLinks = computed(() => [
  { name: 'customers', label: 'Customers', description: 'Manage your customer list', icon: Users, show: auth.canUse('manage_customers') },
  { name: 'sales-history', label: 'Sales History', description: 'View all past sales', icon: Clock3, show: auth.canUse(['view_sales', 'view_reports']) },
  { name: 'receipts', label: 'Receipt', description: 'View & share receipts', icon: FileText, show: auth.canUse(['view_sales', 'view_reports']) },
  { name: 'staff', label: 'Staff', description: 'Manage your team', icon: UserRound, show: auth.isAdmin },
  { name: 'profile', label: 'Settings', description: 'App & business settings', icon: Settings, show: true },
].filter((link) => link.show));

const moreActive = computed(() => moreLinks.value.some((link) => link.name === route.name));

function closeMore() {
  showMore.value = false;
}
</script>

<template>
  <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-2 pb-[max(env(safe-area-inset-bottom),0.5rem)] pt-2 shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-white/[0.07] dark:bg-gray-900/95 lg:hidden">
    <div class="mx-auto grid max-w-md gap-1" :style="{ gridTemplateColumns: `repeat(${primaryLinks.length + 1}, minmax(0, 1fr))` }">
      <RouterLink
        v-for="link in primaryLinks"
        :key="`${link.name}-${link.label}`"
        :to="{ name: link.name }"
        class="grid min-h-12 justify-items-center gap-1 rounded-lg px-1 py-1 text-[11px] font-semibold text-gray-500 dark:text-gray-500"
        active-class="bg-blue-50 text-primary dark:bg-blue-500/10 dark:text-blue-300"
        @click="closeMore"
      >
        <component :is="link.icon" class="h-5 w-5" />
        <span>{{ link.label }}</span>
      </RouterLink>

      <button
        type="button"
        :class="['grid min-h-12 justify-items-center gap-1 rounded-lg px-1 py-1 text-[11px] font-semibold transition', showMore || moreActive ? 'bg-blue-50 text-primary dark:bg-blue-500/10 dark:text-blue-300' : 'text-gray-500 dark:text-gray-500']"
        aria-label="More options"
        @click="showMore = true"
      >
        <MoreHorizontal class="h-5 w-5" />
        <span>More</span>
      </button>
    </div>
  </nav>

  <Teleport to="body">
    <transition name="sheet-fade">
      <div v-if="showMore" class="fixed inset-0 z-50 bg-slate-950/55 backdrop-blur-[1px] lg:hidden" @click.self="closeMore">
        <transition name="sheet-slide" appear>
          <section class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-md overflow-hidden rounded-t-[22px] bg-white shadow-[0_-24px_60px_rgba(15,23,42,0.24)] dark:bg-gray-950">
            <div class="flex h-14 items-center justify-between border-b border-slate-100 px-4 dark:border-white/[0.06]">
              <h2 class="text-sm font-bold text-slate-900 dark:text-white">More Options</h2>
              <button type="button" class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200 dark:bg-white/[0.06] dark:text-slate-300" aria-label="Close more options" @click="closeMore">
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="max-h-[62dvh] overflow-y-auto px-4 py-4">
              <RouterLink
                v-for="link in moreLinks"
                :key="`${link.name}-more`"
                :to="{ name: link.name }"
                class="flex min-h-14 items-center gap-3 rounded-xl px-2 transition hover:bg-slate-50 dark:hover:bg-white/[0.04]"
                active-class="bg-blue-50 dark:bg-blue-500/10"
                @click="closeMore"
              >
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">
                  <component :is="link.icon" class="h-5 w-5" />
                </span>
                <span class="min-w-0">
                  <span class="block truncate text-sm font-bold text-slate-900 dark:text-white">{{ link.label }}</span>
                  <span class="block truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ link.description }}</span>
                </span>
              </RouterLink>
            </div>

            <div class="border-t border-slate-100 px-4 pb-[max(env(safe-area-inset-bottom),1.25rem)] pt-4 dark:border-white/[0.06]">
              <button type="button" class="flex min-h-14 w-full items-center gap-3 rounded-xl px-2 text-left transition hover:bg-rose-50 dark:hover:bg-rose-500/10" @click="emit('signOut'); closeMore()">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300">
                  <LogOut class="h-5 w-5" />
                </span>
                <span class="min-w-0">
                  <span class="block truncate text-sm font-bold text-rose-600 dark:text-rose-300">Sign Out</span>
                  <span class="block truncate text-xs font-medium text-slate-500 dark:text-slate-400">Sign out of your account</span>
                </span>
              </button>
            </div>
          </section>
        </transition>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.sheet-fade-enter-active,
.sheet-fade-leave-active {
  transition: opacity 0.2s ease;
}

.sheet-fade-enter-from,
.sheet-fade-leave-to {
  opacity: 0;
}

.sheet-slide-enter-active,
.sheet-slide-leave-active {
  transition: transform 0.22s ease, opacity 0.22s ease;
}

.sheet-slide-enter-from,
.sheet-slide-leave-to {
  opacity: 0;
  transform: translateY(18px);
}
</style>
