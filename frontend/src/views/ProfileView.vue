<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { api } from '../api.js'
import QRCode from 'qrcode'
import { ensureEuropeanPhone } from '../utils/phone.js'

const user = ref(null)
const formRef = ref(null)
const editable = ref({ nev: '', felhasznalonev: '', email: '', jogosultsag: '', telefonszam: '' })
const password = ref('')
const passwordConfirm = ref('')
const showPassword = ref(false)
const showPasswordConfirm = ref(false)
const showConfirmPassword = ref(false)
const editing = ref(false)
const saving = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')
const twoFactorStatus = ref({ enabled: false, pending: false, recovery_hatralevo: 0 })
const twoFactorLoading = ref(false)
const twoFactorSetup = ref(null)
const twoFactorSetupLoading = ref(false)
const twoFactorCode = ref('')
const twoFactorConfirmLoading = ref(false)
const recoveryCodes = ref([])
const qrDataUrl = ref('')
const disableDialog = ref(false)
const disableForm = ref({ useRecovery: false, code: '', recovery: '' })
const disableLoading = ref(false)
const regenDialog = ref(false)
const regenForm = ref({ useRecovery: false, code: '', recovery: '' })
const regenLoading = ref(false)
const regenCodes = ref([])
const confirmDialog = ref(false)
const confirmPassword = ref('')
const pendingPayload = ref(null)

const roleLabels = {
  Ugyfel: 'Ugyfel',
  szerelo: 'Szerelő',
  admin: 'Admin',
}

const displayedRole = computed(() => {
  const key = editable.value.jogosultsag
  return roleLabels[key] ?? key ?? '—'
})

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
    v => !!(v?.trim()) || 'Az e-mail megadása kötelező',
    v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Érvényes e-mail címet adj meg'
  ],
  phone: [
    v => !!(v?.trim()) || 'A telefonszám megadása kötelező',
    v => ensureEuropeanPhone(v) ? true : 'Érvényes magyar vagy európai telefonszámot adj meg (pl. +36205012465)'
  ],
  password: [
    v => !v || v.length >= 8 || 'Legalább 8 karakter',
    v => !v || (/[A-Za-z]/.test(v) && /\d/.test(v)) || 'Tartalmazzon betűt és számot'
  ],
  confirm: [
    v => v === password.value || 'A jelszavak nem egyeznek'
  ]
}

onMounted(() => {
  try { user.value = JSON.parse(localStorage.getItem('user') || 'null') } catch { user.value = null }
  syncEditableFromUser()
  loadTwoFactorStatus()
})

function syncEditableFromUser(){
  if (!user.value) return

  editable.value = {
    nev: user.value.nev || '',
    felhasznalonev: user.value.felhasznalonev || '',
    email: user.value.email || '',
    jogosultsag: user.value.jogosultsag || '',
    telefonszam: user.value.telefonszam || ''
  }
}

function startEdit(){
  if(!user.value) return

  syncEditableFromUser()
  password.value = ''
  passwordConfirm.value = ''
  editing.value = true
}
function cancelEdit(){
  syncEditableFromUser()
  password.value = ''
  passwordConfirm.value = ''
  editing.value = false
  confirmDialog.value = false
  confirmPassword.value = ''
  pendingPayload.value = null
}
async function saveProfile(){
  if(!user.value) return
  const result = await formRef.value?.validate?.()
  if(!result?.valid){ return }
  try{
    const payload = {
      nev: editable.value.nev.trim(),
      felhasznalonev: editable.value.felhasznalonev.trim(),
      email: editable.value.email.trim()
    }

    const phone = ensureEuropeanPhone(editable.value.telefonszam)
    if (!phone) {
      snackbarText.value = 'Érvényes magyar vagy európai telefonszámot adj meg (pl. +36205012465).'
      snackbar.value = true
      return
    }
    payload.telefonszam = phone
    if (password.value) {
      payload.password = password.value
      payload.password_confirmation = passwordConfirm.value
    }
    pendingPayload.value = payload
    confirmPassword.value = ''
    confirmDialog.value = true
  }catch(e){
    snackbarText.value = e?.response?.data?.message || e?.message || 'Adatellenőrzési hiba'
    snackbar.value = true
  }
}

