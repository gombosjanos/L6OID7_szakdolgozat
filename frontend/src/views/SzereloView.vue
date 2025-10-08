<script setup>
import HeaderBar from '../components/HeaderBar.vue'
</script>

<template>
  <HeaderBar />
  <router-view />
  <div class="p-8">
    <h1 class="text-2xl font-bold">Szerelő felület</h1>
    <p>Itt látod a hozzád rendelt munkalapokat és gépeket.</p>
  </div>
</template>
