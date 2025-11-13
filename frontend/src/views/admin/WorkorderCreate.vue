<template>
  <v-container fluid class="pa-4">
    <!-- Toolbar -->
    <v-toolbar density="comfortable" color="white" elevation="0" class="mb-4">
      <v-btn variant="elevated" color="primary" prepend-icon="mdi-arrow-left" @click="goBack">Vissza</v-btn>
      <v-toolbar-title>Új munkalap</v-toolbar-title>
      <v-spacer />
      <v-btn class="create-btn" color="primary" variant="elevated" prepend-icon="mdi-plus" :loading="saving" :disabled="disableCreate" @click="createWorkorder">Létrehozás</v-btn>
    </v-toolbar>

    <v-alert v-if="errorMsg" type="error" variant="tonal" class="mb-3">{{ errorMsg }}</v-alert>
    <v-snackbar v-model="snackbar" :timeout="3000" :color="snackbarColor" location="top right">
      {{ snackbarText }}
      <template #actions>
        <v-btn variant="text" @click="snackbar = false">OK</v-btn>
      </template>
    </v-snackbar>

    <v-row>
      <!-- Ugyfel blokk -->
      <v-col cols="12" md="6" lg="4">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Ugyfel</v-card-title>
          <v-divider />
          <v-card-text>
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="text-caption text-medium-emphasis">Regisztrált Ugyfel</div>
              <v-switch hide-details color="primary" v-model="isRegistered" inset></v-switch>
            </div>

            <!-- Regisztrált -->
            <div v-if="isRegistered">
              <v-autocomplete
                v-model="Ugyfel"
                :items="UgyfelItems"
                :loading="UgyfelLoading"
                :search="UgyfelSearch"
                @update:search="onSearchUgyfel"
                item-title="nev"
                item-value="id"
                return-object
                label="Ugyfel kiválasztása"
                variant="outlined"
                density="comfortable"
                clearable
                @focus="onSearchUgyfel('')"
              />

              <!-- kiválasztott Ugyfel összegzés -->
              <v-alert v-if="Ugyfel" type="info" variant="tonal" class="mt-2">
                <div><strong>{{ Ugyfel.nev }}</strong></div>
                <div class="text-medium-emphasis">{{ Ugyfel.email || '-' }}</div>
                <div class="text-medium-emphasis">{{ Ugyfel.telefonszam || '-' }}</div>
              </v-alert>
            </div>

            <!-- Nem regisztrált -->
            <div v-else>
              <v-text-field v-model.trim="Ugyfel_nev" :rules="nameRules" label="Név" variant="outlined" density="comfortable" />
              <v-text-field v-model.trim="Ugyfel_email" :rules="emailRules" label="E-mail" type="email" variant="outlined" density="comfortable" />
              <v-text-field
                v-model.trim="Ugyfel_telefon"
                :rules="phoneRules"
                label="Telefonszám"
                variant="outlined"
                density="comfortable"
                autocomplete="tel"
                placeholder="+36205012465"
                hint="Példa formátum: +36205012465"
                persistent-hint
              />
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Gép blokk -->
      <v-col cols="12" md="6" lg="4">
        <v-card class="mb-4">
          <v-card-title class="d-flex align-center">
            <span class="text-subtitle-1">Gép</span>
            <v-spacer />
            <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-plus" @click="addMachineDialog = true">Új gép</v-btn>
          </v-card-title>
          <v-divider />
          <v-card-text>
            <v-autocomplete
              v-model="gep"
              :items="gepItems"
              :loading="gepLoading"
              :search="gepSearch"
              @update:search="onSearchGep"
              :item-title="gepItemTitle"
              :item-value="item => item.ID"
              return-object
              label="Gép kiválasztása (cikkszám / gyártó / típus)"
              variant="outlined"
              density="comfortable"
              clearable
              @focus="onSearchGep('')"
            />

            <!-- kiválasztott gép összegzés -->
            <v-alert v-if="gep" type="info" variant="tonal" class="mt-2">
              <div><strong>{{ gep.gyarto || '-' }}</strong> — {{ gep.tipusnev || '-' }}</div>
              <div class="text-medium-emphasis">Cikkszám: {{ gep.g_cikkszam || '-' }}</div>
              <div class="text-medium-emphasis">Gyártási év: {{ gep.gyartasiev ?? '-' }}</div>
            </v-alert>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Leírások -->
      <v-col cols="12" lg="4">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Leírások</v-card-title>
          <v-divider />
          <v-card-text>
            <v-textarea v-model="hibaleiras" label="Hibaleírás" auto-grow rows="3" variant="outlined" density="comfortable" />
            <v-textarea v-model="megjegyzes" label="Megjegyzés" auto-grow rows="2" variant="outlined" density="comfortable" />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Dialog: Új gép felvétele -->
    <v-dialog v-model="addMachineDialog" max-width="560">
      <v-card>
        <v-card-title class="text-subtitle-1">Új gép hozzáadása</v-card-title>
        <v-divider />
        <v-card-text>
          <v-text-field v-model="newMachine.gyarto" label="Gyártó" variant="outlined" density="comfortable" />
          <v-text-field v-model="newMachine.tipusnev" label="Típusnév" variant="outlined" density="comfortable" />
          <v-text-field v-model="newMachine.g_cikkszam" label="Cikkszám" variant="outlined" density="comfortable" />
          <v-text-field v-model="newMachine.gyartasiev" label="Gyártási év" type="number" variant="outlined" density="comfortable" />
        </v-card-text>
        <v-card-actions class="justify-end">
          <v-btn variant="tonal" @click="addMachineDialog = false">Mégse</v-btn>
          <v-btn color="primary" variant="elevated" :loading="addingMachine" :disabled="addingMachine || !canAddMachine" @click="addMachine">Hozzáadás</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
  </template>