async function confirmSave(){
  if (!pendingPayload.value) {
    confirmDialog.value = false
    return
  }
  if (!confirmPassword.value.trim()) {
    snackbarText.value = 'Add meg a jelenlegi jelszavad a mentéshez.'
    snackbar.value = true
    return
  }
  saving.value = true
  try {
    const payload = {
      ...pendingPayload.value,
      current_password: confirmPassword.value,
    }
    const { data } = await api.put('/profile', payload)
    user.value = data
    syncEditableFromUser()
    localStorage.setItem('user', JSON.stringify(data))
    snackbarText.value = 'Profil frissítve'
    snackbar.value = true
    editing.value = false
    password.value = ''
    passwordConfirm.value = ''
    confirmDialog.value = false
    pendingPayload.value = null
  } catch (e) {
    const res = e?.response
    if (res?.status === 422 && res.data?.errors) {
      const firstKey = Object.keys(res.data.errors)[0]
      snackbarText.value = res.data.errors[firstKey][0]
    } else {
      snackbarText.value = res?.data?.message || e?.message || 'Mentési hiba'
    }
    snackbar.value = true
  } finally {
    saving.value = false
  }
}

async function loadTwoFactorStatus(){
  twoFactorLoading.value = true
  try {
    const { data } = await api.get('/two-factor/status')
    twoFactorStatus.value = {
      enabled: !!data?.enabled,
      pending: !!data?.pending,
      recovery_hatralevo: data?.recovery_hatralevo ?? 0,
    }
    if (user.value) {
      user.value.ketfaktor_aktiv = twoFactorStatus.value.enabled
      localStorage.setItem('user', JSON.stringify(user.value))
    }
  } catch (e) {
    console.debug('2FA status error', e?.response?.data || e)
  } finally {
    twoFactorLoading.value = false
  }
}

watch(
  () => twoFactorSetup.value?.otpauth_url,
  async (value) => {
    if (!value) {
      qrDataUrl.value = ''
      return
    }
    try {
      qrDataUrl.value = await QRCode.toDataURL(value, { width: 220, margin: 1 })
    } catch (error) {
      console.debug('QR generation failed', error)
      qrDataUrl.value = ''
    }
  },
)

const isTwoFactorActive = computed(() => twoFactorStatus.value.enabled)
const isTwoFactorPending = computed(() => twoFactorStatus.value.pending && !twoFactorStatus.value.enabled)

const startTwoFactor = async () => {
  twoFactorSetupLoading.value = true
  snackbarText.value = ''
  regenCodes.value = []
  try {
    const { data } = await api.post('/two-factor/setup')
    twoFactorSetup.value = data
    recoveryCodes.value = data?.recovery_codes || []
    twoFactorCode.value = ''
    twoFactorStatus.value.pending = true
  } catch (e) {
    snackbarText.value = e?.response?.data?.message || 'A kétlépcsős beállítás indítása nem sikerült.'
    snackbar.value = true
  } finally {
    twoFactorSetupLoading.value = false
  }
}

const cancelTwoFactorSetup = async () => {
  if (!twoFactorSetup.value) {
    twoFactorSetup.value = null
    recoveryCodes.value = []
    return
  }
  try {
    await api.post('/two-factor/disable')
    twoFactorSetup.value = null
    recoveryCodes.value = []
    await loadTwoFactorStatus()
  } catch (e) {
    snackbarText.value = e?.response?.data?.message || 'A beállítás megszakítása nem sikerült.'
    snackbar.value = true
  }
}

