<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'

const router = useRouter()
const identifier = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')
const twoFactorRequired = ref(false)
const twoFactorMessage = ref('')
const twoFactorCode = ref('')
const twoFactorRecovery = ref('')
const useRecoveryCode = ref(false)

const forgotDialog = ref(false)
const forgotFormRef = ref(null)
const forgotEmail = ref('')
const forgotLoading = ref(false)
const forgotSuccess = ref('')
const forgotError = ref('')

const identifierRules = [
  v => !!(v?.trim()) || 'Az email vagy felhasználónév megadása kötelező'
]
const emailRequired = (v) => !!v || 'Az email megadása kötelező'
const emailFormat = (v) => /.+@.+\..+/.test(v) || 'Érvényes email címet adj meg'
const passwordRequired = (v) => !!v || 'A jelszó megadása kötelező'

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value
}

const passwordToggleLabel = computed(() => (showPassword.value ? 'Rejt' : 'Mutat'))

const resetTwoFactorState = () => {
  twoFactorRequired.value = false
  twoFactorMessage.value = ''
  twoFactorCode.value = ''
  twoFactorRecovery.value = ''
  useRecoveryCode.value = false
}

const switchTwoFactorMode = () => {
  useRecoveryCode.value = !useRecoveryCode.value
  twoFactorCode.value = ''
  twoFactorRecovery.value = ''
}

const openForgot = () => {
  const maybeEmail = identifier.value.trim()
  forgotEmail.value = maybeEmail.includes('@') ? maybeEmail : ''
  forgotSuccess.value = ''
  forgotError.value = ''
  forgotFormRef.value?.resetValidation?.()
  forgotDialog.value = true
}

const closeForgot = () => {
  forgotDialog.value = false
  forgotLoading.value = false
}

const requestReset = async () => {
  forgotError.value = ''
  forgotSuccess.value = ''
  const result = await forgotFormRef.value?.validate?.()
  if (!result?.valid) return
  forgotLoading.value = true
  const payload = { email: forgotEmail.value.trim() }
  const fallbackMessage = 'Ha a megadott email cím szerepel a rendszerben, elküldtük a jelszó-helyreállító levelet.'
  try {
    const { data } = await api.post('/password/forgot', payload)
    forgotSuccess.value = data?.message || fallbackMessage
  } catch (e) {
    const res = e?.response
    if (res?.status === 429) {
      forgotError.value = res?.data?.message || 'Túl sok próbálkozás történt. Kérjük, próbáld újra később.'
    } else {
      forgotSuccess.value = res?.data?.message || fallbackMessage
    }
  } finally {
    forgotLoading.value = false
  }
}

