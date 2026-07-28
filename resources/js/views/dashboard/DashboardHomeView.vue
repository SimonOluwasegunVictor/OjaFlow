<script setup lang="ts">
import { computed } from 'vue';
import { AlertTriangle, Banknote, Boxes, CreditCard, Package, PackageX, Plus, ReceiptText, Settings, ShoppingCart, TrendingUp, UserPlus, Users, WalletCards } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';
import MetricCard from '../../components/dashboard/MetricCard.vue';
import SalesChart from '../../components/dashboard/SalesChart.vue';
import PaymentBreakdown from '../../components/dashboard/PaymentBreakdown.vue';

const auth = useAuthStore();

const metrics = [
  { label: "Today's Sales", value: 'NGN 633,500', meta: '+12.4%', tone: 'blue', icon: TrendingUp },
  { label: 'Money Received', value: 'NGN 585,500', meta: '+8.2%', tone: 'green', icon: WalletCards },
  { label: 'Customers Owing You', value: 'NGN 343,000', meta: '4 customers', tone: 'orange', icon: CreditCard },
  { label: 'Est. Profit Today', value: 'NGN 94,200', meta: '+NGN 12,000', tone: 'purple', icon: Banknote },
  { label: 'Total Transactions', value: '18', meta: 'Today', tone: 'cyan', icon: ReceiptText },
  { label: 'Low Stock Products', value: '3', meta: 'Need reorder', tone: 'red', icon: AlertTriangle },
] as const;

const sales = [
  { name: 'Ade Okafor', order: 'ORD-001 - 10:45 AM', amount: 'NGN 45,000', status: 'Paid' },
  { name: 'Bimpe Adeyemi', order: 'ORD-002 - 09:30 AM', amount: 'NGN 128,000', status: 'Partial' },
  { name: 'Walk-in Customer', order: 'ORD-003 - 08:55 AM', amount: 'NGN 15,500', status: 'Paid' },
  { name: 'Emeka Nwosu', order: 'ORD-004 - Yesterday', amount: 'NGN 220,000', status: 'Unpaid' },
];

const lowStock = [
  { name: 'Dangote Cement (50kg)', detail: '3 Bags left - Reorder at 20' },
  { name: 'Iron Rod 12mm', detail: '5 Bundles left - Reorder at 15' },
  { name: 'POP Plaster White', detail: '2 Bags left - Reorder at 10' },
];

const quickActions = computed(() => [
  { label: 'Record Sale', route: 'record-sale', icon: ShoppingCart, permission: 'record_sales', primary: true },
  { label: 'Add Product', route: 'products', icon: Plus, permission: 'manage_products', primary: false },
  { label: 'Add Customer', route: 'customers', icon: UserPlus, permission: 'manage_customers', primary: false },
  { label: 'Record Debt Payment', route: 'debts', icon: CreditCard, permission: 'record_debt_payments', primary: false },
].filter((action) => auth.canUse(action.permission)));

