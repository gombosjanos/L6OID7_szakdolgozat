<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const drawer = ref(true)
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
  <!-- FONTOS: v-app NINCS ITT! -->
  <v-layout full-height>
    <!-- FELSŐ SÁV -->
    <v-app-bar
      app
      color="primary"
      dark
      elevation="2"
    >
      <v-app-bar-nav-icon @click="drawer = !drawer" />
      <v-toolbar-title class="font-weight-bold">Gépszerviz rendszer</v-toolbar-title>
      <v-spacer />
      <v-btn
        color="error"
        variant="flat"
        class="text-white"
        @click="logout"
      >
        Kijelentkezés
      </v-btn>
    </v-app-bar>

    <!-- OLDALSÓ MENÜ -->
    <v-navigation-drawer
      v-model="drawer"
      app
      color="grey-lighten-4"
      elevation="1"
      width="220"
    >
      <v-list density="compact" nav>
        <v-list-subheader class="text-subtitle-2 font-weight-bold px-4">
          Menü
        </v-list-subheader>

        <v-list-item
          v-if="user.jogosultsag === 'admin'"
          title="Felhasználók"
          prepend-icon="mdi-account-multiple"
          @click="router.push('/admin/felhasznalok')"
        />
        <v-list-item
          v-if="user.jogosultsag === 'admin'"
          title="Gépek"
          prepend-icon="mdi-cog"
          @click="router.push('/admin/gepek')"
        />
      </v-list>
    </v-navigation-drawer>

    <!-- FŐ TARTALOM -->
    <v-main class="bg-grey-lighten-5">
      <v-container fluid class="pa-6">
        <router-view />
      </v-container>
    </v-main>
  </v-layout>
</template>
