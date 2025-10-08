<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

const router = useRouter()
const nev = ref('')
const email = ref('')
const telefonszam = ref('')
const password = ref('')
const error = ref('')
const success = ref('')

const register = async () => {
  error.value = ''
  success.value = ''
  try {
    const response = await axios.post('http://127.0.0.1:8000/api/register', {
      nev: nev.value,
      email: email.value,
      jelszo: password.value,
      telefonszam: telefonszam.value
    })
    success.value = 'Sikeres regisztráció! Most már bejelentkezhetsz.'
    nev.value = ''
    email.value = ''
    telefonszam.value = ''
    password.value = ''
    setTimeout(() => router.push('/login'), 1500)
  } catch (e) {
    error.value = e.response?.data?.message || 'Hiba történt a regisztráció során.'
  }
}
</script>

<template>
  <v-container class="d-flex align-center justify-center" style="min-height: 100vh;">
    <v-card class="pa-8" max-width="500" elevation="8">
      <v-card-title class="text-h5 font-weight-bold text-center mb-4">
        Regisztráció
      </v-card-title>

      <v-form @submit.prevent="register">
        <v-text-field
          v-model="nev"
          label="Név"
          prepend-inner-icon="mdi-account"
          variant="outlined"
          required
        />
        <v-text-field
          v-model="email"
          label="Email"
          prepend-inner-icon="mdi-email"
          variant="outlined"
          type="email"
          required
        />
        <v-text-field
          v-model="telefonszam"
          label="Telefonszám"
          prepend-inner-icon="mdi-phone"
          variant="outlined"
        />
        <v-text-field
          v-model="password"
          label="Jelszó"
          prepend-inner-icon="mdi-lock"
          variant="outlined"
          type="password"
          required
        />
        <v-btn color="primary" type="submit" block class="mt-4">
          Regisztráció
        </v-btn>

        <v-alert
          v-if="error"
          type="error"
          variant="tonal"
          class="mt-4 text-center"
        >
          {{ error }}
        </v-alert>

        <v-alert
          v-if="success"
          type="success"
          variant="tonal"
          class="mt-4 text-center"
        >
          {{ success }}
        </v-alert>
      </v-form>
    </v-card>
  </v-container>
</template>