const confirmTwoFactor = async () => {
  if (!twoFactorCode.value.trim()) {
    snackbarText.value = 'Írd be a hitelesítő kódot az aktiváláshoz.'
    snackbar.value = true
    return
  }
  twoFactorConfirmLoading.value = true
  try {
    await api.post('/two-factor/confirm', { kod: twoFactorCode.value.trim() })
    snackbarText.value = 'A kétlépcsős azonosítás bekapcsolva.'
    snackbar.value = true
    twoFactorSetup.value = null
    recoveryCodes.value = []
    twoFactorCode.value = ''
    await loadTwoFactorStatus()
  } catch (e) {
    snackbarText.value = e?.response?.data?.message || 'Érvénytelen kód, próbáld újra.'
    snackbar.value = true
  } finally {
    twoFactorConfirmLoading.value = false
  }
}

const openDisableDialog = () => {
  disableDialog.value = true
  disableForm.value = { useRecovery: false, code: '', recovery: '' }
}

const submitDisable = async () => {
  disableLoading.value = true
  try {
    const payload = {}
    if (twoFactorStatus.value.enabled) {
      if (disableForm.value.useRecovery) {
        if (!disableForm.value.recovery.trim()) {
          disableLoading.value = false
          snackbarText.value = 'Adj meg egy helyreállító kódot vagy válts kódra.'
          snackbar.value = true
          return
        }
        payload.helyreallito_kod = disableForm.value.recovery.trim()
      } else {
        if (!disableForm.value.code.trim()) {
          disableLoading.value = false
          snackbarText.value = 'Add meg a hitelesítő kódot.'
          snackbar.value = true
          return
        }
        payload.kod = disableForm.value.code.trim()
      }
    }

    const { data } = await api.post('/two-factor/disable', payload)
    snackbarText.value = data?.message || 'A kétlépcsős azonosítás kikapcsolva.'
    snackbar.value = true
    disableDialog.value = false
    await loadTwoFactorStatus()
    twoFactorSetup.value = null
    recoveryCodes.value = []
  } catch (e) {
    snackbarText.value = e?.response?.data?.message || 'A kikapcsolás nem sikerült.'
    snackbar.value = true
  } finally {
    disableLoading.value = false
  }
}

const openRegenerateDialog = () => {
  regenDialog.value = true
  regenForm.value = { useRecovery: false, code: '', recovery: '' }
  regenCodes.value = []
}

const submitRegenerate = async () => {
  regenLoading.value = true
  try {
    const payload = {}
    if (regenForm.value.useRecovery) {
      if (!regenForm.value.recovery.trim()) {
        regenLoading.value = false
        snackbarText.value = 'Adj meg egy helyreállító kódot a regeneráláshoz.'
        snackbar.value = true
        return
      }
      payload.helyreallito_kod = regenForm.value.recovery.trim()
    } else {
      if (!regenForm.value.code.trim()) {
        regenLoading.value = false
        snackbarText.value = 'Add meg a hitelesítő kódot a regeneráláshoz.'
        snackbar.value = true
        return
      }
      payload.kod = regenForm.value.code.trim()
    }

    const { data } = await api.post('/two-factor/recovery/regenerate', payload)
    regenCodes.value = data?.recovery_codes || []
    snackbarText.value = data?.message || 'Új helyreállító kódok generálva.'
    snackbar.value = true
    await loadTwoFactorStatus()
  } catch (e) {
    snackbarText.value = e?.response?.data?.message || 'A kódok újragenerálása nem sikerült.'
    snackbar.value = true
  } finally {
    regenLoading.value = false
  }
}

const toggleDisableMode = () => {
  disableForm.value.useRecovery = !disableForm.value.useRecovery
  disableForm.value.code = ''
  disableForm.value.recovery = ''
}

const toggleRegenMode = () => {
  regenForm.value.useRecovery = !regenForm.value.useRecovery
  regenForm.value.code = ''
  regenForm.value.recovery = ''
}

watch(confirmDialog, (value) => {
  if (!value) {
    confirmPassword.value = ''
    pendingPayload.value = null
    showConfirmPassword.value = false
    saving.value = false
  }
})
</script>

