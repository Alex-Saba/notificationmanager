<template>
  <div class="p-6 bg-white shadow rounded-md">
    <h2 class="text-lg font-semibold mb-2">Vue is installed!</h2>
    <p class="text-sm text-slate-600">This component is rendered by Vue 3.</p>
    <button
      class="mt-4 px-4 py-2 rounded bg-slate-900 text-white hover:bg-slate-800"
      @click="count++"
    >
      Clicked {{ count }} times
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const count = ref(0);
</script>
