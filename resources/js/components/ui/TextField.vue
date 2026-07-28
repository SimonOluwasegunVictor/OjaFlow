<script setup lang="ts">
import { computed, ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
  id?: string;
  label: string;
  modelValue: string;
  type?: string;
  placeholder?: string;
  required?: boolean;
  autocomplete?: string;
}>(), {
  type: 'text',
  placeholder: '',
  required: false,
  autocomplete: undefined,
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const showingPassword = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => isPassword.value && showingPassword.value ? 'text' : props.type);
</script>

<template>
  <label class="grid gap-2 text-sm font-bold text-slate-900">
    <span>{{ label }}</span>
    <span class="relative block">
      <input
        :id="id"
        :value="modelValue"
        :type="inputType"
        :placeholder="placeholder"
        :required="required"
        :autocomplete="autocomplete"
        class="field"
        :class="{ 'pr-12': isPassword }"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      >
      <button
        v-if="isPassword"
        type="button"
        class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-lg text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/20"
        :aria-label="showingPassword ? 'Hide password' : 'Show password'"
        @click="showingPassword = !showingPassword"
      >
        <EyeOff v-if="showingPassword" class="h-5 w-5" />
        <Eye v-else class="h-5 w-5" />
      </button>
    </span>
  </label>
</template>
