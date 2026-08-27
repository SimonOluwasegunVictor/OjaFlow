<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { CalendarDays, Search, ReceiptText } from 'lucide-vue-next';
import { useSalesHistoryStore } from '../../stores/salesHistory';

const store = useSalesHistoryStore();
const date = ref(new Date().toISOString().slice(0, 10));
const search = ref('');
const status = ref('');

onMounted(() => fetchSales());

async function fetchSales() {
  await store.fetchSales({ date: date.value, search: search.value || undefined, payment_status: status.value || undefined });
}

const money = (value: string | number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(Number(value));
const visibleSales = computed(() => store.sales);
</script>

<template>
  <div class="mx-auto w-full max-w-[1100px]">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div><h2 class="text-lg font-semibold text-slate-950 dark:text-white">Sales History</h2><p class="mt-1 text-sm font-medium text-blue-300">Completed sales recorded for this branch</p></div>
      <div class="flex flex-col gap-2 sm:flex-row">
        <label class="relative"><CalendarDays class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input v-model="date" type="date" class="field pl-10" @change="fetchSales"></label>
        <select v-model="status" class="field sm:w-36" @change="fetchSales"><option value="">All statuses</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="outstanding">Unpaid</option></select>
      </div>
    </div>
    <div class="relative mb-4"><Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input v-model="search" class="field pl-10" placeholder="Search order number or customer" @keyup.enter="fetchSales"></div>
    <p v-if="store.error" class="mb-4 rounded-lg bg-rose-50 px-3 py-3 text-sm font-semibold text-rose-700">{{ store.error }}</p>
    <section v-if="visibleSales.length" class="overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-card dark:border-white/[0.07] dark:bg-gray-900">
      <article v-for="sale in visibleSales" :key="sale.id" class="border-b border-slate-100 p-4 last:border-0 dark:border-white/[0.06]">
        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-bold text-slate-950 dark:text-white">{{ sale.customer?.name ?? 'Walk-in Customer' }}</p><p class="mt-1 text-xs font-medium text-slate-500">{{ sale.order_number }} · {{ new Date(sale.created_at).toLocaleTimeString('en-NG', { hour: 'numeric', minute: '2-digit' }) }}</p></div><strong class="shrink-0 text-sm text-blue-600">{{ money(sale.total) }}</strong></div>
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-medium text-slate-500"><span>{{ sale.items.length }} item{{ sale.items.length === 1 ? '' : 's' }}</span><span>Paid: {{ money(sale.amount_paid) }}</span><span>Balance: {{ money(sale.balance_due) }}</span><span class="rounded-full px-2 py-1 font-bold" :class="sale.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : sale.payment_status === 'partial' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700'">{{ sale.payment_status }}</span></div>
        <div v-if="sale.payments.length" class="mt-3 flex flex-wrap gap-2"><span v-for="payment in sale.payments" :key="payment.id" class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-white/[0.06] dark:text-slate-300">{{ payment.method }} · {{ money(payment.amount) }}<span v-if="payment.account"> · {{ payment.account.name }}</span></span></div>
      </article>
    </section>
    <div v-else class="flex min-h-[45dvh] flex-col items-center justify-center rounded-[14px] border border-dashed border-slate-200 bg-white text-center dark:border-white/[0.08] dark:bg-gray-900"><ReceiptText class="h-8 w-8 text-slate-300" /><p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">No sales found</p><p class="mt-1 text-xs text-slate-400">Try another date or search.</p></div>
  </div>
</template>
