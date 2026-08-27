<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { CalendarCheck, CircleDollarSign } from 'lucide-vue-next';
import { useSalesHistoryStore } from '../../stores/salesHistory';

const store = useSalesHistoryStore();
const date = ref(new Date().toISOString().slice(0, 10));
const money = (value: string | number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(Number(value));

onMounted(() => store.fetchEndOfDay(date.value));
function refresh() { store.fetchEndOfDay(date.value); }
</script>

<template>
  <div class="mx-auto w-full max-w-[1100px]">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-lg font-semibold text-slate-950 dark:text-white">End of Day</h2><p class="mt-1 text-sm font-medium text-blue-300">Reconcile this branch’s sales and payments</p></div><label class="relative"><CalendarCheck class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input v-model="date" type="date" class="field pl-10" @change="refresh"></label></div>
    <p v-if="store.error" class="mb-4 rounded-lg bg-rose-50 px-3 py-3 text-sm font-semibold text-rose-700">{{ store.error }}</p>
    <template v-if="store.endOfDay">
      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"><div v-for="item in [{ label: 'Money Received', value: store.endOfDay.summary.money_received }, { label: 'Sales Total', value: store.endOfDay.summary.sales_total }, { label: 'Outstanding', value: store.endOfDay.summary.outstanding }]" :key="item.label" class="dashboard-card"><p class="text-sm font-medium text-slate-500">{{ item.label }}</p><p class="mt-2 text-xl font-bold text-slate-950 dark:text-white">{{ money(item.value) }}</p></div></section>
      <section class="mt-4 dashboard-card"><div class="flex items-center gap-3"><div class="grid h-10 w-10 place-items-center rounded-lg bg-blue-50 text-blue-600"><CircleDollarSign class="h-5 w-5" /></div><div><h3 class="section-title">Payment breakdown</h3><p class="mt-1 text-xs font-medium text-slate-500">{{ store.endOfDay.summary.transactions }} transaction{{ store.endOfDay.summary.transactions === 1 ? '' : 's' }} · {{ money(store.endOfDay.summary.debt_payments) }} debt payments</p></div></div><div class="mt-5 grid gap-3"> <div v-for="item in store.endOfDay.payment_breakdown" :key="item.method" class="rounded-xl border border-slate-100 p-3 dark:border-white/[0.06]"><div class="flex justify-between gap-3 text-sm"><span class="font-bold capitalize">{{ item.label }}</span><strong>{{ money(item.amount) }}</strong></div><p v-for="(payment, index) in item.payments" :key="index" class="mt-2 text-xs font-medium text-slate-500">{{ money(payment.amount) }}<span v-if="payment.account"> · {{ payment.account }}</span><span v-if="payment.destination"> · {{ payment.destination }}</span></p></div><p v-if="!store.endOfDay.payment_breakdown.length" class="text-sm font-medium text-slate-500">No payments recorded for this date.</p></div></section>
    </template>
  </div>
</template>