const permissionCards = computed(() => [
  { label: 'Dashboard', description: 'View the main business summary.', icon: TrendingUp, permission: 'view_dashboard' },
  { label: 'Sales', description: 'Record sales and view sales history.', icon: ShoppingCart, permission: ['record_sales', 'view_sales'] },
  { label: 'Products', description: 'Manage products and selling items.', icon: Package, permission: 'manage_products' },
  { label: 'Stock', description: 'View or adjust branch stock.', icon: Boxes, permission: ['view_stock', 'adjust_stock'] },
  { label: 'Customers', description: 'Manage customer records.', icon: Users, permission: 'manage_customers' },
  { label: 'Debt Payments', description: 'Record payments for customers owing.', icon: CreditCard, permission: 'record_debt_payments' },
  { label: 'Reports', description: 'View reports and sales performance.', icon: ReceiptText, permission: 'view_reports' },
  { label: 'Settings', description: 'Manage account settings.', icon: Settings, permission: 'manage_settings' },
].filter((item) => auth.canUse(item.permission)));
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-black text-slate-950">Welcome, {{ auth.user?.first_name }}</h2>
        <p class="mt-1 text-sm font-medium text-slate-500">{{ auth.user?.business?.name }} - {{ auth.isStaff ? 'Staff dashboard' : 'Admin dashboard' }}</p>
      </div>
      <RouterLink
        v-if="auth.canUse('record_sales')"
        :to="{ name: 'record-sale' }"
        class="hidden min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-white shadow-soft transition hover:bg-primary-700 sm:inline-flex"
      >
        <Plus class="h-4 w-4" />
        Record Sale
      </RouterLink>
    </div>

    <section v-if="auth.canUse(['view_dashboard', 'view_reports'])" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <MetricCard v-for="metric in metrics" :key="metric.label" v-bind="metric" />
    </section>

    <section>
      <h2 class="section-title mb-3">Quick Actions</h2>
      <div v-if="quickActions.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <RouterLink
          v-for="action in quickActions"
          :key="action.label"
          :to="{ name: action.route }"
          class="inline-flex min-h-11 items-center justify-start gap-2 rounded-lg px-4 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-offset-2"
          :class="action.primary ? 'bg-primary text-white shadow-soft hover:bg-primary-700 focus:ring-primary/40' : 'border border-slate-200 bg-white text-slate-900 shadow-card hover:bg-slate-50 focus:ring-primary/25'"
        >
          <component :is="action.icon" class="h-4 w-4" :class="action.primary ? 'text-white' : 'text-slate-500'" />
          {{ action.label }}
        </RouterLink>
      </div>
      <p v-else class="rounded-lg border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-500 shadow-card">No quick actions are enabled for this account yet.</p>
    </section>

    <section v-if="auth.isStaff" class="dashboard-card">
      <h2 class="section-title">Your Access</h2>
      <div v-if="permissionCards.length" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="item in permissionCards" :key="item.label" class="rounded-lg border border-slate-200 p-4">
          <component :is="item.icon" class="h-5 w-5 text-primary" />
          <h3 class="mt-3 text-sm font-black text-slate-950">{{ item.label }}</h3>
          <p class="mt-1 text-xs font-medium leading-5 text-slate-500">{{ item.description }}</p>
        </article>
      </div>
      <p v-else class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">Your admin has not enabled any dashboard permissions for this staff account yet.</p>
    </section>

    <section v-if="auth.canUse('view_reports')" class="grid gap-4 xl:grid-cols-[1fr_0.95fr]">
      <SalesChart />
      <PaymentBreakdown />
    </section>

    <section v-if="auth.canUse('view_sales')" class="dashboard-card">
      <div class="flex items-center justify-between gap-3">
        <h2 class="section-title">Recent Sales</h2>
        <RouterLink :to="{ name: 'sales-history' }" class="text-sm font-black text-primary">View all</RouterLink>
      </div>
      <div class="mt-5 divide-y divide-slate-100">
        <div v-for="sale in sales" :key="sale.order" class="flex items-center gap-3 py-4">
          <div class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-xs font-black text-slate-500">{{ sale.name.slice(0, 1) }}</div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-black text-slate-950">{{ sale.name }}</p>
            <p class="text-xs font-medium text-slate-400">{{ sale.order }}</p>
          </div>
          <div class="text-right">
            <p class="text-sm font-black">{{ sale.amount }}</p>
            <span
              class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-bold"
              :class="{
                'border border-emerald-200 bg-emerald-50 text-emerald-700': sale.status === 'Paid',
                'border border-amber-200 bg-amber-50 text-amber-700': sale.status === 'Partial',
                'border border-rose-200 bg-rose-50 text-rose-700': sale.status === 'Unpaid',
              }"
            >
              {{ sale.status }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <section v-if="auth.canUse('view_stock')" class="rounded-lg border border-amber-300 bg-amber-50 p-4 sm:p-5">
      <h2 class="flex items-center gap-2 text-sm font-black text-amber-900">
        <PackageX class="h-4 w-4" />
        Low Stock Alert
      </h2>
      <div class="mt-4 grid gap-2">
        <div v-for="item in lowStock" :key="item.name" class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-card">
          <div class="min-w-0">
            <p class="truncate text-sm font-black">{{ item.name }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">{{ item.detail }}</p>
          </div>
          <RouterLink v-if="auth.canUse('adjust_stock')" :to="{ name: auth.isAdmin ? 'branches' : 'stock' }" class="shrink-0 text-sm font-black text-primary">Add Stock</RouterLink>
        </div>
      </div>
    </section>
  </div>
</template>
