<script setup>
import HeaderBar from '../components/HeaderBar.vue'
import TheWelcome from '../components/TheWelcome.vue'
</script>

<template>
  <HeaderBar />
  <router-view />
  <main>
    <TheWelcome />
  </main>
</template>
