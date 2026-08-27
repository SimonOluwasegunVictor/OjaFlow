<template>
  <div class="fixed inset-0 z-[100] grid place-items-center overflow-hidden bg-primary text-white">
    <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10" />
    <div class="absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-blue-900/20" />

    <div class="relative grid justify-items-center gap-5 px-8 text-center">
      <div class="grid h-20 w-20 place-items-center rounded-[24px] border border-white/25 bg-white/15 shadow-2xl backdrop-blur-sm">
        <span class="text-4xl font-black tracking-tight">O</span>
      </div>
      <div>
        <h1 class="text-3xl font-black tracking-tight">OjaFlow</h1>
        <p class="mt-2 text-sm font-medium text-blue-100">Your smart business notebook</p>
      </div>
      <div class="mt-5 h-1 w-16 overflow-hidden rounded-full bg-white/20">
        <div class="h-full w-1/2 animate-[splash-progress_900ms_ease-in-out_infinite] rounded-full bg-white" />
      </div>
    </div>
  </div>
</template>
