<script setup>
import { ref, onMounted, computed } from 'vue'
import { api } from '../api.js'

const user = ref(null)
const formRef = ref(null)
const editable = ref({ nev: '', email: '', jogosultsag: '', telefonszam: '' })
const password = ref('')
const passwordConfirm = ref('')
const showPassword = ref(false)
const showPasswordConfirm = ref(false)
const editing = ref(false)
const saving = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')

const roleLabels = {
  ugyfel: 'Ügyfél',
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
  email: [
    v => !!(v?.trim()) || 'Az e-mail megadása kötelező',
    v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Érvényes e-mail címet adj meg'
  ],
  phone: [
    v => !v || /^\+?[0-9\s-]{6,20}$/.test(v) || 'Érvényes telefonszámot adj meg'
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
})

function startEdit(){
  if(!user.value) return
  editable.value = {
    nev: user.value.nev || '',
    email: user.value.email || '',
    jogosultsag: user.value.jogosultsag || '',
    telefonszam: user.value.telefonszam || ''
  }
  password.value = ''
  passwordConfirm.value = ''
  editing.value = true
}
function cancelEdit(){ editing.value = false }
async function saveProfile(){
  if(!user.value) return
  saving.value = true
  const result = await formRef.value?.validate?.()
  if(!result?.valid){ saving.value = false; return }
  try{
    const payload = {
      nev: editable.value.nev.trim(),
      email: editable.value.email.trim()
    }

    const phone = editable.value.telefonszam?.trim()
    if (phone) {
      payload.telefonszam = phone
    }
    if(password.value){
  payload.password = password.value
  payload.password_confirmation = passwordConfirm.value
    }
    const { data } = await api.put('/profile', payload)
    user.value = data
    localStorage.setItem('user', JSON.stringify(data))
    snackbarText.value = 'Profil frissítve'
    snackbar.value = true
    editing.value = false
    password.value = ''
    passwordConfirm.value = ''
  }catch(e){
    snackbarText.value = e?.response?.data?.message || e?.message || 'Mentési hiba'
    snackbar.value = true
  }finally{
    saving.value = false
  }
}
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
              <v-text-field v-model="editable.email" label="E-mail" :readonly="!editing" :rules="rules.email" variant="outlined" density="comfortable" />
              <v-text-field v-model="editable.telefonszam" label="Telefonszám" :readonly="!editing" :rules="rules.phone" variant="outlined" density="comfortable" />
              <v-text-field :model-value="displayedRole" label="Szerep" readonly variant="outlined" density="comfortable" />
              <div class="text-error text-caption mt-1">A jogosultság módosítása csak rendszergazda feladata.</div>
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
                <v-btn color="primary" variant="elevated" :loading="saving" :disabled="saving" @click="saveProfile">Mentés</v-btn>
              </template>
            </div>
          </v-form>
        </template>
        <div v-else class="text-medium-emphasis">Nem található felhasználói adat.</div>
      </v-card-text>
    </v-card>
    <v-snackbar v-model="snackbar" color="primary" timeout="2000">{{ snackbarText }}</v-snackbar>
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
</style>

