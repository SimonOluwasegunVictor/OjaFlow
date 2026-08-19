<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Boxes, Filter, PackageSearch } from 'lucide-vue-next';
import { useProductStore } from '../../stores/products';
import type { StockMovement, StockMovementType } from '../../types';

const productStore = useProductStore();
const selectedType = ref<StockMovementType | 'all'>('all');

const movementTypes: Array<{ value: StockMovementType | 'all'; label: string }> = [
  { value: 'all', label: 'All' },
  { value: 'purchase', label: 'Stock In' },
  { value: 'sale', label: 'Sale' },
  { value: 'damage', label: 'Damage' },
  { value: 'correction', label: 'Adjustment' },
  { value: 'manual_adjustment', label: 'Manual' },
  { value: 'return', label: 'Return' },
];

const movements = computed(() => productStore.stockMovements);

onMounted(async () => {
  await productStore.fetchStockMovements().catch(() => undefined);
});

async function filterMovements(type: StockMovementType | 'all') {
  selectedType.value = type;
  await productStore.fetchStockMovements(type === 'all' ? {} : { type });
}

function movementLabel(type: StockMovementType) {
  const labels: Record<StockMovementType, string> = {
    purchase: 'Stock In',
    sale: 'Sale',
    return: 'Return',
    damage: 'Damage',
    correction: 'Adjustment',
    manual_adjustment: 'Adjustment',
  };

  return labels[type];
}

function movementTone(type: StockMovementType) {
  if (type === 'purchase' || type === 'return') {
    return 'border-emerald-200 bg-emerald-50 text-emerald-700';
  }

  if (type === 'sale') {
    return 'border-blue-200 bg-blue-50 text-blue-700';
  }

  if (type === 'damage') {
    return 'border-rose-200 bg-rose-50 text-rose-700';
  }

  return 'border-amber-200 bg-amber-50 text-amber-700';
}

function quantityDelta(movement: StockMovement) {
  const difference = movement.new_quantity - movement.previous_quantity;

  if (difference === 0) {
    return '0';
  }

  return `${difference > 0 ? '+' : ''}${difference}`;
}

function deltaTone(movement: StockMovement) {
  const difference = movement.new_quantity - movement.previous_quantity;

  if (difference > 0) {
    return 'text-emerald-600';
  }

  if (difference < 0) {
    return 'text-rose-600';
  }

  return 'text-slate-500';
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: '2-digit',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(value));
}

const visibleMovements = computed(() => movements.value);
</script>

<template>
  <div class="mx-auto w-full max-w-[1320px]">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Stock Movement</h2>
        <p class="mt-1 text-sm font-medium text-blue-300">All stock changes across products</p>
      </div>

      <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:justify-end">
        <button
          v-for="type in movementTypes"
          :key="type.value"
          type="button"
          :class="['inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border px-3 text-sm font-semibold shadow-sm transition', selectedType === type.value ? 'border-blue-500 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/[0.08] dark:bg-gray-900 dark:text-gray-200']"
          @click="filterMovements(type.value)"
        >
          <Filter v-if="type.value === 'all'" class="h-4 w-4" />
          {{ type.label }}
        </button>
      </div>
    </div>

    <p v-if="productStore.error" class="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
      {{ productStore.error }}
    </p>

    <section v-if="visibleMovements.length" class="overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-[0_1px_4px_rgba(15,23,42,0.14)] dark:border-white/[0.07] dark:bg-gray-900">
      <article
        v-for="movement in visibleMovements"
        :key="movement.id"
        class="grid gap-3 border-b border-slate-100 px-4 py-4 last:border-b-0 dark:border-white/[0.06] sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center lg:px-5"
      >
        <div class="min-w-0">
          <div class="flex items-start gap-3">
            <div class="hidden h-10 w-10 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-500 sm:grid">
              <Boxes class="h-5 w-5" />
            </div>
            <div class="min-w-0">
              <h3 class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ movement.product?.name ?? 'Unknown Product' }}</h3>
              <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                <span>Before: <strong class="text-slate-950 dark:text-white">{{ movement.previous_quantity }}</strong></span>
                <span>After: <strong class="text-slate-950 dark:text-white">{{ movement.new_quantity }}</strong></span>
                <span :class="['font-bold', deltaTone(movement)]">{{ quantityDelta(movement) }}</span>
                <span>By: <strong class="text-slate-950 dark:text-white">{{ movement.user?.name ?? 'System' }}</strong></span>
                <span>{{ formatDate(movement.created_at) }}</span>
              </div>
              <p class="mt-2 text-xs font-medium italic text-blue-300">{{ movement.reason ?? 'Stock movement recorded' }}</p>
            </div>
          </div>
        </div>

        <span :class="['inline-flex h-7 w-fit items-center rounded-full border px-3 text-xs font-semibold sm:justify-self-end', movementTone(movement.type)]">
          {{ movementLabel(movement.type) }}
        </span>
      </article>
    </section>

    <div v-else class="flex min-h-[50dvh] flex-col items-center justify-center gap-4 rounded-[14px] border border-dashed border-slate-200 bg-white px-4 text-center dark:border-white/[0.08] dark:bg-gray-900">
      <div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-50">
        <PackageSearch class="h-6 w-6 text-blue-500" />
      </div>
      <div>
        <p class="text-sm font-semibold text-slate-900 dark:text-white">No stock movement yet</p>
        <p class="mt-1 text-xs font-medium text-slate-400">Adjust stock or add opening stock to see activity here.</p>
      </div>
    </div>
  </div>
</template>