<template>
  <v-container class="py-6" fluid>
    <v-card max-width="720" class="mx-auto">
      <v-card-title class="text-h6">Profilom</v-card-title>
      <v-divider />
      <v-card-text>
        <template v-if="user">
          <v-form ref="formRef">
            <div class="d-flex flex-column gap-2">
              <v-text-field v-model="editable.nev" label="Név" :readonly="!editing" :rules="rules.name" variant="outlined" density="comfortable" />
              <v-text-field
                v-model="editable.felhasznalonev"
                label="Felhasználónév"
                :readonly="!editing"
                :rules="rules.username"
                variant="outlined"
                density="comfortable"
                autocomplete="username"
                hint="Engedélyezett: betű, szám, pont, kötőjel, aláhúzás"
                persistent-hint
              />
              <v-text-field v-model="editable.email" label="E-mail" :readonly="!editing" :rules="rules.email" variant="outlined" density="comfortable" />
              <v-text-field
                v-model="editable.telefonszam"
                label="Telefonszám"
                :readonly="!editing"
                :rules="rules.phone"
                variant="outlined"
                density="comfortable"
                autocomplete="tel"
                placeholder="+36205012465"
                hint="Példa formátum: +36205012465"
                persistent-hint
              />
              <v-text-field :model-value="displayedRole" label="Szerep" readonly variant="outlined" density="comfortable" />
              <div class="text-error text-caption mt-1">A jogosultság módosítása az adminisztrátor jogköréhez tartozik.</div>
              <v-text-field
                v-if="editing"
                class="password-field"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                label="Új jelszó"
                prepend-inner-icon="mdi-lock"
                :rules="rules.password"
                variant="outlined"
                density="comfortable"
              >
                <template #append-inner>
                  <v-btn
                    class="password-toggle-btn"
                    icon
                    variant="text"
                    size="small"
                    @click="showPassword = !showPassword"
                  >
                    <v-icon
                      class="password-toggle-icon"
                      :icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                      color="primary"
                      size="small"
                    />
                  </v-btn>
                </template>
              </v-text-field>
              <v-text-field
                v-if="editing"
                class="password-field"
                v-model="passwordConfirm"
                :type="showPasswordConfirm ? 'text' : 'password'"
                label="Jelszó megerősítése"
                prepend-inner-icon="mdi-lock-check"
                :rules="rules.confirm"
                variant="outlined"
                density="comfortable"
              >
                <template #append-inner>
                  <v-btn
                    class="password-toggle-btn"
                    icon
                    variant="text"
                    size="small"
                    @click="showPasswordConfirm = !showPasswordConfirm"
                  >
                    <v-icon
                      class="password-toggle-icon"
                      :icon="showPasswordConfirm ? 'mdi-eye-off' : 'mdi-eye'"
                      color="primary"
                      size="small"
                    />
                  </v-btn>
                </template>
              </v-text-field>
            </div>
            <div class="d-flex justify-end ga-2 mt-4">
              <v-btn v-if="!editing" color="primary" variant="elevated" @click="startEdit">Szerkesztés</v-btn>
              <template v-else>
                <v-btn variant="text" @click="cancelEdit">Mégse</v-btn>
                <v-btn color="primary" variant="elevated" :loading="saving" :disabled="saving || confirmDialog" @click="saveProfile">Mentés</v-btn>
              </template>
            </div>
          </v-form>

          <v-divider class="my-6" />

          <section class="twofactor-section">
            <div class="d-flex flex-wrap justify-space-between align-center gap-4">
              <div>
                <div class="text-subtitle-1 font-weight-medium">Kétlépcsős azonosítás</div>
                <div class="text-body-2 text-medium-emphasis mt-1">
                  <template v-if="twoFactorLoading">Állapot betöltése…</template>
                  <template v-else-if="isTwoFactorActive">Aktív — hátralévő helyreállító kódok: {{ twoFactorStatus.recovery_hatralevo }}</template>
                  <template v-else-if="isTwoFactorPending">Folyamatban lévő beállítás. Erősítsd meg az alkalmazásból kapott kóddal.</template>
                  <template v-else>Javasoljuk, hogy kapcsold be az extra védelem érdekében.</template>
                </div>
              </div>
              <div class="d-flex flex-wrap ga-2">
                <v-btn
                  v-if="!isTwoFactorActive"
                  color="primary"
                  variant="elevated"
                  :loading="twoFactorSetupLoading"
                  :disabled="twoFactorSetupLoading"
                  @click="startTwoFactor"
                >
                  {{ isTwoFactorPending ? 'Beállítás folytatása' : 'Bekapcsolás' }}
                </v-btn>
                <v-btn
                  v-if="isTwoFactorActive"
                  color="secondary"
                  variant="outlined"
                  :disabled="twoFactorSetupLoading"
                  @click="openRegenerateDialog"
                >
                  Új helyreállító kódok
                </v-btn>
                <v-btn
                  v-if="isTwoFactorActive || isTwoFactorPending"
                  color="error"
                  variant="tonal"
                  :disabled="twoFactorSetupLoading"
                  @click="openDisableDialog"
                >
                  Kikapcsolás
                </v-btn>
              </div>
            </div>

            <v-expand-transition>
              <div v-if="twoFactorSetup" class="twofactor-setup mt-4">
                <div class="text-body-2 text-medium-emphasis mb-3">
                  Olvasd be az alábbi QR kódot egy hitelesítő alkalmazással (Google Authenticator, Microsoft Authenticator stb.), majd írd be a megjelenő 6 számjegyű kódot.
                </div>
                <div class="d-flex flex-wrap ga-4 align-center">
                  <div v-if="qrDataUrl" class="qr-wrapper">
                    <img :src="qrDataUrl" alt="Hitelesítő QR kód" width="200" height="200" />
                  </div>
                  <div class="flex-grow-1">
                    <v-text-field
                      v-model="twoFactorSetup.secret"
                      label="Titkos kulcs"
                      readonly
                      variant="outlined"
                      density="comfortable"
                    />
                    <v-text-field
                      v-model="twoFactorCode"
                      label="Hitelesítő kód"
                      prepend-inner-icon="mdi-shield-key"
                      inputmode="numeric"
                      maxlength="6"
                      variant="outlined"
                      density="comfortable"
                    />
                    <div class="text-caption text-medium-emphasis mt-1">
                      A kód rövid ideig érvényes, ha lejár, írj be egy újat az alkalmazásból.
                    </div>
                    <div class="d-flex ga-2 mt-4">
                      <v-btn color="primary" variant="elevated" :loading="twoFactorConfirmLoading" @click="confirmTwoFactor">
                        Aktiválás
                      </v-btn>
                      <v-btn variant="text" :disabled="twoFactorConfirmLoading" @click="cancelTwoFactorSetup">Mégse</v-btn>
                    </div>
                  </div>
                </div>
                <div class="mt-4">
                  <div class="text-subtitle-2 mb-2">Helyreállító kódok</div>
                  <div class="text-body-2 text-medium-emphasis mb-2">Írd fel ezeket a kódokat biztonságos helyre. Minden kód egyszer használható.</div>
                  <div class="recovery-grid">
                    <div v-for="code in recoveryCodes" :key="code" class="recovery-chip">{{ code }}</div>
                  </div>
                </div>
              </div>
            </v-expand-transition>

            <v-expand-transition>
              <div v-if="regenCodes.length" class="mt-4">
                <v-alert type="success" variant="tonal" density="comfortable">
                  Új helyreállító kódok:
                  <div class="recovery-grid mt-2">
                    <div v-for="code in regenCodes" :key="code" class="recovery-chip">{{ code }}</div>
                  </div>
                </v-alert>
              </div>
            </v-expand-transition>
          </section>
        </template>
        <div v-else class="text-medium-emphasis">Nem található felhasználói adat.</div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="disableDialog" max-width="420">
      <v-card>
        <v-card-title class="text-h6">Kétlépcsős azonosítás kikapcsolása</v-card-title>
        <v-card-text>
          <div class="text-body-2 text-medium-emphasis mb-3">
            A biztonság érdekében add meg a hitelesítő alkalmazás aktuális kódját vagy egy érvényes helyreállító kódot.
          </div>
          <v-btn variant="text" size="small" class="mb-3" @click="toggleDisableMode">
            {{ disableForm.useRecovery ? 'Hitelesítő kód használata' : 'Helyreállító kód használata' }}
          </v-btn>
          <v-text-field
            v-if="!disableForm.useRecovery"
            v-model="disableForm.code"
            label="Hitelesítő kód"
            prepend-inner-icon="mdi-shield-key"
            inputmode="numeric"
            maxlength="6"
            variant="outlined"
            density="comfortable"
          />
          <v-text-field
            v-else
            v-model="disableForm.recovery"
            label="Helyreállító kód"
            prepend-inner-icon="mdi-lifebuoy"
            variant="outlined"
            density="comfortable"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="disableDialog = false">Mégse</v-btn>
          <v-btn color="error" :loading="disableLoading" @click="submitDisable">Kikapcsolás</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="confirmDialog" max-width="420">
      <v-card>
        <v-card-title class="text-h6">Mentés megerősítése</v-card-title>
        <v-card-text>
          <div class="text-body-2 text-medium-emphasis mb-3">
            A profilod módosításához add meg a jelenlegi jelszavadat.
          </div>
          <v-text-field
            v-model="confirmPassword"
            :type="showConfirmPassword ? 'text' : 'password'"
            label="Jelenlegi jelszó"
            prepend-inner-icon="mdi-lock"
            variant="outlined"
            density="comfortable"
            :append-inner-icon="showConfirmPassword ? 'mdi-eye-off' : 'mdi-eye'"
            @click:append-inner="showConfirmPassword = !showConfirmPassword"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="saving" @click="confirmDialog = false">Mégse</v-btn>
          <v-btn color="primary" :loading="saving" :disabled="saving" @click="confirmSave">Megerősítés</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="regenDialog" max-width="420">
      <v-card>
        <v-card-title class="text-h6">Új helyreállító kódok</v-card-title>
        <v-card-text>
          <div class="text-body-2 text-medium-emphasis mb-3">
            A régi helyreállító kódok érvénytelenné válnak. Add meg a hitelesítő kódot vagy egy jelenlegi helyreállító kódot.
          </div>
          <v-btn variant="text" size="small" class="mb-3" @click="toggleRegenMode">
            {{ regenForm.useRecovery ? 'Hitelesítő kód használata' : 'Helyreállító kód használata' }}
          </v-btn>
          <v-text-field
            v-if="!regenForm.useRecovery"
            v-model="regenForm.code"
            label="Hitelesítő kód"
            prepend-inner-icon="mdi-shield-key"
            inputmode="numeric"
            maxlength="6"
            variant="outlined"
            density="comfortable"
          />
          <v-text-field
            v-else
            v-model="regenForm.recovery"
            label="Helyreállító kód"
            prepend-inner-icon="mdi-lifebuoy"
            variant="outlined"
            density="comfortable"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="regenDialog = false">Mégse</v-btn>
          <v-btn color="secondary" :loading="regenLoading" @click="submitRegenerate">Új kódok</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar" color="primary" timeout="2500">{{ snackbarText }}</v-snackbar>
  </v-container>
</template>

<style scoped>
.gap-2 > * + * { margin-top: 8px; }

:deep(.password-field .v-field__append-inner) {
  opacity: 1 !important;
}

:deep(.password-field .password-toggle-btn) {
  opacity: 1 !important;
  color: var(--v-theme-primary);
}

:deep(.password-field .password-toggle-btn .v-icon) {
  opacity: 1 !important;
  color: inherit;
}

:deep(.password-field .password-toggle-icon) {
  opacity: 1 !important;
}

.twofactor-section {
  padding-bottom: 8px;
}

.qr-wrapper {
  padding: 12px;
  border: 1px dashed rgba(76, 175, 80, 0.4);
  border-radius: 12px;
  background: rgba(76, 175, 80, 0.05);
}

.recovery-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}

.recovery-chip {
  padding: 8px 12px;
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.04);
  font-family: 'Roboto Mono', monospace;
  font-size: 0.9rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
</style>

