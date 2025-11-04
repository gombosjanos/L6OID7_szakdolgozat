<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api.js'

const route = useRoute()
const router = useRouter()

const formRef = ref(null)
const token = ref(route.query.token || '')
const email = ref(route.query.email || '')
const password = ref('')
const passwordConfirm = ref('')
const showPassword = ref(false)
const showPasswordConfirm = ref(false)
const loading = ref(false)
const error = ref('')
const success = ref('')

watch(
  () => route.query,
  (query) => {
    token.value = query.token || ''
    email.value = query.email || ''
  }
)

const rules = {
  email: [
    (v) => !!(v?.trim()) || 'Az email megadása kötelező',
    (v) => /.+@.+\..+/.test(v) || 'Érvényes email címet adj meg'
  ],
  password: [
    (v) => !!v || 'A jelszó megadása kötelező',
    (v) => (v?.length ?? 0) >= 8 || 'Legalább 8 karakter',
    (v) => (/[A-Za-z]/.test(v) && /\d/.test(v)) || 'Tartalmazzon betűt és számot'
  ],
  confirm: [
    (v) => v === password.value || 'A jelszavak nem egyeznek'
  ]
}

const submit = async () => {
  error.value = ''
  success.value = ''
  const result = await formRef.value?.validate?.()
  if (!result?.valid) return

  if (!token.value) {
    error.value = 'A helyreállító token hiányzik vagy lejárt. Kérj új jelszó-helyreállító emailt.'
    return
  }

  loading.value = true
  try {
    const { data } = await api.post('/password/reset', {
      email: email.value.trim(),
      token: token.value,
      password: password.value,
      password_confirmation: passwordConfirm.value
    })
    success.value = data?.message || 'A jelszó sikeresen frissült.'
    setTimeout(() => router.push('/login'), 2000)
  } catch (e) {
    const res = e?.response
    error.value = res?.data?.message || 'A jelszó visszaállítása nem sikerült. Kérjük, kérj új linket és próbáld meg ismét.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <v-container fluid class="auth-bg d-flex align-center justify-center">
    <v-row class="w-100 ma-0" align="center" justify="center">
      <v-col cols="12" sm="10" md="9" lg="8" xl="7">
        <v-card class="auth-card overflow-hidden" elevation="10">
          <v-row class="ma-0" no-gutters>
            <v-col cols="12" md="6" class="pa-0 d-none d-md-flex">
              <div class="illustration-panel">
                <div class="panel-content">
                  <div class="text-h5 font-weight-bold mb-2">Új jelszó beállítása</div>
                  <div class="text-body-2 opacity-80">Add meg az új jelszavadat, majd jelentkezz be ismét a frissített adatokkal.</div>
                </div>
              </div>
            </v-col>

            <v-col cols="12" md="6" class="pa-6 pa-md-8">
              <div class="text-center mb-6">
                <v-avatar color="primary" size="56" class="mb-3">
                  <v-icon icon="mdi-lock-reset" color="white"></v-icon>
                </v-avatar>
                <div class="text-h5 font-weight-bold">Jelszó visszaállítása</div>
                <div class="text-body-2 text-medium-emphasis mt-1">
                  Ha nem te kérted a jelszó módosítását, hagyd figyelmen kívül ezt a lépést.
                </div>
              </div>

              <v-form ref="formRef" @submit.prevent="submit">
                <v-row>
                  <v-col cols="12">
                    <v-text-field
                      v-model="email"
                      label="Email"
                      type="email"
                      :rules="rules.email"
                      prepend-inner-icon="mdi-email"
                      autocomplete="email"
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="password"
                      :type="showPassword ? 'text' : 'password'"
                      label="Új jelszó"
                      prepend-inner-icon="mdi-lock"
                      :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                      @click:append-inner="showPassword = !showPassword"
                      :rules="rules.password"
                      autocomplete="new-password"
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="passwordConfirm"
                      :type="showPasswordConfirm ? 'text' : 'password'"
                      label="Új jelszó megerősítése"
                      prepend-inner-icon="mdi-lock-check"
                      :append-inner-icon="showPasswordConfirm ? 'mdi-eye-off' : 'mdi-eye'"
                      @click:append-inner="showPasswordConfirm = !showPasswordConfirm"
                      :rules="rules.confirm"
                      autocomplete="new-password"
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-btn color="primary" type="submit" block size="large" :loading="loading">
                      Jelszó frissítése
                    </v-btn>
                  </v-col>
                </v-row>

                <v-alert
                  v-if="error"
                  type="error"
                  variant="tonal"
                  class="mt-4 text-center"
                  density="comfortable"
                >
                  {{ error }}
                </v-alert>

                <v-alert
                  v-if="success"
                  type="success"
                  variant="tonal"
                  class="mt-4 text-center"
                  density="comfortable"
                >
                  {{ success }}
                </v-alert>

                <div class="text-caption text-medium-emphasis mt-4 text-center">
                  Vissza a <router-link to="/login">bejelentkezés</router-link> oldalra.
                </div>
              </v-form>
            </v-col>
          </v-row>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<style scoped>
.auth-bg {
  min-height: 100vh;
  padding: 16px;
  background: radial-gradient(1200px 600px at 80% -10%, rgba(67, 160, 71, 0.18), transparent 60%),
              radial-gradient(1000px 500px at -10% 110%, rgba(27, 94, 32, 0.15), transparent 60%),
              linear-gradient(180deg, #f5faf6 0%, #eef7f0 100%);
}

.auth-card {
  border-radius: 18px;
}

.illustration-panel {
  position: relative;
  background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%);
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.illustration-panel::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(500px 220px at 20% 20%, rgba(255, 255, 255, 0.18), transparent 60%),
              radial-gradient(400px 180px at 90% 80%, rgba(255, 255, 255, 0.12), transparent 60%);
}

.panel-content {
  position: relative;
  color: white;
  text-align: center;
  padding: 48px 28px;
  max-width: 420px;
}

.opacity-80 {
  opacity: 0.8;
}

@media (max-width: 600px) {
  .auth-bg {
    padding: 12px;
  }

  .auth-card {
    border-radius: 14px;
  }
}
</style>
