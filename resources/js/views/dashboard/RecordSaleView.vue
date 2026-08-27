<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { AlertTriangle, CheckCircle2, Minus, Package, Plus, Search, ShoppingCart, Trash2, X } from 'lucide-vue-next';
import { useCartStore } from '../../stores/cart';
import { useAuthStore } from '../../stores/auth';
import { useCustomerStore } from '../../stores/customers';
import { useProductStore } from '../../stores/products';
import { useSalesStore } from '../../stores/sales';
import { usePaymentAccountStore } from '../../stores/paymentAccounts';
import SearchableSelect from '../../components/ui/SearchableSelect.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import type { Product, SalePaymentMethod, SalePayload } from '../../types';

interface SearchableSelectOption {
  value: string;
  label: string;
  description?: string | null;
}

const cartStore = useCartStore();
const auth = useAuthStore();
const productStore = useProductStore();
const customerStore = useCustomerStore();
const salesStore = useSalesStore();
const paymentAccountStore = usePaymentAccountStore();
const search = ref('');
const selectedCustomerId = ref('');
const discount = ref(0);
const checkoutOpen = ref(false);
const paymentOpen = ref(false);
const successOpen = ref(false);
const paymentMethod = ref<SalePaymentMethod>('cash');
const amountPaid = ref(0);
const dueDate = ref(defaultDueDate());
const notice = ref('');
const selectedAccountId = ref('');
const reference = ref('');
interface PaymentLine {
  method: Exclude<SalePaymentMethod, 'credit' | 'split'>;
  amount: number;
  payment_account_id: string;
  reference: string;
}
const paymentLines = ref<PaymentLine[]>([]);

onMounted(async () => {
  await Promise.all([
    productStore.fetchProducts({ status: 'active' }),
    customerStore.fetchCustomers(),
    cartStore.fetchCart(),
    paymentAccountStore.fetch(),
  ]);

  syncCartFields();
});

const filteredProducts = computed(() => {
  const query = search.value.trim().toLowerCase();

  return productStore.activeProducts.filter((product) => {
    if (product.quantity <= 0) {
      return false;
    }

    if (!query) {
      return true;
    }

    return [product.name, product.sku, product.category, product.unit].some((field) => field?.toLowerCase().includes(query));
  });
});

const cartItems = computed(() => cartStore.items);
const subtotal = computed(() => cartStore.subtotal);
const total = computed(() => cartStore.total);
const itemCount = computed(() => cartStore.count);
const paymentTotal = computed(() => paymentMethod.value === 'split'
  ? paymentLines.value.reduce((sum, line) => sum + Number(line.amount || 0), 0)
  : paymentMethod.value === 'credit' ? 0 : Number(amountPaid.value || 0));
const balanceRemaining = computed(() => Math.max(0, total.value - paymentTotal.value));
const selectedCustomer = computed(() => customerStore.customers.find((customer) => customer.id === selectedCustomerId.value) ?? null);
const customerOptions = computed<SearchableSelectOption[]>(() => [
  { value: '', label: 'Walk-in Customer', description: 'No credit balance will be assigned' },
  ...customerStore.customers.map((customer) => ({
    value: customer.id,
    label: customer.name,
    description: customer.phone ?? customer.email ?? 'Customer record',
  })),
]);
const customerSelectionRequired = computed(() => balanceRemaining.value > 0);
const creditNeedsCustomer = computed(() => customerSelectionRequired.value && !selectedCustomer.value);
const needsDueDate = computed(() => balanceRemaining.value > 0);
const splitPaymentsValid = computed(() => paymentMethod.value !== 'split' || (paymentLines.value.length >= 2 && paymentLines.value.every((line) => Number(line.amount) > 0 && (line.method === 'cash' || Boolean(line.payment_account_id)))));
const accountSelectionValid = computed(() => paymentMethod.value === 'cash' || paymentMethod.value === 'credit' || Boolean(selectedAccountId.value));
const canSubmitPayment = computed(() => Boolean(cartStore.cart?.id) && cartItems.value.length > 0 && !creditNeedsCustomer.value && splitPaymentsValid.value && accountSelectionValid.value && (!needsDueDate.value || Boolean(dueDate.value)));
const paymentMethods: Array<{ value: SalePaymentMethod; label: string }> = [
  { value: 'cash', label: 'Cash' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'pos', label: 'POS' },
  { value: 'credit', label: 'Credit' },
  { value: 'split', label: 'Split' },
];

function money(value: string | number) {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(value));
}

