<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { PackagePlus, Pencil, Plus, Search } from 'lucide-vue-next';
import { useProductStore, type ProductForm, type StockAdjustmentForm } from '../../stores/products';
import type { Product } from '../../types';
import BaseModal from '../../components/ui/BaseModal.vue';
import TextField from '../../components/ui/TextField.vue';

const productStore = useProductStore();

const search = ref('');
const showProductModal = ref(false);
const showStockModal = ref(false);
const selectedProductId = ref<string | null>(null);

const productForm = reactive<ProductForm>({
  name: '',
  sku: '',
  category: '',
  unit: 'piece',
  cost_price: '',
  selling_price: '',
  quantity: '',
  reorder_level: '',
});

const stockForm = reactive<StockAdjustmentForm>({
  type: 'manual_adjustment',
  quantity: '',
  reorder_level: '',
  reason: '',
});

const selectedProduct = computed(() => productStore.products.find((product) => product.id === selectedProductId.value) ?? null);

const filteredProducts = computed(() => {
  const query = search.value.trim().toLowerCase();

  if (!query) {
    return productStore.products;
  }

  return productStore.products.filter((product) =>
    [product.name, product.sku, product.category, product.unit].some((field) => field?.toLowerCase().includes(query)),
  );
});

const stockTypes: Array<{ value: StockAdjustmentForm['type']; label: string }> = [
  { value: 'purchase', label: 'Purchase' },
  { value: 'return', label: 'Return' },
  { value: 'damage', label: 'Damage' },
  { value: 'correction', label: 'Correction' },
  { value: 'manual_adjustment', label: 'Manual Adjustment' },
];

onMounted(() => {
  productStore.fetchProducts();
});

watch(selectedProduct, (product) => {
  if (!product) {
    resetProductForm();
    return;
  }

  productForm.name = product.name;
  productForm.sku = product.sku ?? '';
  productForm.category = product.category ?? '';
  productForm.unit = product.unit;
  productForm.cost_price = product.cost_price;
  productForm.selling_price = product.selling_price;
  productForm.quantity = String(product.quantity);
  productForm.reorder_level = String(product.reorder_level);

  stockForm.reorder_level = String(product.reorder_level);
});

function money(value: string | number) {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(value));
}

function stockStatus(product: Product) {
  return product.is_low_stock ? 'Low Stock' : 'In Stock';
}

function resetProductForm() {
  productForm.name = '';
  productForm.sku = '';
  productForm.category = '';
  productForm.unit = 'piece';
  productForm.cost_price = '';
  productForm.selling_price = '';
  productForm.quantity = '';
  productForm.reorder_level = '';
}

function resetStockForm(product?: Product | null) {
  stockForm.type = 'manual_adjustment';
  stockForm.quantity = '';
  stockForm.reorder_level = product ? String(product.reorder_level) : '';
  stockForm.reason = '';
}

function openProductModal(product?: Product) {
  selectedProductId.value = product?.id ?? null;

  if (!product) {
    resetProductForm();
  }

  showProductModal.value = true;
}

function closeProductModal() {
  showProductModal.value = false;
  selectedProductId.value = null;
}

function openStockModal(product: Product) {
  selectedProductId.value = product.id;
  resetStockForm(product);
  showStockModal.value = true;
}

function closeStockModal() {
  showStockModal.value = false;
  selectedProductId.value = null;
  resetStockForm();
}

async function submitProduct() {
  const success = selectedProduct.value
    ? await productStore.updateProduct(selectedProduct.value.id, productForm)
    : await productStore.createProduct(productForm);

  if (success) {
    closeProductModal();
  }
}

async function submitStock() {
  if (!selectedProduct.value) {
    return;
  }

  const success = await productStore.adjustStock(selectedProduct.value.id, stockForm);

  if (success) {
    closeStockModal();
  }
}

</script>

