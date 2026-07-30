<script setup lang="ts">
// import { defineProps, defineEmits } from 'vue'
import { X } from 'lucide-vue-next'

defineProps<{
  show: boolean
  title?: string
}>()

const emit = defineEmits(['close'])
const closeModal = () => emit('close')
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 backdrop-blur sm:items-center"
      @click.self="closeModal"
    >
      <transition name="scale">
        <div
          v-if="show"
          class="max-h-[85vh] w-full max-w-md overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 text-gray-950 shadow-xl dark:border-white/[0.08] dark:bg-gray-900 dark:text-white sm:p-6"
          style="scrollbar-width: none;"
        >
          <div class="mb-4 flex items-center justify-between gap-4">
            <h3 class="text-base font-semibold">{{ title }}</h3>
            <button
              @click="closeModal"
              class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 dark:border-white/[0.08] text-gray-400 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors cursor-pointer"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <div class="space-y-4">
            <slot />
          </div>
        </div>
      </transition>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.scale-enter-from {
  opacity: 0;
  transform: scale(0.95);
}
.scale-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>