function productStock(product: Product) {
  return `${product.quantity} ${product.unit}${product.quantity === 1 ? '' : 's'} left`;
}

function syncCartFields() {
  selectedCustomerId.value = cartStore.cart?.customer_id ?? '';
  discount.value = cartStore.discount;
}

async function updateCartMeta() {
  const success = await cartStore.updateCart({
    customer_id: selectedCustomerId.value || null,
    discount: Number(discount.value || 0),
  });
  syncCartFields();
  return success;
}

async function addProduct(product: Product) {
  await cartStore.addProduct(product);
  checkoutOpen.value = true;
  notice.value = '';
}

async function increase(productId: string, quantity: number) {
  await cartStore.updateProductQuantity(productId, quantity + 1);
}

async function decrease(productId: string, quantity: number) {
  await cartStore.updateProductQuantity(productId, Math.max(0, quantity - 1));
}

async function removeItem(productId: string) {
  await cartStore.removeProduct(productId);
}

async function openPayment() {
  if (!cartItems.value.length) {
    return;
  }

  const cartUpdated = await updateCartMeta();

  if (!cartUpdated) {
    return;
  }
  amountPaid.value = total.value;
  paymentMethod.value = 'cash';
  paymentLines.value = [];
  dueDate.value = defaultDueDate();
  paymentOpen.value = true;
  notice.value = '';
}

async function submitSale() {
  if (!canSubmitPayment.value) {
    return;
  }

  const cartUpdated = await updateCartMeta();

  if (!cartUpdated) {
    return;
  }

  if (!cartStore.cart?.id) {
    return;
  }

  const payments: SalePayload['payments'] = paymentMethod.value === 'split'
    ? paymentLines.value.map((line) => ({ method: line.method, amount: Number(line.amount), payment_account_id: line.payment_account_id || null, reference: line.reference || null }))
    : paymentMethod.value === 'credit' ? [] : [{ method: paymentMethod.value as Exclude<SalePaymentMethod, 'credit' | 'split'>, amount: Number(amountPaid.value || 0), payment_account_id: selectedAccountId.value || null, reference: reference.value || null }];

  const success = await salesStore.completeSale({
    cart_id: cartStore.cart.id,
    payment_method: paymentMethod.value,
    amount_paid: paymentTotal.value,
    payments,
    due_date: balanceRemaining.value > 0 ? dueDate.value : null,
  });

  if (!success) {
    return;
  }

  notice.value = `Sale ${salesStore.lastSale?.order_number ?? ''} completed.`;
  discount.value = 0;
  amountPaid.value = 0;
  checkoutOpen.value = false;
  paymentOpen.value = false;
  successOpen.value = true;
  await Promise.all([
    productStore.fetchProducts({ status: 'active' }).catch(() => undefined),
    customerStore.fetchCustomers().catch(() => undefined),
    cartStore.fetchCart().catch(() => undefined),
  ]);
  syncCartFields();
}

const accountOptions = computed(() => paymentAccountStore.accounts.filter((account) => account.type === (paymentMethod.value === 'pos' ? 'pos' : 'bank')));
const splitAccountOptions = (method: PaymentLine['method']) => paymentAccountStore.accounts.filter((account) => account.type === (method === 'pos' ? 'pos' : 'bank'));

function changePaymentMethod(method: SalePaymentMethod) {
  paymentMethod.value = method;
  selectedAccountId.value = '';
  reference.value = '';

  if (method === 'credit') amountPaid.value = 0;
  if (method === 'split' && paymentLines.value.length < 2) {
    paymentLines.value = [
      { method: 'cash', amount: 0, payment_account_id: '', reference: '' },
      { method: 'transfer', amount: 0, payment_account_id: '', reference: '' },
    ];
  }
}

function addPaymentLine() {
  paymentLines.value.push({ method: 'cash', amount: 0, payment_account_id: '', reference: '' });
}

function removePaymentLine(index: number) {
  if (paymentLines.value.length <= 2) return;
  paymentLines.value.splice(index, 1);
}

function startNewSale() {
  successOpen.value = false;
  paymentOpen.value = false;
  selectedCustomerId.value = '';
  paymentLines.value = [];
  selectedAccountId.value = '';
  reference.value = '';
  notice.value = '';
}

function printReceipt() {
  window.print();
}

function defaultDueDate() {
  const date = new Date();
  date.setDate(date.getDate() + 7);
  return date.toISOString().slice(0, 10);
}
</script>

