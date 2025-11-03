<script setup>
import { useRouter } from 'vue-router'
import { api } from '../api.js'

const router = useRouter()
const user = JSON.parse(localStorage.getItem('user') || '{}')

const logout = async () => {
  try {
    await api.post('/logout')
  } catch {}
  localStorage.clear()
  router.push('/login')
}
</script>

<template>
  <header class="site-header bg-white text-black shadow px-8 py-4 flex justify-between items-center">
    <h1 class="text-lg font-semibold tracking-wide text-black">Gépszerviz rendszer</h1>
    <div v-if="user.nev" class="flex items-center gap-4">
      <span class="text-sm text-black">{{ user.nev }} ({{ user.jogosultsag }})</span>
      <button
        @click="logout"
        class="logout-btn bg-red-600 hover:bg-red-700 px-4 py-2 rounded-md text-sm font-medium transition text-white"
      >
        Kijelentkezés
      </button>
    </div>
  </header>
</template>
<style scoped>
/* Hide the default navigation (hamburger) button in the header */
:deep(.v-app-bar-nav-icon) {
  display: none !important;
}
/* Ensure header text is black and readable */
.site-header h1,
.site-header span {
  color: #000 !important;
}
.site-header .logout-btn {
  color: #fff !important;
}
</style>

