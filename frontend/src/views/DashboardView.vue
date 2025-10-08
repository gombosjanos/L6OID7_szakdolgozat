<template>
  <div>
    <h1 class="text-2xl font-semibold text-gray-800 mb-6">Statisztika</h1>

    <div class="grid md:grid-cols-3 gap-6">
      <StatCard title="Felhasználók száma" :value="124" />
      <StatCard title="Aktív projektek" :value="12" />
      <StatCard title="Mai belépések" :value="31" />
    </div>
  </div>
</template>

<script setup>
import StatCard from '../components/StatCard.vue'
</script>