<template>
  <div class="mx-auto grid min-h-[calc(100dvh-6rem)] w-full max-w-[1320px] gap-4 lg:grid-cols-[minmax(0,1fr)_352px]">
    <section class="min-w-0">
      <div class="mb-4 grid gap-3 xl:grid-cols-[minmax(0,1fr)_352px]">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Search products..."
            class="h-11 w-full rounded-[10px] border border-slate-200 bg-white pl-10 pr-4 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-900 dark:text-white dark:focus:ring-blue-500/20"
          >
        </div>

      </div>

      <p v-if="productStore.error || customerStore.error || cartStore.error" class="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
        {{ productStore.error || customerStore.error || cartStore.error }}
      </p>

      <div v-if="filteredProducts.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <button
          v-for="product in filteredProducts"
          :key="product.id"
          type="button"
          class="group min-h-36 rounded-[14px] border border-slate-200 bg-white p-4 text-left shadow-[0_1px_4px_rgba(15,23,42,0.14)] transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-[0_10px_24px_rgba(37,99,235,0.10)] dark:border-white/[0.07] dark:bg-gray-900"
          @click="addProduct(product)"
        >
          <div class="mb-4 grid h-10 place-items-center rounded-[10px] bg-blue-50 text-blue-500 transition group-hover:bg-blue-100">
            <Package class="h-5 w-5" />
          </div>
          <div class="flex min-h-20 flex-col justify-end">
            <h3 class="line-clamp-2 text-sm font-semibold text-slate-950 dark:text-white">{{ product.name }}</h3>
            <p class="mt-1 text-sm font-bold text-blue-600">{{ money(product.selling_price) }}</p>
            <div class="mt-2 flex items-center justify-between gap-3">
              <p class="truncate text-xs font-medium text-blue-300">{{ productStock(product) }}</p>
              <span v-if="product.is_low_stock" class="shrink-0 text-xs font-bold text-orange-600">Low</span>
            </div>
          </div>
        </button>
      </div>

      <div v-else class="flex min-h-[42dvh] flex-col items-center justify-center gap-4 rounded-[14px] border border-dashed border-slate-200 bg-white px-4 text-center dark:border-white/[0.08] dark:bg-gray-900">
        <div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-50">
          <Package class="h-6 w-6 text-blue-500" />
        </div>
        <div>
          <p class="text-sm font-semibold text-slate-900 dark:text-white">No products found</p>
          <p class="mt-1 text-xs font-medium text-slate-400">Try a different search or add active products first.</p>
        </div>
      </div>
    </section>

    <aside
      :class="[
        'fixed inset-x-0 bottom-[4.5rem] z-30 border-t border-slate-200 bg-white shadow-[0_-18px_34px_rgba(15,23,42,0.16)] transition-transform duration-200 dark:border-white/[0.07] dark:bg-gray-900 lg:sticky lg:inset-x-auto lg:bottom-auto lg:top-20 lg:z-auto lg:flex lg:h-[calc(100dvh-6.5rem)] lg:translate-y-0 lg:flex-col lg:border lg:shadow-none',
        checkoutOpen || cartItems.length ? 'translate-y-0' : 'translate-y-[calc(100%-4.75rem)]',
      ]"
    >
      <button type="button" class="flex w-full items-center justify-between border-b border-slate-100 px-4 py-3 text-left lg:hidden dark:border-white/[0.06]" @click="checkoutOpen = !checkoutOpen">
        <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-950 dark:text-white">
          <ShoppingCart class="h-5 w-5 text-blue-600" />
          Cart
          <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{{ itemCount }}</span>
        </span>
        <span class="text-sm font-bold text-blue-600">{{ money(total) }}</span>
      </button>

      <div class="flex min-h-0 flex-1 flex-col">
        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
          <div v-if="cartItems.length" class="space-y-4">
            <article v-for="item in cartItems" :key="item.id" class="grid grid-cols-[minmax(0,1fr)_auto] gap-3">
              <div class="min-w-0">
                <h3 class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ item.product_name }}</h3>
                <p class="mt-1 text-sm font-bold text-blue-600">{{ money(item.unit_price) }}</p>
              </div>
              <div class="grid justify-items-end gap-2">
                <p class="text-sm font-bold text-slate-950 dark:text-white">{{ money(item.line_total) }}</p>
                <div class="flex items-center gap-2">
                  <button type="button" class="grid h-8 w-8 place-items-center rounded-lg bg-slate-100 text-slate-500 dark:bg-white/[0.06]" aria-label="Decrease quantity" @click="decrease(item.product_id, item.quantity)">
                    <Minus class="h-4 w-4" />
                  </button>
                  <span class="grid h-8 min-w-8 place-items-center text-sm font-bold text-slate-950 dark:text-white">{{ item.quantity }}</span>
                  <button type="button" class="grid h-8 w-8 place-items-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10" aria-label="Increase quantity" @click="increase(item.product_id, item.quantity)">
                    <Plus class="h-4 w-4" />
                  </button>
                  <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-rose-500 hover:bg-rose-50" aria-label="Remove item" @click="removeItem(item.product_id)">
                    <Trash2 class="h-4 w-4" />
                  </button>
                </div>
              </div>
            </article>
          </div>

          <div v-else class="flex h-full min-h-40 flex-col items-center justify-center text-center">
            <ShoppingCart class="h-8 w-8 text-slate-300" />
            <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">No items selected</p>
          </div>
        </div>

        <div class="border-t border-slate-100 px-4 pb-[max(env(safe-area-inset-bottom),1rem)] pt-4 dark:border-white/[0.06]">
          <div class="space-y-3">
            <div class="flex items-center justify-between text-sm">
              <span class="font-medium text-slate-500">Subtotal</span>
              <strong class="text-slate-950 dark:text-white">{{ money(subtotal) }}</strong>
            </div>
            <label class="flex items-center justify-between gap-3 text-sm">
              <span class="font-medium text-slate-500">Discount</span>
              <span class="flex items-center gap-1">
                <span class="text-slate-400">N</span>
                <input v-model.number="discount" type="number" min="0" class="h-9 w-24 rounded-lg border border-slate-200 px-3 text-right text-sm font-medium outline-none dark:border-white/[0.08] dark:bg-gray-950" @change="updateCartMeta">
              </span>
            </label>
            <div class="flex items-center justify-between border-t border-slate-100 pt-3 text-base dark:border-white/[0.06]">
              <span class="font-bold text-slate-950 dark:text-white">Total</span>
              <strong class="text-blue-600">{{ money(total) }}</strong>
            </div>
          </div>

          <p v-if="notice" class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">{{ notice }}</p>

          <button type="button" :disabled="!cartItems.length || cartStore.loading" class="mt-4 flex h-12 w-full items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white shadow-soft transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50" @click="openPayment">
            Complete Sale
          </button>
        </div>
      </div>
    </aside>

    <div v-if="paymentOpen" class="fixed inset-0 z-50 grid place-items-end bg-black/45 px-0 sm:place-items-center sm:p-4">
      <form class="max-h-[92dvh] w-full overflow-y-auto rounded-t-[20px] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.28)] dark:bg-gray-900 sm:max-w-md sm:rounded-[20px]" @submit.prevent="submitSale">
        <div class="mb-5 flex items-center justify-between gap-3">
          <h2 class="text-lg font-bold text-slate-950 dark:text-white">Complete Payment</h2>
          <button type="button" class="grid h-9 w-9 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/[0.06]" aria-label="Close payment" @click="paymentOpen = false">
            <X class="h-5 w-5" />
          </button>
        </div>

        <p v-if="salesStore.error" class="mb-4 rounded-lg bg-rose-50 px-3 py-3 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
          {{ salesStore.error }}
        </p>

        <div class="rounded-[14px] bg-slate-50 p-4 dark:bg-white/[0.04]">
          <div class="flex items-center justify-between text-sm">
            <span class="font-medium text-slate-500">Subtotal</span>
            <strong class="text-slate-950 dark:text-white">{{ money(subtotal) }}</strong>
          </div>
          <div class="mt-3 flex items-center justify-between text-sm">
            <span class="font-medium text-slate-500">Discount</span>
            <strong class="text-rose-600">-{{ money(discount || 0) }}</strong>
          </div>
          <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3 text-base dark:border-white/[0.08]">
            <span class="font-bold text-slate-950 dark:text-white">Total</span>
            <strong class="text-slate-950 dark:text-white">{{ money(total) }}</strong>
          </div>
        </div>

        <div class="mt-5">
          <p class="text-sm font-bold text-slate-900 dark:text-white">Payment Method</p>
          <div class="mt-3 grid grid-cols-3 gap-2">
            <button
              v-for="method in paymentMethods"
              :key="method.value"
              type="button"
              :class="['h-10 rounded-lg border text-sm font-bold transition', paymentMethod === method.value ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/[0.08] dark:bg-gray-950 dark:text-slate-200']"
              @click="changePaymentMethod(method.value)"
            >
              {{ method.label }}
            </button>
          </div>
        </div>

        <div v-if="creditNeedsCustomer" class="mt-4 flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-3 text-sm font-semibold text-amber-700">
          <AlertTriangle class="h-4 w-4 shrink-0" />
          A customer must be selected for credit sales
        </div>

        <div class="mt-4">
          <SearchableSelect
            v-model="selectedCustomerId"
            label="Customer"
            :options="customerOptions"
            placeholder="Select customer"
            search-placeholder="Search customers by name, phone, or email..."
            empty-text="No customers match your search"
          />
        </div>

        <p class="mt-2 text-xs font-medium text-slate-400">Leave this as Walk-in Customer for a sale with no customer balance.</p>

        <label v-if="paymentMethod !== 'split'" class="mt-4 grid gap-2 text-sm font-bold text-slate-900 dark:text-white">
          Amount Paid Now
          <input v-model.number="amountPaid" type="number" min="0" :max="total" class="h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-950">
        </label>

        <div v-if="paymentMethod === 'transfer' || paymentMethod === 'pos'" class="mt-4 grid gap-3">
          <label class="grid gap-2 text-sm font-bold text-slate-900 dark:text-white">
            {{ paymentMethod === 'pos' ? 'POS machine' : 'Paid to account' }}
            <select v-model="selectedAccountId" class="h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold outline-none dark:border-white/[0.08] dark:bg-gray-950">
              <option value="">Select destination</option>
              <option v-for="account in accountOptions" :key="account.id" :value="account.id">
                {{ account.name }}{{ account.provider ? ` · ${account.provider}` : '' }}{{ account.account_number ? ` · ${account.account_number}` : account.terminal_id ? ` · ${account.terminal_id}` : '' }}
              </option>
            </select>
          </label>
          <label class="grid gap-2 text-sm font-bold text-slate-900 dark:text-white">
            Reference (optional)
            <input v-model="reference" type="text" placeholder="Transfer reference or POS receipt no." class="h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold outline-none dark:border-white/[0.08] dark:bg-gray-950">
          </label>
        </div>

        <div v-if="paymentMethod === 'split'" class="mt-4 grid gap-3">
          <div v-for="(line, index) in paymentLines" :key="index" class="rounded-xl border border-slate-200 p-3 dark:border-white/[0.08]">
            <div class="flex items-center justify-between gap-3">
              <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Payment {{ index + 1 }}</p>
              <button v-if="paymentLines.length > 2" type="button" class="text-xs font-bold text-rose-600" @click="removePaymentLine(index)">Remove</button>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
              <select v-model="line.method" class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold outline-none dark:border-white/[0.08] dark:bg-gray-950">
                <option value="cash">Cash</option>
                <option value="transfer">Transfer</option>
                <option value="pos">POS</option>
              </select>
              <input v-model.number="line.amount" type="number" min="0" :max="total" placeholder="Amount" class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold outline-none dark:border-white/[0.08] dark:bg-gray-950">
            </div>
            <select v-if="line.method !== 'cash'" v-model="line.payment_account_id" class="mt-3 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold outline-none dark:border-white/[0.08] dark:bg-gray-950">
              <option value="">Select {{ line.method === 'pos' ? 'POS machine' : 'account' }}</option>
              <option v-for="account in splitAccountOptions(line.method)" :key="account.id" :value="account.id">{{ account.name }}{{ account.provider ? ` · ${account.provider}` : '' }}{{ account.account_number ? ` · ${account.account_number}` : account.terminal_id ? ` · ${account.terminal_id}` : '' }}</option>
            </select>
            <input v-if="line.method !== 'cash'" v-model="line.reference" type="text" placeholder="Reference (optional)" class="mt-3 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold outline-none dark:border-white/[0.08] dark:bg-gray-950">
          </div>
          <button type="button" class="h-10 rounded-lg border border-dashed border-blue-300 text-sm font-bold text-blue-600" @click="addPaymentLine">+ Add another payment method</button>
        </div>

        <div class="mt-4 flex items-center justify-between text-sm">
          <span class="font-medium text-slate-500">Balance remaining</span>
          <strong :class="balanceRemaining > 0 ? 'text-rose-600' : 'text-emerald-600'">{{ money(balanceRemaining) }}</strong>
        </div>

        <label v-if="needsDueDate" class="mt-4 grid gap-2 text-sm font-bold text-slate-900 dark:text-white">
          Due Date
          <input v-model="dueDate" type="date" class="h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-950">
        </label>

        <button type="submit" :disabled="!canSubmitPayment || salesStore.loading" class="mt-5 flex h-12 w-full items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white shadow-soft transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
          {{ salesStore.loading ? 'Completing...' : 'Complete Sale' }}
        </button>
      </form>
    </div>

    <BaseModal :show="successOpen" title="Sale completed" @close="startNewSale">
      <div class="grid justify-items-center gap-3 text-center">
        <div class="grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
          <CheckCircle2 class="h-7 w-7" />
        </div>
        <div>
          <p class="text-lg font-bold text-slate-950 dark:text-white">Payment recorded</p>
          <p class="mt-1 text-sm font-medium text-slate-500">{{ salesStore.lastSale?.order_number }}</p>
        </div>
        <div class="w-full rounded-xl bg-slate-50 p-4 text-left text-sm dark:bg-white/[0.04]">
          <div class="flex justify-between gap-3"><span class="text-slate-500">Total</span><strong>{{ money(salesStore.lastSale?.total ?? 0) }}</strong></div>
          <div class="mt-2 flex justify-between gap-3"><span class="text-slate-500">Paid</span><strong>{{ money(salesStore.lastSale?.amount_paid ?? 0) }}</strong></div>
          <div class="mt-2 flex justify-between gap-3"><span class="text-slate-500">Balance</span><strong>{{ money(salesStore.lastSale?.balance_due ?? 0) }}</strong></div>
        </div>
        <div v-if="salesStore.lastSale?.payments.length" class="w-full text-left">
          <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Payment details</p>
          <div v-for="payment in salesStore.lastSale.payments" :key="payment.id" class="mt-2 flex justify-between gap-3 text-xs">
            <span class="font-semibold capitalize">{{ payment.method }}<span v-if="payment.account"> · {{ payment.account.name }}</span></span>
            <strong>{{ money(payment.amount) }}</strong>
          </div>
        </div>
        <div class="grid w-full gap-2 sm:grid-cols-2">
          <button type="button" class="h-11 rounded-lg border border-slate-200 text-sm font-bold text-slate-700 dark:border-white/[0.08] dark:text-slate-200" @click="printReceipt">Print receipt</button>
          <button type="button" class="h-11 rounded-lg bg-blue-600 text-sm font-bold text-white" @click="startNewSale">New sale</button>
        </div>
      </div>
    </BaseModal>

    <section v-if="salesStore.lastSale" class="print-only bg-white p-6 text-black">
      <h1 class="text-xl font-bold">{{ auth.user?.business?.name ?? 'TradeNest' }}</h1>
      <p class="mt-1 text-sm">{{ salesStore.lastSale.order_number }} · {{ new Date(salesStore.lastSale.created_at).toLocaleString('en-NG') }}</p>
      <p v-if="salesStore.lastSale.customer" class="mt-3 text-sm">Customer: {{ salesStore.lastSale.customer.name }}</p>
      <div class="mt-5 border-y border-black py-3 text-sm">
        <div v-for="item in salesStore.lastSale.items" :key="item.id" class="flex justify-between gap-4 py-1">
          <span>{{ item.quantity }} × {{ item.product_name }}</span>
          <span>{{ money(item.line_total) }}</span>
        </div>
      </div>
      <div class="mt-4 space-y-1 text-sm">
        <div class="flex justify-between"><span>Subtotal</span><span>{{ money(salesStore.lastSale.subtotal) }}</span></div>
        <div class="flex justify-between"><span>Discount</span><span>-{{ money(salesStore.lastSale.discount) }}</span></div>
        <div class="flex justify-between border-t border-black pt-2 font-bold"><span>Total</span><span>{{ money(salesStore.lastSale.total) }}</span></div>
        <div class="flex justify-between"><span>Paid</span><span>{{ money(salesStore.lastSale.amount_paid) }}</span></div>
        <div class="flex justify-between"><span>Balance</span><span>{{ money(salesStore.lastSale.balance_due) }}</span></div>
      </div>
      <div v-if="salesStore.lastSale.payments.length" class="mt-5 border-t border-black pt-3 text-xs">
        <p class="font-bold">Payment details</p>
        <p v-for="payment in salesStore.lastSale.payments" :key="payment.id" class="mt-1">
          {{ payment.method.toUpperCase() }}: {{ money(payment.amount) }}<span v-if="payment.account"> · {{ payment.account.name }}</span><span v-if="payment.reference"> · Ref: {{ payment.reference }}</span>
        </p>
      </div>
    </section>
  </div>
</template>
