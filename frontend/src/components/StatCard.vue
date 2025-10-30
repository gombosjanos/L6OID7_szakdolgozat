<template>
  <v-card elevation="1" class="pa-4 stat-card">
    <div class="text-caption text-medium-emphasis">{{ title }}</div>
    <div class="text-h5 font-weight-bold mt-1 text-primary">{{ value }}</div>
  </v-card>
  
</template>

<script setup>
defineProps({
  title: String,
  value: [String, Number],
})
</script>

<style scoped>
.stat-card { border-radius: 14px; }
</style>
