<script setup lang="ts">
import { Check, ChevronDown, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface SearchableSelectOption {
  value: string;
  label: string;
  description?: string | null;
}

const props = withDefaults(defineProps<{
  label?: string;
  modelValue: string;
  options: SearchableSelectOption[];
  placeholder?: string;
  searchPlaceholder?: string;
  emptyText?: string;
}>(), {
  label: undefined,
  placeholder: 'Select an option',
  searchPlaceholder: 'Search...',
  emptyText: 'No options found',
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
  change: [value: string];
}>();

const root = ref<HTMLElement | null>(null);
const open = ref(false);
const searchQuery = ref('');

const selectedOption = computed(() => props.options.find((option) => option.value === props.modelValue));
const filteredOptions = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();

  if (!query) {
    return props.options;
  }

  return props.options.filter((option) =>
    [option.label, option.description].some((field) => field?.toLowerCase().includes(query)),
  );
});

function choose(value: string) {
  emit('update:modelValue', value);
  emit('change', value);
  open.value = false;
  searchQuery.value = '';
}

function toggle() {
  open.value = !open.value;

  if (!open.value) {
    searchQuery.value = '';
  }
}

function handleClickOutside(event: MouseEvent) {
  if (!root.value?.contains(event.target as Node)) {
    open.value = false;
    searchQuery.value = '';
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div ref="root" class="relative">
    <label v-if="label" class="mb-2 block text-sm font-bold text-slate-900 dark:text-white">{{ label }}</label>

    <button
      type="button"
      class="flex h-11 w-full items-center justify-between gap-3 rounded-[10px] border border-slate-200 bg-white px-3 text-left text-sm font-semibold text-slate-950 outline-none transition hover:bg-slate-50 focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-950 dark:text-white dark:hover:bg-white/[0.04] dark:focus:ring-blue-500/20"
      aria-haspopup="listbox"
      :aria-expanded="open"
      @click.stop="toggle"
    >
      <span class="min-w-0">
        <span class="block truncate">{{ selectedOption?.label ?? placeholder }}</span>
        <span v-if="selectedOption?.description" class="block truncate text-xs font-medium text-slate-400">{{ selectedOption.description }}</span>
      </span>
      <ChevronDown :class="['h-4 w-4 shrink-0 text-slate-400 transition', open ? 'rotate-180' : '']" />
    </button>

    <div
      v-if="open"
      class="absolute left-0 right-0 top-[calc(100%+0.5rem)] z-50 rounded-xl border border-slate-200 bg-white p-2 shadow-[0_18px_40px_rgba(15,23,42,0.18)] dark:border-white/[0.08] dark:bg-gray-950"
      @click.stop
    >
      <div class="relative">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="searchPlaceholder"
          class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/[0.08] dark:bg-gray-900 dark:text-white dark:focus:ring-blue-500/20"
          @keydown.escape="open = false"
        >
      </div>

      <div class="mt-2 max-h-56 overflow-y-auto pr-1" role="listbox">
        <button
          v-for="option in filteredOptions"
          :key="option.value"
          type="button"
          class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-blue-50 dark:hover:bg-blue-500/10"
          role="option"
          :aria-selected="option.value === props.modelValue"
          @click="choose(option.value)"
        >
          <span class="min-w-0">
            <span class="block truncate text-sm font-bold text-slate-900 dark:text-white">{{ option.label }}</span>
            <span v-if="option.description" class="block truncate text-xs font-medium text-slate-400">{{ option.description }}</span>
          </span>
          <Check v-if="option.value === props.modelValue" class="h-4 w-4 shrink-0 text-blue-600" />
        </button>

        <p v-if="!filteredOptions.length" class="px-3 py-4 text-center text-sm font-semibold text-slate-400">
          {{ emptyText }}
        </p>
      </div>
    </div>
  </div>
</template>
