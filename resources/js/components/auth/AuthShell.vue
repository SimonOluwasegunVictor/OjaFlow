<script setup lang="ts">
import { Store } from 'lucide-vue-next';
</script>

<template>
  <main class="auth-screen min-h-dvh bg-primary text-white">
    <div class="mx-auto flex min-h-dvh w-full max-w-5xl flex-col px-5 py-7 sm:px-8 lg:px-10">
      <slot name="back" />

      <section class="flex flex-1 flex-col items-center justify-center gap-8 py-8">
        <div class="grid justify-items-center gap-4 text-center">
          <div class="grid h-16 w-16 place-items-center rounded-2xl bg-white/20 text-white shadow-soft backdrop-blur">
            <Store class="h-8 w-8" />
          </div>
          <div>
            <h1 class="text-3xl font-semibold tracking-normal sm:text-4xl">TradeNest</h1>
            <p class="mt-2 text-sm font-medium leading-6 text-blue-100">
              Sales, Stock and Debt Book<br>for Nigerian Businesses
            </p>
          </div>
        </div>

        <slot />
      </section>
    </div>
  </main>
</template>
