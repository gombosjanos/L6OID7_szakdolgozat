<script setup>
import { ref, computed } from 'vue'
import { api } from '../api.js'
import { useRouter } from 'vue-router'
import { ensureEuropeanPhone } from '../utils/phone.js'

const formRef = ref(null)
const router = useRouter()

// Form state
const nev = ref('')
const felhasznalonev = ref('')
const email = ref('')
const telefonszam = ref('')
const password = ref('')
const passwordConfirm = ref('')
const showPassword = ref(false)
const showPasswordConfirm = ref(false)
const loading = ref(false)
const error = ref('')
const success = ref('')

const passwordToggleLabel = computed(() => (showPassword.value ? 'Rejt' : 'Mutat'))
const passwordConfirmToggleLabel = computed(() => (showPasswordConfirm.value ? 'Rejt' : 'Mutat'))

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value
}

const togglePasswordConfirmVisibility = () => {
  showPasswordConfirm.value = !showPasswordConfirm.value
}

const rules = {
  name: [
    v => !!(v?.trim()) || 'A név megadása kötelező',
    v => (v?.trim()?.length ?? 0) >= 2 || 'Legalább 2 karakter',
    v => (v?.trim()?.length ?? 0) <= 50 || 'Legfeljebb 50 karakter'
  ],
  username: [
    v => !!(v?.trim()) || 'A felhasználónév megadása kötelező',
    v => (v?.trim()?.length ?? 0) >= 4 || 'Legalább 4 karakter',
    v => /^[A-Za-z0-9._-]+$/.test(v || '') || 'Csak betű, szám, pont, kötőjel vagy aláhúzás engedélyezett'
  ],
  email: [
    v => !!(v?.trim()) || 'Az email megadása kötelező',
    v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Érvényes email címet adj meg'
  ],
  phone: [
    v => !!(v?.trim()) || 'A telefonszám megadása kötelező',
    v => ensureEuropeanPhone(v) ? true : 'Érvényes magyar vagy európai telefonszámot adj meg (pl. +36205012465)'
  ],
  password: [
    v => !!v || 'A jelszó megadása kötelező',
    v => v.length >= 8 || 'Legalább 8 karakter',
    v => (/[A-Za-z]/.test(v) && /\d/.test(v)) || 'Tartalmazzon betűt és számot'
  ],
  confirm: [
    v => v === password.value || 'A jelszavak nem egyeznek'
  ]
}

