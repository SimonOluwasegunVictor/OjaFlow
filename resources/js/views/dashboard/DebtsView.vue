<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { AlertTriangle, CreditCard, FileText, MessageSquare, Phone } from 'lucide-vue-next';
import { useSalesStore } from '../../stores/sales';
import type { Debt, DebtPaymentPayload } from '../../types';

type DebtStatus = 'outstanding' | 'partial' | 'overdue' | 'paid';

const salesStore = useSalesStore();
const activeStatus = ref<DebtStatus>('overdue');
const paymentDebt = ref<Debt | null>(null);
const paymentForm = reactive<DebtPaymentPayload>({
  amount: 0,
  payment_method: 'cash',
  note: '',
});

const tabs: Array<{ value: DebtStatus; label: string }> = [
  { value: 'outstanding', label: 'Outstanding' },
  { value: 'partial', label: 'Partial' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'paid', label: 'Paid' },
];

const paymentMethods: Array<{ value: DebtPaymentPayload['payment_method']; label: string }> = [
  { value: 'cash', label: 'Cash' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'pos', label: 'POS' },
];

const selectedBalance = computed(() => Number(paymentDebt.value?.balance_due ?? 0));

onMounted(() => {
  salesStore.fetchDebts({ status: activeStatus.value });
});

async function changeStatus(status: DebtStatus) {
  activeStatus.value = status;
  await salesStore.fetchDebts({ status });
}

function money(value: string | number) {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(value));
}

function openPayment(debt: Debt) {
  paymentDebt.value = debt;
  paymentForm.amount = Number(debt.balance_due);
  paymentForm.payment_method = 'cash';
  paymentForm.note = '';
}

function closePayment() {
  paymentDebt.value = null;
  paymentForm.amount = 0;
  paymentForm.note = '';
}

async function submitPayment() {
  if (!paymentDebt.value) {
    return;
  }

  const success = await salesStore.recordDebtPayment(paymentDebt.value.id, {
    amount: Math.min(Number(paymentForm.amount || 0), selectedBalance.value),
    payment_method: paymentForm.payment_method,
    note: paymentForm.note || null,
  });

  if (success) {
    await salesStore.fetchDebts({ status: activeStatus.value });
    closePayment();
  }
}
</script>

