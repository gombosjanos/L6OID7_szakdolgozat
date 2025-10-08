<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')

const login = async () => {
  error.value = ''
  try {
    const { data } = await axios.post('http://127.0.0.1:8000/api/login', {
      email: email.value,
      jelszo: password.value
    })

    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(data.user))

    if (data.user.jogosultsag === 'admin') router.push('/admin')
    else if (data.user.jogosultsag === 'szerelo') router.push('/szerelo')
    else router.push('/ugyfel')
  } catch {
    error.value = 'Hibás email vagy jelszó.'
  }
}
</script>

<template>
  <v-app>
    <v-main class="d-flex align-center justify-center" style="min-height: 100vh;">
      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
          <v-col cols="12" sm="8" md="4">
            <v-card elevation="8" class="pa-6">
              <v-card-title class="text-h5 text-center font-weight-bold mb-2">
                Gépszerviz rendszer
              </v-card-title>
              <v-card-subtitle class="text-center mb-6">
                Jelentkezz be a folytatáshoz
              </v-card-subtitle>

              <v-form @submit.prevent="login">
                <v-text-field
                  v-model="email"
                  label="Email"
                  type="email"
                  prepend-inner-icon="mdi-email"
                  variant="outlined"
                  class="mb-4"
                  required
                ></v-text-field>

                <v-text-field
                  v-model="password"
                  label="Jelszó"
                  type="password"
                  prepend-inner-icon="mdi-lock"
                  variant="outlined"
                  class="mb-6"
                  required
                ></v-text-field>

                <v-btn
                  type="submit"
                  color="primary"
                  block
                  size="large"
                  class="mb-4"
                >
                  Bejelentkezés
                </v-btn>

                <v-alert
                  v-if="error"
                  type="error"
                  variant="tonal"
                  border="start"
                  prominent
                  class="text-center"
                >
                  {{ error }}
                </v-alert>
              
              </v-form>
              <v-card-subtitle class="text-center mt-4">
                Nincs még fiókod?
                <v-btn variant="text" color="primary" @click="router.push('/register')">
                   Regisztrálj itt
                </v-btn>
              </v-card-subtitle>

            </v-card>
          </v-col>
        </v-row>
        
      </v-container>
    </v-main>
  </v-app>
  
</template>
