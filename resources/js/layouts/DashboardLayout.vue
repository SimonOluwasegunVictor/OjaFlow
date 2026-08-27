<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Bell,
  Boxes,
  CreditCard,
  History,
  ClipboardCheck,
  Home,
  LogOut,
  Package,
  Settings,
  ShoppingCart,
  SunMoon,
  Users,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/auth';
import { useBranchStore } from '../stores/branches';
import { useStaffStore } from '../stores/staff';
import { useThemeStore } from '../stores/theme';
import BrandMark from '../components/dashboard/BrandMark.vue';
import MobileTopBar from '../components/dashboard/MobileTopBar.vue';
import BottomNav from '../components/dashboard/BottomNav.vue';
import ConfirmDialog from '../components/ui/ConfirmDialog.vue';

const auth = useAuthStore();
const branches = useBranchStore();
const staff = useStaffStore();
const theme = useThemeStore();
const route = useRoute();
const router = useRouter();
const confirmingLogout = ref(false);

const links = computed(() => [
  { name: 'dashboard', label: 'Dashboard', icon: Home, show: true },
  { name: 'record-sale', label: 'Record Sale', icon: ShoppingCart, show: auth.canUse('record_sales') },
  { name: 'products', label: 'Products', icon: Package, show: auth.canUse('manage_products') },
  { name: 'stock', label: 'Stock', icon: Boxes, show: auth.canUse(['view_stock', 'adjust_stock', 'manage_products']) },
  { name: 'customers', label: 'Customers', icon: Users, show: auth.canUse('manage_customers') },
  { name: 'debts', label: 'Debts', icon: CreditCard, show: auth.canUse('record_debt_payments') },
  { name: 'sales-history', label: 'Sales History', icon: History, show: auth.canUse(['view_sales', 'view_reports']) },
  { name: 'end-of-day', label: 'End of Day', icon: ClipboardCheck, show: auth.canUse(['view_sales', 'view_reports']) },
  { name: 'staff', label: 'Staff', icon: Users, show: auth.isAdmin },
  { name: 'profile', label: 'Settings', icon: Settings, show: true },
].filter((link) => link.show));

const title = computed(() => route.meta.title?.toString() ?? 'Dashboard');

onMounted(async () => {
  if (auth.isAdmin) {
    await Promise.all([
      branches.fetchBranches().catch(() => undefined),
      staff.bootstrap().catch(() => undefined),
    ]);
  }
});

async function logout() {
  await auth.logout();
  staff.$reset();
  branches.$reset();
  confirmingLogout.value = false;
  await router.push({ name: 'login' });
}
</script>

<template>
  <main class="min-h-dvh bg-gray-50 text-gray-950 transition-colors dark:bg-gray-950 dark:text-gray-100">
    <MobileTopBar :title="title" :business-name="auth.user?.business?.name" :can-record-sale="auth.canUse('record_sales')" />

    <aside class="fixed inset-y-0 left-0 z-40 hidden w-[229px] border-r border-gray-200 bg-white dark:border-white/[0.07] dark:bg-gray-900 lg:flex lg:flex-col">
      <div class="border-b border-gray-100 px-3.5 py-3.5 dark:border-white/[0.06]">
        <BrandMark :business-name="auth.user?.business?.name" />
      </div>

      <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">
        <RouterLink
          v-for="link in links"
          :key="link.name"
          :to="{ name: link.name }"
          class="flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-700 transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.06]"
          active-class="bg-primary text-white shadow-soft hover:bg-primary"
        >
          <component :is="link.icon" class="h-5 w-5" />
          <span>{{ link.label }}</span>
        </RouterLink>
      </nav>

      <div class="border-t border-gray-100 px-3.5 py-4 dark:border-white/[0.06]">
        <RouterLink :to="{ name: 'profile' }" class="flex items-center gap-3 rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-white/[0.04]">
          <div class="grid h-8 w-8 place-items-center rounded-full bg-blue-100 text-xs font-semibold text-primary dark:bg-blue-500/10 dark:text-blue-300">
            {{ auth.user?.first_name.slice(0, 1) }}{{ auth.user?.last_name.slice(0, 1) }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-semibold">{{ auth.fullName }}</p>
            <p class="truncate text-xs font-medium capitalize text-gray-400 dark:text-gray-500">{{ auth.user?.role }} / {{ auth.user?.status }}</p>
          </div>
        </RouterLink>
        <button class="mt-2 flex min-h-10 w-full items-center gap-3 rounded-lg px-3 text-sm font-semibold text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.06]" type="button" @click="confirmingLogout = true">
          <LogOut class="h-4 w-4" />
          Sign Out
        </button>
      </div>
    </aside>

    <section class="lg:pl-[229px]">
      <header class="sticky top-0 z-20 hidden h-14 items-center justify-between border-b border-gray-200 bg-white/90 px-6 backdrop-blur lg:flex dark:border-white/[0.07] dark:bg-gray-900/85">
        <h1 class="text-base font-semibold text-slate-950">{{ title }}</h1>
        <div class="flex items-center gap-3">
          <button class="hidden h-10 w-10 place-items-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.06]" type="button" aria-label="Toggle dark mode" @click="theme.toggleDarkMode()">
            <SunMoon class="h-5 w-5" />
          </button>
          <button class="relative grid h-10 w-10 place-items-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.06]" type="button" aria-label="Notifications">
            <Bell class="h-5 w-5" />
            <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500" />
          </button>
        </div>
      </header>

      <div class="w-full px-4 pb-24 pt-4 sm:px-6 lg:px-6 lg:pb-8 lg:pt-6">
        <RouterView />
      </div>
    </section>

    <BottomNav @sign-out="confirmingLogout = true" />
    <ConfirmDialog
      v-model:open="confirmingLogout"
      title="Sign out?"
      description="You will return to the login screen and your current session token will be cleared from this device."
      confirm-label="Sign Out"
      tone="warning"
      @confirm="logout"
    />
  </main>
</template>