<template>
  <div class="mx-auto w-full max-w-[1320px]">
    <div class="mb-4 grid gap-3 md:grid-cols-3">
      <div class="rounded-[14px] border border-slate-200 bg-white p-5 text-center shadow-[0_1px_4px_rgba(15,23,42,0.14)] dark:border-white/[0.07] dark:bg-gray-900">
        <p class="text-xl font-bold text-rose-600">{{ money(salesStore.debtSummary.total_owed) }}</p>
        <p class="mt-1 text-xs font-medium text-blue-300">Total Owed</p>
      </div>
      <div class="rounded-[14px] border border-slate-200 bg-white p-5 text-center shadow-[0_1px_4px_rgba(15,23,42,0.14)] dark:border-white/[0.07] dark:bg-gray-900">
        <p class="text-xl font-bold text-rose-600">{{ money(salesStore.debtSummary.overdue) }}</p>
        <p class="mt-1 text-xs font-medium text-blue-300">Overdue</p>
      </div>
      <div class="rounded-[14px] border border-slate-200 bg-white p-5 text-center shadow-[0_1px_4px_rgba(15,23,42,0.14)] dark:border-white/[0.07] dark:bg-gray-900">
        <p class="text-xl font-bold text-slate-950 dark:text-white">{{ salesStore.debtSummary.customers }}</p>
        <p class="mt-1 text-xs font-medium text-blue-300">Customers</p>
      </div>
    </div>

    <div class="mb-4 grid rounded-[12px] border border-slate-200 bg-white p-1 shadow-[0_1px_4px_rgba(15,23,42,0.12)] dark:border-white/[0.07] dark:bg-gray-900 sm:grid-cols-4">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        :class="['h-9 rounded-lg text-xs font-bold transition', activeStatus === tab.value ? 'bg-blue-600 text-white shadow-soft' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/[0.05]']"
        @click="changeStatus(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>

    <p v-if="salesStore.error" class="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
      {{ salesStore.error }}
    </p>

    <div v-if="salesStore.debts.length" class="space-y-3">
      <article
        v-for="debt in salesStore.debts"
        :key="debt.id"
        class="rounded-[14px] border border-slate-200 bg-white p-4 shadow-[0_1px_4px_rgba(15,23,42,0.14)] dark:border-white/[0.07] dark:bg-gray-900"
      >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0">
            <h3 class="truncate text-base font-bold text-slate-950 dark:text-white">{{ debt.customer?.name ?? 'Unknown customer' }}</h3>
            <p class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-blue-300">
              <Phone class="h-3.5 w-3.5" />
              {{ debt.customer?.phone ?? 'No phone' }} · {{ debt.order_number }}
            </p>
          </div>
          <span :class="['w-fit rounded-full border px-3 py-1 text-xs font-bold', debt.is_overdue ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700']">
            {{ debt.is_overdue ? 'Overdue' : debt.payment_status }}
          </span>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-3">
          <div class="rounded-lg bg-slate-50 px-4 py-3 text-center dark:bg-white/[0.04]">
            <p class="text-xs font-medium text-blue-300">Original</p>
            <p class="mt-1 text-sm font-bold text-slate-950 dark:text-white">{{ money(debt.original_amount) }}</p>
          </div>
          <div class="rounded-lg bg-slate-50 px-4 py-3 text-center dark:bg-white/[0.04]">
            <p class="text-xs font-medium text-blue-300">Paid</p>
            <p class="mt-1 text-sm font-bold text-emerald-600">{{ money(debt.paid_amount) }}</p>
          </div>
          <div class="rounded-lg bg-rose-50 px-4 py-3 text-center">
            <p class="text-xs font-medium text-rose-300">Balance</p>
            <p class="mt-1 text-sm font-bold text-rose-600">{{ money(debt.balance_due) }}</p>
          </div>
        </div>

        <div v-if="debt.is_overdue && debt.due_date" class="mt-4 flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600">
          <AlertTriangle class="h-4 w-4" />
          Due date passed: {{ debt.due_date }}
        </div>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
          <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 text-xs font-bold text-white shadow-soft hover:bg-blue-700" @click="openPayment(debt)">
            <CreditCard class="h-4 w-4" />
            Record Payment
          </button>
          <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-white/[0.08] dark:text-slate-200">
            <MessageSquare class="h-4 w-4" />
            Send Reminder
          </button>
          <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-white/[0.08] dark:text-slate-200">
            <FileText class="h-4 w-4" />
            View Statement
          </button>
        </div>
      </article>
    </div>

    <div v-else class="flex min-h-[42dvh] flex-col items-center justify-center gap-4 rounded-[14px] border border-dashed border-slate-200 bg-white px-4 text-center dark:border-white/[0.08] dark:bg-gray-900">
      <div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-50">
        <CreditCard class="h-6 w-6 text-blue-500" />
      </div>
      <div>
        <p class="text-sm font-semibold text-slate-900 dark:text-white">No debts here</p>
        <p class="mt-1 text-xs font-medium text-slate-400">Credit or partial sales will show in this list.</p>
      </div>
    </div>

    <div v-if="paymentDebt" class="fixed inset-0 z-50 grid place-items-end bg-black/45 px-0 sm:place-items-center sm:p-4">
      <form class="w-full rounded-t-[20px] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.28)] dark:bg-gray-900 sm:max-w-md sm:rounded-[20px]" @submit.prevent="submitPayment">
        <h2 class="text-lg font-bold text-slate-950 dark:text-white">Record Payment</h2>
        <p class="mt-1 text-sm font-medium text-slate-500">{{ paymentDebt.customer?.name }} · Balance {{ money(paymentDebt.balance_due) }}</p>

        <label class="mt-5 grid gap-2 text-sm font-bold text-slate-900 dark:text-white">
          Amount
          <input v-model.number="paymentForm.amount" type="number" min="1" :max="selectedBalance" class="h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-950">
        </label>

        <div class="mt-4">
          <p class="text-sm font-bold text-slate-900 dark:text-white">Payment Method</p>
          <div class="mt-2 grid grid-cols-3 gap-2">
            <button
              v-for="method in paymentMethods"
              :key="method.value"
              type="button"
              :class="['h-10 rounded-lg border text-sm font-bold transition', paymentForm.payment_method === method.value ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/[0.08] dark:bg-gray-950 dark:text-slate-200']"
              @click="paymentForm.payment_method = method.value"
            >
              {{ method.label }}
            </button>
          </div>
        </div>

        <label class="mt-4 grid gap-2 text-sm font-bold text-slate-900 dark:text-white">
          Note
          <textarea v-model="paymentForm.note" rows="3" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-950" />
        </label>

        <div class="mt-5 flex gap-2">
          <button type="button" class="h-11 flex-1 rounded-lg border border-slate-200 text-sm font-bold text-slate-700" @click="closePayment">Cancel</button>
          <button type="submit" :disabled="salesStore.loading" class="h-11 flex-1 rounded-lg bg-blue-600 text-sm font-bold text-white disabled:opacity-50">
            {{ salesStore.loading ? 'Saving...' : 'Save Payment' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