<template>
  <div class="flex h-full flex-col">
    <div class="flex-1 overflow-auto">
      <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center">
        <div class="relative min-w-0 flex-1">
          <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Search products..."
            class="h-11 w-full rounded-[10px] border border-slate-200 bg-white pl-10 pr-4 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-800 dark:text-white dark:focus:ring-blue-500/20"
          >
        </div>
        <button
          type="button"
          class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-[10px] bg-blue-600 px-5 text-sm font-semibold text-white shadow-soft transition hover:bg-blue-700 md:w-auto"
          @click="openProductModal()"
        >
          <Plus class="h-4 w-4" />
          Add Product
        </button>
      </div>

      <p v-if="productStore.error" class="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
        {{ productStore.error }}
      </p>

      <template v-if="filteredProducts.length">
        <div class="grid gap-3 md:hidden">
          <article
            v-for="product in filteredProducts"
            :key="product.id"
            class="rounded-[14px] border border-slate-200 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.12)] dark:border-white/[0.07] dark:bg-gray-800"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ product.name }}</h3>
                </div>
                <p class="mt-1 text-xs font-medium text-blue-300">{{ product.unit }}</p>
              </div>
              <span :class="['min-w-28 rounded-full border px-3 py-1 text-center text-xs font-semibold leading-none', product.is_low_stock ? 'border-amber-300 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700']">
                {{ stockStatus(product) }}
              </span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
              <div>
                <p class="text-slate-400">Category</p>
                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ product.category ?? '-' }}</p>
              </div>
              <div>
                <p class="text-slate-400">Stock</p>
                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ product.quantity }}</p>
              </div>
              <div>
                <p class="text-slate-400">Cost Price</p>
                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ money(product.cost_price) }}</p>
              </div>
              <div>
                <p class="text-slate-400">Selling Price</p>
                <p class="mt-1 font-semibold text-blue-600">{{ money(product.selling_price) }}</p>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 dark:border-white/[0.06]">
              <button type="button" class="flex h-9 items-center justify-center gap-1 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700" @click="openProductModal(product)">
                <Pencil class="h-3.5 w-3.5" /> Edit
              </button>
              <button type="button" class="flex h-9 items-center justify-center gap-1 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700" @click="openStockModal(product)">
                <Plus class="h-3.5 w-3.5" /> Stock
              </button>
            </div>
          </article>
        </div>

        <div class="hidden overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.16)] dark:border-white/[0.07] dark:bg-gray-800 md:block">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-slate-100 dark:border-white/[0.06]">
                <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Product</th>
                <th class="px-4 py-4 text-left text-xs font-bold uppercase text-slate-500">Category</th>
                <th class="px-4 py-4 text-left text-xs font-bold uppercase text-slate-500">Cost Price</th>
                <th class="px-4 py-4 text-left text-xs font-bold uppercase text-slate-500">Selling Price</th>
                <th class="px-4 py-4 text-left text-xs font-bold uppercase text-slate-500">Stock</th>
                <th class="px-4 py-4 text-left text-xs font-bold uppercase text-slate-500">Status</th>
                <th class="py-4 pl-4 pr-5 text-right text-xs font-bold uppercase text-slate-500">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/[0.04]">
              <tr v-for="product in filteredProducts" :key="product.id" class="transition-colors hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                <td class="px-5 py-4">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-950 dark:text-gray-100">{{ product.name }}</p>
                    <p class="mt-1 truncate text-xs font-medium text-blue-300">{{ product.unit }}</p>
                  </div>
                </td>
                <td class="px-4 py-4 text-sm font-medium text-slate-700 dark:text-gray-300">{{ product.category ?? '-' }}</td>
                <td class="px-4 py-4 text-sm font-semibold text-slate-950 dark:text-gray-100">{{ money(product.cost_price) }}</td>
                <td class="px-4 py-4 text-sm font-semibold text-blue-600">{{ money(product.selling_price) }}</td>
                <td class="px-4 py-4 text-sm font-semibold text-slate-950 dark:text-gray-100">{{ product.quantity }}</td>
                <td class="px-4 py-4">
                  <span :class="['inline-flex h-6 min-w-36 items-center rounded-full border px-3 text-xs font-semibold leading-none', product.is_low_stock ? 'border-amber-300 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700']">
                    {{ stockStatus(product) }}
                  </span>
                </td>
                <td class="py-4 pl-4 pr-5 text-right">
                  <div class="flex items-center justify-end gap-4">
                    <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800" aria-label="Edit product" @click="openProductModal(product)">
                      <Pencil class="h-3.5 w-3.5" />
                    </button>
                    <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-blue-500 transition-colors hover:bg-blue-50 hover:text-blue-700" aria-label="Adjust stock" @click="openStockModal(product)">
                      <Plus class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <div v-else class="flex flex-col items-center justify-center gap-4 py-24">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-gray-200 bg-gray-100 dark:border-white/[0.08] dark:bg-white/[0.04]">
          <PackagePlus class="h-5 w-5 text-gray-400" />
        </div>
        <div class="text-center">
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ search ? 'No products found' : 'No products yet' }}</p>
          <p class="mt-1 text-xs text-gray-400">{{ search ? 'Try a different search term' : 'Click New Product to start your stock list' }}</p>
        </div>
      </div>
    </div>

    <BaseModal :show="showProductModal" :title="selectedProduct ? 'Edit Product' : 'New Product'" @close="closeProductModal">
      <form class="space-y-4" @submit.prevent="submitProduct">
        <div class="grid gap-4 sm:grid-cols-2">
          <TextField v-model="productForm.name" class="sm:col-span-2" label="Product name" required />
          <TextField v-model="productForm.sku" label="SKU" />
          <TextField v-model="productForm.category" label="Category" placeholder="Cement, Iron, Paint" />
          <TextField v-model="productForm.unit" label="Unit" placeholder="piece, bag, carton" required />
          <TextField v-model="productForm.cost_price" label="Cost price" type="number" required />
          <TextField v-model="productForm.selling_price" label="Selling price" type="number" required />
          <TextField v-if="!selectedProduct" v-model="productForm.quantity" label="Opening quantity" type="number" />
          <TextField v-if="!selectedProduct" v-model="productForm.reorder_level" label="Low-stock level" type="number" />
        </div>

        <p v-if="productStore.error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ productStore.error }}</p>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-white/[0.06]">
          <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50" @click="closeProductModal">
            Cancel
          </button>
          <button type="submit" :disabled="productStore.loading" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50">
            {{ productStore.loading ? 'Saving...' : selectedProduct ? 'Update Product' : 'Create Product' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <BaseModal :show="showStockModal" :title="selectedProduct ? `Adjust ${selectedProduct.name}` : 'Adjust Stock'" @close="closeStockModal">
      <form class="space-y-4" @submit.prevent="submitStock">
        <label class="grid gap-2 text-sm font-semibold text-gray-900 dark:text-gray-200">
          Movement type
          <select v-model="stockForm.type" class="field">
            <option v-for="type in stockTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
          </select>
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
          <TextField v-model="stockForm.quantity" label="Quantity" type="number" required />
          <TextField v-model="stockForm.reorder_level" label="Low-stock level" type="number" />
        </div>
        <TextField v-model="stockForm.reason" label="Reason" placeholder="e.g. New delivery, damaged bags" />

        <p v-if="productStore.error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ productStore.error }}</p>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-white/[0.06]">
          <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50" @click="closeStockModal">
            Cancel
          </button>
          <button type="submit" :disabled="productStore.loading" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50">
            {{ productStore.loading ? 'Saving...' : 'Update Stock' }}
          </button>
        </div>
      </form>
    </BaseModal>

  </div>
</template>
