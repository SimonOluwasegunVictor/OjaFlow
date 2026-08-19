<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { AlertTriangle, Minus, Package, Plus, Search, ShoppingCart, Trash2, X } from 'lucide-vue-next';
import { useCartStore } from '../../stores/cart';
import { useCustomerStore } from '../../stores/customers';
import { useProductStore } from '../../stores/products';
import { useSalesStore } from '../../stores/sales';
import SearchableSelect from '../../components/ui/SearchableSelect.vue';
import type { Product, SalePaymentMethod } from '../../types';

interface SearchableSelectOption {
  value: string;
  label: string;
  description?: string | null;
}

const cartStore = useCartStore();
const productStore = useProductStore();
const customerStore = useCustomerStore();
const salesStore = useSalesStore();
const search = ref('');
const selectedCustomerId = ref('');
const discount = ref(0);
const checkoutOpen = ref(false);
const paymentOpen = ref(false);
const paymentMethod = ref<SalePaymentMethod>('cash');
const amountPaid = ref(0);
const dueDate = ref(defaultDueDate());
const notice = ref('');

onMounted(async () => {
  await Promise.all([
    productStore.fetchProducts({ status: 'active' }),
    customerStore.fetchCustomers(),
    cartStore.fetchCart(),
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
const balanceRemaining = computed(() => Math.max(0, total.value - Number(amountPaid.value || 0)));
const selectedCustomer = computed(() => customerStore.customers.find((customer) => customer.id === selectedCustomerId.value) ?? null);
const customerOptions = computed<SearchableSelectOption[]>(() => [
  { value: '', label: 'Walk-in Customer', description: 'No credit balance will be assigned' },
  ...customerStore.customers.map((customer) => ({
    value: customer.id,
    label: customer.name,
    description: customer.phone ?? customer.email ?? 'Customer record',
  })),
]);
const customerSelectionRequired = computed(() => ['credit', 'split'].includes(paymentMethod.value) && balanceRemaining.value > 0);
const creditNeedsCustomer = computed(() => customerSelectionRequired.value && !selectedCustomer.value);
const needsDueDate = computed(() => balanceRemaining.value > 0);
const canSubmitPayment = computed(() => Boolean(cartStore.cart?.id) && cartItems.value.length > 0 && !creditNeedsCustomer.value && (!needsDueDate.value || Boolean(dueDate.value)));
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
  await cartStore.updateCart({
    customer_id: selectedCustomerId.value || null,
    discount: Number(discount.value || 0),
  });
  syncCartFields();
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

  await updateCartMeta();
  amountPaid.value = total.value;
  paymentMethod.value = selectedCustomer.value ? 'cash' : 'cash';
  dueDate.value = defaultDueDate();
  paymentOpen.value = true;
  notice.value = '';
}

async function submitSale() {
  if (!canSubmitPayment.value) {
    return;
  }

  await updateCartMeta();

  if (!cartStore.cart?.id) {
    return;
  }

  const success = await salesStore.completeSale({
    cart_id: cartStore.cart.id,
    payment_method: paymentMethod.value,
    amount_paid: Number(amountPaid.value || 0),
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
  await Promise.all([
    productStore.fetchProducts({ status: 'active' }).catch(() => undefined),
    customerStore.fetchCustomers().catch(() => undefined),
    cartStore.fetchCart().catch(() => undefined),
  ]);
  syncCartFields();
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

        <SearchableSelect
          v-model="selectedCustomerId"
          :options="customerOptions"
          placeholder="Walk-in Customer"
          search-placeholder="Search customers..."
          @change="updateCartMeta"
        />
      </div>

      <p v-if="productStore.error || customerStore.error || cartStore.error || salesStore.error" class="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
        {{ productStore.error || customerStore.error || cartStore.error || salesStore.error }}
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
        <div class="hidden border-b border-slate-100 px-4 py-4 lg:block dark:border-white/[0.06]">
          <SearchableSelect
            v-model="selectedCustomerId"
            :options="customerOptions"
            placeholder="Walk-in Customer"
            search-placeholder="Search customers..."
            @change="updateCartMeta"
          />
        </div>

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
              @click="paymentMethod = method.value; amountPaid = method.value === 'credit' ? 0 : amountPaid"
            >
              {{ method.label }}
            </button>
          </div>
        </div>

        <div v-if="creditNeedsCustomer" class="mt-4 flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-3 text-sm font-semibold text-amber-700">
          <AlertTriangle class="h-4 w-4 shrink-0" />
          A customer must be selected for credit sales
        </div>

        <div v-if="customerSelectionRequired" class="mt-4">
          <SearchableSelect
            v-model="selectedCustomerId"
            label="Customer"
            :options="customerOptions"
            placeholder="Select customer"
            search-placeholder="Search customers by name, phone, or email..."
            empty-text="No customers match your search"
          />
        </div>

        <label class="mt-4 grid gap-2 text-sm font-bold text-slate-900 dark:text-white">
          Amount Paid Now
          <input v-model.number="amountPaid" type="number" min="0" :max="total" class="h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-950">
        </label>

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
  </div>
</template>
