<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'

const router = useRouter()
const drawer = ref(true)
const user = JSON.parse(localStorage.getItem('user') || '{}')

const logout = async () => {
  try { await api.post('/logout') } catch {}
  localStorage.clear()
  router.push('/login')
}
</script>

<template>
  <!-- App chrome -->
  <v-layout full-height>
    <!-- Top Bar -->
    <v-app-bar app color="white" elevation="3">
      <v-btn class="me-2 menu-btn" icon variant="text" @click="drawer = !drawer" aria-label="Menü">
        <svg width="26" height="26" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
          <path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z" />
        </svg>
      </v-btn>
      <v-toolbar-title class="font-weight-bold text-black">Kertigép Szerviz</v-toolbar-title>
      <v-spacer />
      <v-btn
        v-if="user?.nev"
        :to="{ path: '/profil' }"
        variant="flat"
        color="black"
        size="small"
        class="me-4 welcome-btn welcome-btn--dark"
        prepend-icon="mdi-account-circle"
        title="Profilom"
        aria-label="Profil megnyitása"
      >
        Üdvözöljük: {{ user.nev }}
      </v-btn>
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
        <span class="text-subtitle-1 font-weight-medium">Navigáció</span>
      </div>
      <v-divider />
      <v-list density="comfortable" nav>
        <v-list-item v-if="user?.jogosultsag === 'admin'" title="Főoldal" prepend-icon="mdi-view-dashboard" @click="router.push('/admin')" />
        <v-list-item v-if="user?.jogosultsag === 'admin'" title="Felhasználókezelés" prepend-icon="mdi-account-multiple" @click="router.push('/admin/ugyfelek')" />
        <v-list-item v-if="user?.jogosultsag === 'szerelo'" title="Ügyfelek" prepend-icon="mdi-account" @click="router.push('/admin/ugyfelek')" />
        <v-list-item v-if="user?.jogosultsag === 'admin'" title="Gépek nyilvántartása" prepend-icon="mdi-robot-mower" @click="router.push('/admin/gepek')" />
        <v-list-item v-if="user?.jogosultsag === 'szerelo'" title="Gépek" prepend-icon="mdi-robot-mower" @click="router.push('/admin/gepek')" />
        <v-list-item v-if="user?.jogosultsag === 'admin' || user?.jogosultsag === 'szerelo'" title="Munkalapok" prepend-icon="mdi-clipboard-text" @click="router.push('/admin/munkalapok')" />
        <v-list-item v-if="user?.jogosultsag === 'admin' || user?.jogosultsag === 'szerelo'" title="Alkatrészek" prepend-icon="mdi-cog" @click="router.push('/admin/alkatreszek')" />
        <v-list-item v-if="user?.jogosultsag === 'ugyfel'" title="Ügyfél kezdőlap" prepend-icon="mdi-account" @click="router.push('/ugyfel')" />
        <v-list-item v-if="user?.jogosultsag === 'szerelo'" title="Szerelő kezdőlap" prepend-icon="mdi-wrench" @click="router.push('/szerelo')" />
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
.v-app-bar { background: #fff; }
.menu-btn svg { display: block; }
.menu-btn svg path { fill: #111; }
.welcome-btn { font-weight: 600; }
.welcome-btn--dark { background: #111 !important; color: #fff !important; }
.welcome-btn--dark :deep(.v-btn__content),
.welcome-btn--dark :deep(.v-icon) { color: #fff !important; }
@media (max-width: 600px){ .welcome-btn { display:none !important; } }
</style>


