<template>
  <router-view />
  <Transition name="splash">
    <SplashScreen v-if="showSplash" />
  </Transition>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import SplashScreen from './components/ui/SplashScreen.vue';

const showSplash = ref(true);

onMounted(() => {
  window.setTimeout(() => {
    showSplash.value = false;
  }, 900);
});
</script>

<style scoped>
.splash-leave-active {
  transition: opacity 220ms ease;
}

.splash-leave-to {
  opacity: 0;
}
</style>
