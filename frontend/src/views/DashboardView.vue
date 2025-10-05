<script setup>
import { ref, onMounted } from 'vue'
import { api } from '@/api'
import { useRouter } from 'vue-router'

const user = ref(JSON.parse(localStorage.getItem('user')))
const router = useRouter()

const logout = async () => {
  await api.post('/logout')
  localStorage.clear()
  router.push('/login')
}
</script>

<template>
  <div class="p-8">
    <h1 class="text-2xl font-bold mb-4">Üdv, {{ user?.nev }}!</h1>
    <p>Szerepkör: {{ user?.jogosultsag }}</p>
    <button @click="logout" class="bg-red-600 text-white px-4 py-2 rounded mt-4">Kijelentkezés</button>
  </div>
</template>
