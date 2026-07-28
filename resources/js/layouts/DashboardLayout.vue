<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Bell,
  Boxes,
  ChevronRight,
  CreditCard,
  History,
  Home,
  LogOut,
  Package,
  Plus,
  Settings,
  ShoppingCart,
  Users,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/auth';
import { useBranchStore } from '../stores/branches';
import { useStaffStore } from '../stores/staff';
import BrandMark from '../components/dashboard/BrandMark.vue';
import MobileTopBar from '../components/dashboard/MobileTopBar.vue';
import BottomNav from '../components/dashboard/BottomNav.vue';
import ConfirmDialog from '../components/ui/ConfirmDialog.vue';

const auth = useAuthStore();
const branches = useBranchStore();
const staff = useStaffStore();
const route = useRoute();
const router = useRouter();
const confirmingLogout = ref(false);

const links = computed(() => [
  { name: 'dashboard', label: 'Dashboard', icon: Home, show: true },
  { name: 'record-sale', label: 'Record Sale', icon: ShoppingCart, show: auth.canUse('record_sales') },
  { name: 'products', label: 'Products', icon: Package, show: auth.canUse('manage_products') },
  { name: 'branches', label: 'Branches', icon: Boxes, show: auth.isAdmin },
  { name: 'stock', label: 'Stock', icon: Boxes, show: auth.isStaff && auth.canUse(['view_stock', 'adjust_stock']) },
  { name: 'customers', label: 'Customers', icon: Users, show: auth.canUse('manage_customers') },
  { name: 'debts', label: 'Debts', icon: CreditCard, show: auth.canUse('record_debt_payments') },
  { name: 'sales-history', label: 'Sales History', icon: History, show: auth.canUse(['view_sales', 'view_reports']) },
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
  <main class="min-h-dvh bg-slate-50 text-slate-950">
    <MobileTopBar :title="title" :business-name="auth.user?.business?.name" :can-record-sale="auth.canUse('record_sales')" />

    <aside class="fixed inset-y-0 left-0 z-40 hidden w-60 border-r border-slate-200 bg-white lg:flex lg:flex-col">
      <div class="border-b border-slate-100 px-5 py-5">
        <BrandMark :business-name="auth.user?.business?.name" />
      </div>

      <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <RouterLink
          v-for="link in links"
          :key="link.name"
          :to="{ name: link.name }"
          class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-bold text-slate-600 transition hover:bg-slate-100"
          active-class="bg-primary text-white shadow-soft hover:bg-primary"
        >
          <component :is="link.icon" class="h-5 w-5" />
          <span>{{ link.label }}</span>
        </RouterLink>
      </nav>

      <div class="border-t border-slate-100 px-5 py-4">
        <RouterLink :to="{ name: 'profile' }" class="flex items-center gap-3 rounded-lg p-2 hover:bg-slate-50">
          <div class="grid h-11 w-11 place-items-center rounded-full bg-blue-100 text-sm font-black text-primary">
            {{ auth.user?.first_name.slice(0, 1) }}{{ auth.user?.last_name.slice(0, 1) }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-black">{{ auth.fullName }}</p>
            <p class="truncate text-xs font-medium capitalize text-slate-400">{{ auth.user?.role }} / {{ auth.user?.status }}</p>
          </div>
          <ChevronRight class="h-4 w-4 text-slate-400" />
        </RouterLink>
        <button class="mt-2 flex min-h-10 w-full items-center gap-3 rounded-lg px-3 text-sm font-bold text-slate-500 hover:bg-slate-100" type="button" @click="confirmingLogout = true">
          <LogOut class="h-4 w-4" />
          Sign Out
        </button>
      </div>
    </aside>

    <section class="lg:pl-60">
      <header class="sticky top-0 z-20 hidden h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-6 backdrop-blur lg:flex">
        <h1 class="text-lg font-black">{{ title }}</h1>
        <div class="flex items-center gap-3">
          <button class="relative grid h-10 w-10 place-items-center rounded-lg text-slate-700 hover:bg-slate-100" type="button" aria-label="Notifications">
            <Bell class="h-5 w-5" />
            <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500" />
          </button>
          <RouterLink
            v-if="auth.canUse('record_sales')"
            :to="{ name: 'record-sale' }"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-white shadow-soft transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2"
          >
            <Plus class="h-4 w-4" />
            Record Sale
          </RouterLink>
        </div>
      </header>

      <div class="mx-auto w-full max-w-[1680px] px-4 pb-24 pt-4 sm:px-6 lg:px-6 lg:pb-8">
        <RouterView />
      </div>
    </section>

    <BottomNav />
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