const login = async () => {
  error.value = ''
  loading.value = true
  try {
    const payload = {
      azonosito: identifier.value.trim(),
      jelszo: password.value
    }

    if (twoFactorRequired.value) {
      if (useRecoveryCode.value) {
        if (!twoFactorRecovery.value.trim()) {
          error.value = 'Add meg a helyreállító kódot.'
          loading.value = false
          return
        }
        payload.helyreallito_kod = twoFactorRecovery.value.trim()
      } else {
        if (!twoFactorCode.value.trim()) {
          error.value = 'Add meg a hitelesítő alkalmazás kódját.'
          loading.value = false
          return
        }
        payload.ketfaktor_kod = twoFactorCode.value.trim()
      }
    }

    const { data } = await api.post('/login', payload)

    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(data.user))

    resetTwoFactorState()

    if (data.user.jogosultsag === 'admin') router.push('/admin')
    else if (data.user.jogosultsag === 'szerelo') router.push('/szerelo')
    else router.push('/Ugyfel')
  } catch (e) {
    const res = e?.response
    if (res?.status === 423) {
      twoFactorRequired.value = true
      twoFactorMessage.value = res?.data?.message || 'Add meg a kétlépcsős azonosító kódot.'
      error.value = ''
    } else if (res?.status === 422 && twoFactorRequired.value) {
      error.value = res?.data?.message || 'Érvénytelen kétlépcsős kód.'
    } else if (res?.status === 422 && res.data?.errors) {
      const firstKey = Object.keys(res.data.errors)[0]
      error.value = res.data.errors[firstKey][0]
    } else {
  error.value = res?.data?.message || res?.data?.error || 'Hibás email/felhasználónév vagy jelszó.'
      console.debug('Login error', res?.data || e)
    }
    if (res?.status !== 423 && res?.status !== 422) {
      resetTwoFactorState()
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
            <v-col cols="12" md="6" class="pa-0 d-none d-md-flex">
              <div class="illustration-panel">
                <div class="panel-content">
                  <div class="text-h5 font-weight-bold mb-2">Üdv újra!</div>
                  <div class="text-body-2 opacity-80">Lépj be a fűnyíró szerviz admin felületére.</div>
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6" class="pa-6 pa-md-8">
              <div class="text-center mb-6">
                <v-avatar color="primary" size="56" class="mb-3">
                  <v-icon icon="mdi-lawn-mower" color="white"></v-icon>
                </v-avatar>
                <div class="text-h5 font-weight-bold">Bejelentkezés</div>
                <div class="text-body-2 text-medium-emphasis mt-1">
                  Nincs még fiókod?
                  <router-link to="/register">Regisztrálj itt</router-link>
                </div>
              </div>

              <v-form @submit.prevent="login">
                <v-row>
                  <v-col cols="12">
                    <v-text-field
                      v-model="identifier"
                      label="Email vagy felhasználónév"
                      type="text"
                      prepend-inner-icon="mdi-account"
                      :rules="identifierRules"
                      autocomplete="username"
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
                        :rules="[passwordRequired]"
                        autocomplete="current-password"
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

                  <template v-if="twoFactorRequired">
                    <v-col cols="12">
                      <v-alert type="info" variant="tonal" density="comfortable">
                        {{ twoFactorMessage || 'Add meg a kétlépcsős azonosító kódot.' }}
                      </v-alert>
                    </v-col>
                    <v-col cols="12" v-if="!useRecoveryCode">
                      <v-text-field
                        v-model="twoFactorCode"
                        label="Hitelesítő kód"
                        prepend-inner-icon="mdi-shield-key"
                        inputmode="numeric"
                        maxlength="6"
                        autocomplete="one-time-code"
                        variant="outlined"
                        density="comfortable"
                      />
                    </v-col>
                    <v-col cols="12" v-else>
                      <v-text-field
                        v-model="twoFactorRecovery"
                        label="Helyreállító kód"
                        prepend-inner-icon="mdi-lifebuoy"
                        autocomplete="off"
                        variant="outlined"
                        density="comfortable"
                      />
                    </v-col>
                    <v-col cols="12" class="text-end">
                      <v-btn variant="text" size="small" color="primary" @click="switchTwoFactorMode">
                        {{ useRecoveryCode ? 'Hitelesítő kód használata' : 'Helyreállító kód használata' }}
                      </v-btn>
                    </v-col>
                  </template>

                  <v-col cols="12">
                    <v-btn type="submit" color="primary" block size="large" :loading="loading">
                      Bejelentkezés
                    </v-btn>
                  </v-col>
                  <v-col cols="12" class="text-end">
                    <v-btn variant="text" size="small" color="primary" @click="openForgot">
                      Elfelejtetted a jelszavad?
                    </v-btn>
                  </v-col>
                </v-row>

                <v-alert v-if="error" type="error" variant="tonal" class="mt-4 text-center" density="comfortable">
                  {{ error }}
                </v-alert>
              </v-form>
            </v-col>
          </v-row>
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="forgotDialog" max-width="520">
      <v-card>
        <v-card-title class="text-h6">Elfelejtett jelszó</v-card-title>
        <v-card-text>
          <div class="text-body-2 text-medium-emphasis mb-3">
            Add meg az email címedet, és ha szerepel a rendszerben, néhány percen belül küldünk egy jelszó-helyreállító levelet.
          </div>
          <v-form ref="forgotFormRef" @submit.prevent="requestReset">
            <v-text-field
              v-model="forgotEmail"
              label="Email"
              type="email"
              :rules="[emailRequired, emailFormat]"
              prepend-inner-icon="mdi-email"
              autocomplete="email"
              required
            />
            <v-alert
              v-if="forgotError"
              type="error"
              variant="tonal"
              class="mt-3"
              density="comfortable"
            >
              {{ forgotError }}
            </v-alert>
            <v-alert
              v-if="forgotSuccess"
              type="success"
              variant="tonal"
              class="mt-3"
              density="comfortable"
            >
              {{ forgotSuccess }}
            </v-alert>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeForgot">Mégse</v-btn>
          <v-btn color="primary" :loading="forgotLoading" @click="requestReset">Küldés</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
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

.auth-card { border-radius: 18px; }

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

.panel-content { position: relative; color: white; text-align: center; padding: 48px 28px; max-width: 420px; }
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
  right: 10px;
  transform: translateY(-50%);
  min-width: 48px;
  opacity: 1 !important;
  color: var(--v-theme-primary);
  z-index: 2;
  font-weight: 600;
  height: 36px;
  padding: 0 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}

:deep(.password-toggle-btn .v-icon) {
  color: inherit;
  opacity: 1 !important;
}


@media (max-width: 600px) {
  .auth-bg { padding: 12px; }
  .auth-card { border-radius: 14px; }
}
</style>
