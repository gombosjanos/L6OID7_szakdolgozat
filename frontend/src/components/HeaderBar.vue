<script setup>
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const user = JSON.parse(localStorage.getItem('user') || '{}')

const logout = async () => {
  try {
    const token = localStorage.getItem('token')
    if (token) {
      await axios.post('http://127.0.0.1:8000/api/logout', {}, {
        headers: { Authorization: `Bearer ${token}` }
      })
    }
  } catch {}
  localStorage.clear()
  router.push('/login')
}
</script>

<template>
  <header class="bg-gray-900 text-white shadow-lg px-8 py-4 flex justify-between items-center">
    <h1 class="text-lg font-semibold tracking-wide">Gépszerviz rendszer</h1>
    <div v-if="user.nev" class="flex items-center gap-4">
      <span class="text-sm">{{ user.nev }} ({{ user.jogosultsag }})</span>
      <button
        @click="logout"
        class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-md text-sm font-medium transition"
      >
        Kijelentkezés
      </button>
    </div>
  </header>
</template>