const register = async () => {
  error.value = ''
  success.value = ''
  const result = await formRef.value?.validate?.()
  if(!result?.valid) return
  loading.value = true
  try {
    const payload = {
      nev: nev.value.trim(),
      felhasznalonev: felhasznalonev.value.trim(),
      email: email.value.trim(),
      jelszo: password.value,
      jelszo_confirmation: passwordConfirm.value,
    }

    const phone = ensureEuropeanPhone(telefonszam.value)
    if (!phone) {
      error.value = 'Érvényes magyar vagy európai telefonszámot adj meg (pl. +36205012465).'
      loading.value = false
      return
    }

    payload.telefonszam = phone

    await api.post('/register', payload)
    success.value = 'Sikeres regisztráció! Most már bejelentkezhetsz.'
    nev.value = ''
    felhasznalonev.value = ''
    email.value = ''
    telefonszam.value = ''
    password.value = ''
    passwordConfirm.value = ''
    setTimeout(() => router.push('/login'), 1400)
  } catch (e) {
    const res = e?.response
    if (res?.status === 422 && (res.data?.errors || res.data?.message)) {
      error.value = res.data?.errors ? Object.values(res.data.errors)[0][0] : res.data.message
    } else {
      error.value = res?.data?.message || res?.data?.error || 'Hiba történt a regisztráció során.'
      console.debug('Register error', res?.data || e)
    }
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
            <!-- Illustration / welcome panel on md+ -->
            <v-col cols="12" md="6" class="pa-0 d-none d-md-flex">
              <div class="illustration-panel">
                <div class="panel-content">
                  <div class="text-h5 font-weight-bold mb-2">Üdvözlünk!</div>
                  <div class="text-body-2 opacity-80">Hozz létre fiókot, és kezdd el használni a szolgáltatásokat.</div>
                </div>
              </div>
            </v-col>

            <!-- Form side -->
            <v-col cols="12" md="6" class="pa-6 pa-md-8">
              <div class="text-center mb-6">
                <v-avatar color="primary" size="56" class="mb-3">
                  <v-icon icon="mdi-account-plus" color="white"></v-icon>
                </v-avatar>
                <div class="text-h5 font-weight-bold">Regisztráció</div>
                <div class="text-body-2 text-medium-emphasis mt-1">
                  Már van fiókod?
                  <router-link to="/login">Jelentkezz be</router-link>
                </div>
              </div>

              <v-form ref="formRef" @submit.prevent="register">
                <v-row>
                  <v-col cols="12">
                    <v-text-field
                      v-model="nev"
                      label="Név"
                      prepend-inner-icon="mdi-account"
                      variant="outlined"
                      density="comfortable"
                      :rules="rules.name"
                      autocomplete="name"
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="felhasznalonev"
                      label="Felhasználónév"
                      prepend-inner-icon="mdi-account-circle"
                      variant="outlined"
                      density="comfortable"
                      :rules="rules.username"
                      autocomplete="username"
                      hint="Engedélyezett: betű, szám, pont, kötőjel, aláhúzás"
                      persistent-hint
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="email"
                      label="Email"
                      prepend-inner-icon="mdi-email"
                      variant="outlined"
                      density="comfortable"
                      type="email"
                      :rules="rules.email"
                      autocomplete="email"
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="telefonszam"
                      label="Telefonszám"
                      prepend-inner-icon="mdi-phone"
                      variant="outlined"
                      density="comfortable"
                      :rules="rules.phone"
                      autocomplete="tel"
                      placeholder="+36205012465"
                      hint="Példa formátum: +36205012465"
                      persistent-hint
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <div class="password-input-wrapper">
                      <v-text-field
                        class="password-field"
                        v-model="password"
                        :type="showPassword ? 'text' : 'password'"
                        label="Jelszó"
                        prepend-inner-icon="mdi-lock"
                        variant="outlined"
                        density="comfortable"
                        :rules="rules.password"
                        autocomplete="new-password"
                        required
                      />
                      <v-btn
                        class="password-toggle-btn"
                        variant="text"
                        size="small"
                        @click="togglePasswordVisibility"
                        @mousedown.prevent
                      >
                        {{ passwordToggleLabel }}
                      </v-btn>
                    </div>
                  </v-col>

                  <v-col cols="12">
                    <div class="password-input-wrapper">
                      <v-text-field
                        class="password-field"
                        v-model="passwordConfirm"
                        :type="showPasswordConfirm ? 'text' : 'password'"
                        label="Jelszó megerősítése"
                        prepend-inner-icon="mdi-lock-check"
                        variant="outlined"
                        density="comfortable"
                        :rules="rules.confirm"
                        autocomplete="new-password"
                        required
                      />
                      <v-btn
                        class="password-toggle-btn"
                        variant="text"
                        size="small"
                        @click="togglePasswordConfirmVisibility"
                        @mousedown.prevent
                      >
                        {{ passwordConfirmToggleLabel }}
                      </v-btn>
                    </div>
                  </v-col>

                  <v-col cols="12">
                    <v-btn color="primary" type="submit" block size="large" :loading="loading">
                      Regisztráció
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
                  Regisztrációval elfogadod a felhasználási feltételeket és az adatkezelési tájékoztatót.
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

.opacity-80 { opacity: 0.8; }

:deep(.password-field .v-field__input) {
  padding-right: 80px;
}

.password-input-wrapper {
  position: relative;
  width: 100%;
}

.password-toggle-btn {
  position: absolute;
  top: 35%;
  right: 12px;
  transform: translateY(-50%);
  min-width: 48px;
  height: 36px;
  padding: 0 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 1 !important;
  color: var(--v-theme-primary);
  font-weight: 600;
  z-index: 2;
}

:deep(.password-toggle-btn .v-btn__overlay) {
  display: none;
}

@media (max-width: 600px) {
  .auth-bg { padding: 12px; }
  .auth-card { border-radius: 14px; }
}
</style>
