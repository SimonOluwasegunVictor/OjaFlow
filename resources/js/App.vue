<script setup lang="ts">
import { ref } from 'vue';

const message = ref<string>('Hello from Laravel, Vue, TypeScript, and Tailwind!');
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-100">
    <h1 class="text-3xl font-bold text-blue-600 underline">
      {{ message }}
    </h1>
  </div>
</template>