<script setup>
import * as Vue from 'vue'
import { useRouter } from 'vue-router'
import { ensureEuropeanPhone } from '../../utils/phone.js'

// Lightweight request helper (matches other views)
async function request(path, { method = 'GET', body } = {}) {
  const url = `/api${path}`
  const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' }
  try { const tk = localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('AUTH_TOKEN'); if (tk) headers['Authorization'] = `Bearer ${tk}` } catch {}
  const res = await fetch(url + (method === 'GET' && body ? `?${new URLSearchParams(body)}` : ''), { method, headers, body: method === 'GET' ? undefined : JSON.stringify(body ?? {}), credentials: 'include' })
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${await res.text().catch(() => '')}`)
  const ct = res.headers.get('content-type') || ''
  return ct.includes('application/json') ? res.json() : null
}

const router = useRouter()

// Snackbar
const snackbar = Vue.ref(false)
const snackbarText = Vue.ref('')
const snackbarColor = Vue.ref('success')
function setSnack(text, color = 'success'){ snackbarText.value = text; snackbarColor.value = color; snackbar.value = true }

// State
const isRegistered = Vue.ref(true)

const Ugyfel = Vue.ref(null)
const UgyfelItems = Vue.ref([])
const UgyfelSearch = Vue.ref('')
const UgyfelLoading = Vue.ref(false)
const Ugyfel_nev = Vue.ref('')
const Ugyfel_email = Vue.ref('')
const Ugyfel_telefon = Vue.ref('')

const gep = Vue.ref(null)
const gepItems = Vue.ref([])
const gepSearch = Vue.ref('')
const gepLoading = Vue.ref(false)

const hibaleiras = Vue.ref('')
const megjegyzes = Vue.ref('')
const saving = Vue.ref(false)
const errorMsg = Vue.ref('')

// New machine dialog
const addMachineDialog = Vue.ref(false)
const addingMachine = Vue.ref(false)
const newMachine = Vue.reactive({ gyarto: '', tipusnev: '', g_cikkszam: '', gyartasiev: '' })
const canAddMachine = Vue.computed(()=> !!(newMachine.gyarto && newMachine.tipusnev && newMachine.g_cikkszam && String(newMachine.gyartasiev).length >= 4))

// Helpers
function gepItemTitle(item){ if(!item) return '-'; return [item.gyarto, item.tipusnev, item.g_cikkszam].filter(Boolean).join(' — ') }

// Search handlers (debounced)
let tU = null
async function onSearchUgyfel(q){
  UgyfelSearch.value = q
  if(tU) clearTimeout(tU)
  tU = setTimeout(async ()=>{
    UgyfelLoading.value = true
    try{ UgyfelItems.value = await request('/felhasznalok', { method: 'GET', body: { q: q || '', limit: '20' } }) }
    catch(e){ console.error(e) }
    finally{ UgyfelLoading.value = false }
  }, 200)
}

let tG = null
async function onSearchGep(q){
  gepSearch.value = q
  if(tG) clearTimeout(tG)
  tG = setTimeout(async ()=>{
    gepLoading.value = true
    try{ gepItems.value = await request('/gepek', { method: 'GET', body: { q: q || '', limit: '20' } }) }
    catch(e){ console.error(e) }
    finally{ gepLoading.value = false }
  }, 200)
}

// Add machine from dialog
async function addMachine(){
  if(!canAddMachine.value) return
  addingMachine.value = true
  try{
    // Ensure year is a 4-digit number
    const payload = { ...newMachine, gyartasiev: Number(String(newMachine.gyartasiev).slice(0,4)) || 0 }
    const rec = await request('/gepek', { method: 'POST', body: payload })
    gep.value = rec
    // Reset dialog state
    Object.assign(newMachine, { gyarto: '', tipusnev: '', g_cikkszam: '', gyartasiev: '' })
    addMachineDialog.value = false
    setSnack('Gép hozzáadva')
  }catch(e){ setSnack(e?.message || 'Gép hozzáadása sikertelen', 'error') }
  finally{ addingMachine.value = false }
}

// Simple validators
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const emailValid = Vue.computed(()=> isRegistered.value ? true : emailPattern.test((Ugyfel_email.value||'').trim()))
const nameValid = Vue.computed(()=> isRegistered.value ? true : !!(Ugyfel_nev.value||'').trim())
const phoneValid = Vue.computed(()=> isRegistered.value ? true : !!ensureEuropeanPhone(Ugyfel_telefon.value))
const nameRules = [ v => isRegistered.value || (!!(v||'').trim()) || 'Név megadása kötelező' ]
const emailRules = [ v => isRegistered.value || (emailPattern.test((v||'').trim()) || 'Érvénytelen e-mail cím') ]
const phoneRules = [
  v => isRegistered.value || (!!(v||'').trim()) || 'Telefonszám megadása kötelező',
  v => (isRegistered.value || ensureEuropeanPhone(v)) ? true : 'Érvényes magyar vagy európai telefonszámot adj meg (pl. +36205012465)'
]

// Create workorder
const disableCreate = Vue.computed(()=>{
  const hasGep = !!(gep.value && (gep.value.ID || gep.value.id))
  const hasUser = isRegistered.value
    ? !!(Ugyfel.value && (Ugyfel.value.id || Ugyfel.value.ID))
  : (nameValid.value && emailValid.value && phoneValid.value)
  return !(hasGep && hasUser) || saving.value
})

async function createWorkorder(){
  if(disableCreate.value) return
  errorMsg.value = ''
  saving.value = true
  try{
    const payload = {
      gep_id: gep.value?.ID ?? gep.value?.id,
      statusz: 'uj'
    }
    if(isRegistered.value){
      payload.user_id = Ugyfel.value?.id ?? Ugyfel.value?.ID
    } else {
      payload.regisztralt = false
      // Client-side guard: prevent invalid email from being sent
      const trimmedName = (Ugyfel_nev.value||'').trim()
      const trimmedEmail = (Ugyfel_email.value||'').trim()
      if(!trimmedName){ setSnack('Név megadása kötelező', 'error'); saving.value=false; return }
      if(!emailPattern.test(trimmedEmail)){ setSnack('Érvénytelen e-mail cím', 'error'); saving.value=false; return }
      const canonicalPhone = ensureEuropeanPhone(Ugyfel_telefon.value)
  if(!canonicalPhone){ setSnack('Érvényes magyar vagy európai telefonszámot adj meg (pl. +36205012465).', 'error'); saving.value=false; return }
      payload.Ugyfel_nev = trimmedName
      payload.Ugyfel_email = trimmedEmail.toLowerCase()
      payload.Ugyfel_telefon = canonicalPhone
    }
    if(hibaleiras.value) payload.hibaleiras = hibaleiras.value
    if(megjegyzes.value) payload.megjegyzes = megjegyzes.value

    const created = await request('/munkalapok', { method: 'POST', body: payload })
    const newId = created?.ID ?? created?.id
    setSnack('Munkalap létrehozva')
    if(newId){ router.push(`/admin/munkalapok/${newId}`) }
    else { router.push('/admin/munkalapok') }
  }catch(e){
    console.error(e)
    errorMsg.value = e?.message || 'Létrehozás sikertelen'
    setSnack(errorMsg.value, 'error')
  } finally {
    saving.value = false
  }
}

function goBack(){ try{ if(window.history && window.history.length>1) router.back(); else router.push('/admin/munkalapok') } catch { router.push('/admin/munkalapok') } }

// Prime lists on mount
if(typeof window !== 'undefined'){
  onSearchUgyfel('')
  onSearchGep('')
}
</script>

<style scoped>
.create-btn{ font-weight: 800; letter-spacing: .2px; }
</style>
