<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'

const router = useRouter()
const drawer = ref(true)
const user = JSON.parse(localStorage.getItem('user') || '{}')
const firstName = (user?.nev || '').split(' ')[0] || user?.nev || ''

const logout = async () => {
  try { await api.post('/logout') } catch {}
  localStorage.clear()
  router.push('/login')
}
</script>

<template>
  <v-layout full-height>
    <!-- Felső sáv -->
    <v-app-bar app color="white" elevation="3">
      <v-btn class="me-2 Menü-btn" icon variant="text" @click="drawer = !drawer" aria-label="Menü">
        <svg width="26" height="26" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
          <path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z" />
        </svg>
      </v-btn>
      <v-toolbar-title class="font-weight-bold text-black app-title">Kertigép Szerviz</v-toolbar-title>
      <v-spacer />
      <v-btn
        v-if="user?.nev"
        :to="{ path: '/profile' }"
        variant="flat"
        color="black"
        size="small"
        class="me-4 welcome-btn welcome-btn--dark"
        prepend-icon="mdi-account-circle"
        title="Profilom"
        aria-label="Profil megnyitása"
      >
        Üdv, {{ firstName }}
      </v-btn>
      <v-btn color="error" variant="flat" class="text-white" @click="logout">
        Kijelentkezés
      </v-btn>
    </v-app-bar>

    <!-- Oldalsó Menü -->
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
        <v-list-item v-if="user?.jogosultsag === 'admin'" title="Felhasználókezelés" prepend-icon="mdi-account-multiple" @click="router.push('/admin/Ugyfelek')" />
        <v-list-item v-if="user?.jogosultsag === 'admin' || user?.jogosultsag === 'szerelo' || user?.jogosultsag === 'Ugyfel'" title="Profilom" prepend-icon="mdi-view-dashboard" @click="router.push('/profile')" />
        <v-list-item v-if="user?.jogosultsag === 'admin'" title="Gépek nyilvántartása" prepend-icon="mdi-robot-mower" @click="router.push('/admin/gepek')" />
        <v-list-item v-if="user?.jogosultsag === 'szerelo'" title="Gépek" prepend-icon="mdi-robot-mower" @click="router.push('/admin/gepek')" />
        <v-list-item v-if="user?.jogosultsag === 'admin' || user?.jogosultsag === 'szerelo'" title="Munkalapok" prepend-icon="mdi-clipboard-text" @click="router.push('/admin/munkalapok')" />
        <v-list-item v-if="user?.jogosultsag === 'admin' || user?.jogosultsag === 'szerelo'" title="Alkatrészek" prepend-icon="mdi-cog" @click="router.push('/admin/alkatreszek')" />
        <v-list-item v-if="user?.jogosultsag === 'Ugyfel'" title="Kezdőlap" prepend-icon="mdi-account" @click="router.push('/Ugyfel')" />
        <v-list-item v-if="user?.jogosultsag === 'szerelo'" title="Kezdőlap" prepend-icon="mdi-wrench" @click="router.push('/szerelo')" />
      </v-list>
    </v-navigation-drawer>

    <!-- Tartalom -->
    <v-main class="bg-grey-lighten-5">
      <v-container fluid class="pa-6">
        <router-view />
      </v-container>
    </v-main>
  </v-layout>
</template>

<style scoped>
.v-app-bar { background: #fff; }
.Menü-btn svg { display: block; }
.Menü-btn svg path { fill: #111; }
.app-title {
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.welcome-btn { font-weight: 600; max-width: 220px; }
.welcome-btn--dark { background: #111 !important; color: #fff !important; }
.welcome-btn--dark :deep(.v-btn__content),
.welcome-btn--dark :deep(.v-icon) { color: #fff !important; }

@media (max-width: 960px) {
  .app-title { max-width: 220px; font-size: 1.1rem; }
}

@media (max-width: 600px){
  .app-title { max-width: 160px; font-size: 1rem; }
  .welcome-btn {
    display: inline-flex !important;
    max-width: 150px;
    padding-inline: 12px;
    font-size: 0.78rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .welcome-btn :deep(.v-icon) { display: none; }
  .welcome-btn :deep(.v-btn__content) { gap: 6px; }
}
</style>





