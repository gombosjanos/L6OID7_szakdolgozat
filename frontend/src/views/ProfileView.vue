<script setup>
import { ref, onMounted } from 'vue'

const user = ref(null)

onMounted(() => {
  try { user.value = JSON.parse(localStorage.getItem('user') || 'null') } catch { user.value = null }
})
</script>

<template>
  <v-container class="py-6" fluid>
    <v-card max-width="720" class="mx-auto">
      <v-card-title class="text-h6">Profilom</v-card-title>
      <v-divider />
      <v-card-text>
        <div v-if="user" class="d-flex flex-column gap-2">
          <div><strong>Név:</strong> {{ user.nev || '-' }}</div>
          <div><strong>Szerep:</strong> {{ user.jogosultsag || '-' }}</div>
          <div v-if="user.email"><strong>E-mail:</strong> {{ user.email }}</div>
        </div>
        <div v-else class="text-medium-emphasis">Nem található felhasználói adat.</div>
      </v-card-text>
    </v-card>
  </v-container>
  
</template>

<style scoped>
.gap-2 > * + * { margin-top: 8px; }
</style>

