<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { ChevronRight, Phone, Plus, Search, UserRound } from 'lucide-vue-next';
import BaseModal from '../../components/ui/BaseModal.vue';
import TextField from '../../components/ui/TextField.vue';
import { useCustomerStore, type CustomerForm } from '../../stores/customers';
import type { Customer } from '../../types';

const customerStore = useCustomerStore();
const search = ref('');
const showModal = ref(false);
const editingCustomer = ref<Customer | null>(null);

const form = reactive<CustomerForm>({
  name: '',
  phone: '',
  email: '',
  address: '',
});

const filteredCustomers = computed(() => {
  const query = search.value.trim().toLowerCase();

  if (!query) {
    return customerStore.customers;
  }

  return customerStore.customers.filter((customer) =>
    [customer.name, customer.phone, customer.email].some((field) => field?.toLowerCase().includes(query)),
  );
});

onMounted(() => {
  customerStore.fetchCustomers();
});

watch(search, (value) => {
  customerStore.fetchCustomers(value.trim() ? { search: value.trim() } : {});
});

function money(value: string | number) {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(value));
}

function initials(name: string) {
  return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('');
}

function lastPurchase(customer: Customer) {
  if (!customer.last_purchase_at) {
    return 'No purchases yet';
  }

  const date = new Date(customer.last_purchase_at);
  const today = new Date();
  const diffDays = Math.floor((today.getTime() - date.getTime()) / 86400000);

  if (diffDays <= 0) {
    return 'Today';
  }

  if (diffDays === 1) {
    return 'Yesterday';
  }

  if (diffDays < 7) {
    return `${diffDays} days ago`;
  }

  return `${Math.floor(diffDays / 7)} week${diffDays >= 14 ? 's' : ''} ago`;
}

function resetForm() {
  form.name = '';
  form.phone = '';
  form.email = '';
  form.address = '';
  editingCustomer.value = null;
}

function openAddModal() {
  resetForm();
  showModal.value = true;
}

function openEditModal(customer: Customer) {
  editingCustomer.value = customer;
  form.name = customer.name;
  form.phone = customer.phone ?? '';
  form.email = customer.email ?? '';
  form.address = customer.address ?? '';
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  resetForm();
}

async function submitCustomer() {
  const success = editingCustomer.value
    ? await customerStore.updateCustomer(editingCustomer.value.id, form)
    : await customerStore.createCustomer(form);

  if (success) {
    closeModal();
  }
}
</script>

<template>
  <div class="mx-auto w-full max-w-[1320px]">
    <div class="mb-4 flex gap-3">
      <div class="relative min-w-0 flex-1">
        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          v-model="search"
          type="text"
          placeholder="Search by name or phone..."
          class="h-11 w-full rounded-[10px] border border-slate-200 bg-white pl-10 pr-4 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-900 dark:text-white"
        >
      </div>
      <button type="button" class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-bold text-white shadow-soft hover:bg-blue-700" @click="openAddModal">
        <Plus class="h-4 w-4" />
        <span class="hidden sm:inline">Add</span>
      </button>
    </div>

    <p v-if="customerStore.error" class="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
      {{ customerStore.error }}
    </p>

    <div v-if="filteredCustomers.length" class="space-y-2.5">
      <button
        v-for="customer in filteredCustomers"
        :key="customer.id"
        type="button"
        class="grid w-full gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-left shadow-[0_1px_4px_rgba(15,23,42,0.14)] transition hover:border-blue-200 dark:border-white/[0.07] dark:bg-gray-900 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
        @click="openEditModal(customer)"
      >
        <div class="flex min-w-0 items-center gap-3">
          <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">
            {{ initials(customer.name) }}
          </div>
          <div class="min-w-0">
            <h3 class="truncate text-sm font-bold text-slate-950 dark:text-white">{{ customer.name }}</h3>
            <p class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-blue-300">
              <Phone class="h-3.5 w-3.5" />
              {{ customer.phone ?? 'No phone' }}
            </p>
          </div>
        </div>

        <div class="grid gap-2 sm:min-w-56 sm:justify-items-end">
          <div class="flex items-center justify-between gap-3 sm:justify-end">
            <div class="text-left sm:text-right">
              <p class="text-sm font-bold text-slate-950 dark:text-white">{{ money(customer.total_purchases) }}</p>
              <p :class="['mt-1 text-xs font-bold', Number(customer.outstanding_balance) > 0 ? 'text-rose-600' : 'text-emerald-600']">
                {{ Number(customer.outstanding_balance) > 0 ? `Owes ${money(customer.outstanding_balance)}` : 'Settled' }}
              </p>
            </div>
            <ChevronRight class="h-4 w-4 text-slate-300" />
          </div>
          <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-2 text-xs font-medium text-blue-300 dark:border-white/[0.06] sm:w-full">
            <span>Last purchase: {{ lastPurchase(customer) }}</span>
            <span>Total purchases: {{ money(customer.total_purchases) }}</span>
          </div>
        </div>
      </button>
    </div>

    <div v-else class="flex min-h-[48dvh] flex-col items-center justify-center gap-4 rounded-[14px] border border-dashed border-slate-200 bg-white px-4 text-center dark:border-white/[0.08] dark:bg-gray-900">
      <div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-50">
        <UserRound class="h-6 w-6 text-blue-500" />
      </div>
      <div>
        <p class="text-sm font-semibold text-slate-900 dark:text-white">No customers found</p>
        <p class="mt-1 text-xs font-medium text-slate-400">Add a customer to track purchases and credit balances.</p>
      </div>
    </div>

    <BaseModal :show="showModal" :title="editingCustomer ? 'Edit Customer' : 'Add Customer'" @close="closeModal">
      <form class="space-y-4" @submit.prevent="submitCustomer">
        <TextField v-model="form.name" label="Name" required />
        <TextField v-model="form.phone" label="Phone" />
        <TextField v-model="form.email" label="Email" type="email" />
        <TextField v-model="form.address" label="Address" />

        <p v-if="customerStore.error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ customerStore.error }}</p>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-white/[0.06]">
          <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600" @click="closeModal">Cancel</button>
          <button type="submit" :disabled="customerStore.loading" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">
            {{ customerStore.loading ? 'Saving...' : 'Save Customer' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
