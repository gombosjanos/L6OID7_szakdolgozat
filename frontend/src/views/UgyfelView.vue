<script setup>
import HeaderBar from '../components/HeaderBar.vue'
</script>

<template>
  <HeaderBar />
  <router-view />
  <div class="p-8">
    <h1 class="text-2xl font-bold">Ügyfél felület</h1>
    <p>Itt követheted a géped javítási státuszát.</p>
  </div>
</template>
