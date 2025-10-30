<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'

const router = useRouter()
const drawer = ref(true)
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
  <!-- App chrome -->
  <v-layout full-height>
    <!-- Top Bar -->
    <v-app-bar app class="brand-bar" elevation="3">
      <v-app-bar-nav-icon color="white" @click="drawer = !drawer" />
      <v-toolbar-title class="font-weight-bold text-white">Fűnyíró Szerviz Admin</v-toolbar-title>
      <v-spacer />
      <v-chip v-if="user?.nev" class="me-3" color="white" variant="tonal" prepend-icon="mdi-account-circle">
        {{ user.nev }} <span v-if="user.jogosultsag"> • {{ user.jogosultsag }}</span>
      </v-chip>
      <v-btn color="error" variant="flat" class="text-white" @click="logout">
        Kijelentkezés
      </v-btn>
    </v-app-bar>

    <!-- Side Navigation -->
    <v-navigation-drawer v-model="drawer" app color="white" elevation="2" width="240">
      <div class="px-4 py-4 d-flex align-center">
        <v-avatar color="primary" size="36" class="me-2">
          <v-icon icon="mdi-lawn-mower" color="white"></v-icon>
        </v-avatar>
        <span class="text-subtitle-1 font-weight-medium">Műhely navigáció</span>
      </div>
      <v-divider></v-divider>
      <v-list density="comfortable" nav>
        <v-list-item
          v-if="user?.jogosultsag === 'admin'"
          title="Admin irányítópult"
          prepend-icon="mdi-view-dashboard"
          @click="router.push('/admin')"
        />
        <v-list-item
          v-if="user?.jogosultsag === 'admin'"
          title="Ügyfélkezelés"
          prepend-icon="mdi-account-multiple"
          @click="router.push('/admin/ugyfelek')"
        />
        <v-list-item
          v-if="user?.jogosultsag === 'szerelo'"
          title="Ügyfelek"
          prepend-icon="mdi-account"
          @click="router.push('/admin/ugyfelek')"
        />
        <v-list-item
          v-if="user?.jogosultsag === 'admin'"
          title="Gépek nyilvántartása"
          prepend-icon="mdi-robot-mower"
          @click="router.push('/admin/gepek')"
        />
        <v-list-item
          v-if="user?.jogosultsag === 'szerelo'"
          title="Gépek"
          prepend-icon="mdi-robot-mower"
          @click="router.push('/admin/gepek')"
        />
        <v-list-item
          v-if="user?.jogosultsag === 'admin' || user?.jogosultsag === 'szerelo'"
          title="Munkalapok"
          prepend-icon="mdi-clipboard-text"
          @click="router.push('/admin/munkalapok')"
        />
        <v-list-item
          v-if="user?.jogosultsag === 'admin' || user?.jogosultsag === 'szerelo'"
          title="Alkatrészek"
          prepend-icon="mdi-cog"
          @click="router.push('/admin/alkatreszek')"
        />
        <v-list-item
          v-if="user?.jogosultsag === 'ugyfel'"
          title="Ügyfél kezdőlap"
          prepend-icon="mdi-account"
          @click="router.push('/ugyfel')"
        />
        <v-list-item
          v-if="user?.jogosultsag === 'szerelo'"
          title="Szerelő kezdőlap"
          prepend-icon="mdi-wrench"
          @click="router.push('/szerelo')"
        />
      </v-list>
    </v-navigation-drawer>

    <!-- Main Content -->
    <v-main class="bg-grey-lighten-5">
      <v-container fluid class="pa-6">
        <router-view />
      </v-container>
    </v-main>
  </v-layout>
</template>

<style scoped>
.brand-bar {
  background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%);
}
</style>
