<template>
  <div class="bg-white p-6 rounded-lg shadow hover:shadow-md transition">
    <h2 class="text-sm text-gray-500">{{ title }}</h2>
    <p class="text-3xl font-bold text-blue-600 mt-1">{{ value }}</p>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  value: [String, Number],
})
</script>
