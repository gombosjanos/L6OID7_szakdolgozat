<template>
  <v-container fluid class="pa-4">
    <v-toolbar density="comfortable" color="white" elevation="0" class="mb-4">
      <v-btn
        variant="elevated"
        color="primary"
        prepend-icon="mdi-arrow-left"
        @click="goBack"
      >
        Vissza
      </v-btn>
      <v-toolbar-title>Munkalap szerkesztése</v-toolbar-title>
      <v-spacer />
      <v-btn
        class="create-btn"
        color="primary"
        variant="elevated"
        prepend-icon="mdi-content-save"
        :loading="saving"
        :disabled="disableSave"
        @click="updateWorkorder"
      >
        Mentés
      </v-btn>
    </v-toolbar>

    <v-alert
      v-if="errorMsg"
      type="error"
      variant="tonal"
      class="mb-3"
    >
      {{ errorMsg }}
    </v-alert>
    <v-snackbar
      v-model="snackbar"
      :timeout="3000"
      :color="snackbarColor"
      location="top right"
    >
      {{ snackbarText }}
      <template #actions>
        <v-btn variant="text" @click="snackbar = false">OK</v-btn>
      </template>
    </v-snackbar>

    <v-row>
      <!-- Ügyfél blokk (csak regisztrált) -->
      <v-col cols="12" md="6" lg="4">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Ügyfél</v-card-title>
          <v-divider />
          <v-card-text>
            <v-autocomplete
              v-model="Ugyfel"
              :items="UgyfelItems"
              :loading="UgyfelLoading"
              :search="UgyfelSearch"
              @update:search="onSearchUgyfel"
              item-title="nev"
              item-value="id"
              return-object
              label="Ügyfél kiválasztása"
              variant="outlined"
              density="comfortable"
              clearable
              @focus="onSearchUgyfel('')"
            />
            <v-alert
              v-if="Ugyfel"
              type="info"
              variant="tonal"
              class="mt-2"
            >
              <div><strong>{{ Ugyfel.nev }}</strong></div>
              <div class="text-medium-emphasis">
                {{ Ugyfel.email || '-' }}
              </div>
              <div class="text-medium-emphasis">
                {{ Ugyfel.telefonszam || '-' }}
              </div>
            </v-alert>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Gép blokk -->
      <v-col cols="12" md="6" lg="4">
        <v-card class="mb-4">
          <v-card-title class="d-flex align-center">
            <span class="text-subtitle-1">Gép</span>
            <v-spacer />
            <v-btn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="mdi-plus"
              @click="addMachineDialog = true"
            >
              Új gép
            </v-btn>
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
            <v-alert
              v-if="gep"
              type="info"
              variant="tonal"
              class="mt-2"
            >
              <div>
                <strong>{{ gep.gyarto || '-' }}</strong>
                – {{ gep.tipusnev || '-' }}
              </div>
              <div class="text-medium-emphasis">
                Cikkszám: {{ gep.g_cikkszam || '-' }}
              </div>
              <div class="text-medium-emphasis">
                Gyártási év: {{ gep.gyartasiev ?? '-' }}
              </div>
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
            <v-textarea
              v-model="hibaleiras"
              label="Hiba leírás"
              auto-grow
              rows="3"
              variant="outlined"
              density="comfortable"
            />
            <v-textarea
              v-model="megjegyzes"
              label="Megjegyzés"
              auto-grow
              rows="2"
              variant="outlined"
              density="comfortable"
              class="mt-3"
            />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Új gép felvétele -->
    <v-dialog v-model="addMachineDialog" max-width="560">
      <v-card>
        <v-card-title class="text-subtitle-1">
          Új gép hozzáadása
        </v-card-title>
        <v-divider />
        <v-card-text>
          <v-text-field
            v-model="newMachine.gyarto"
            label="Gyártó"
            variant="outlined"
            density="comfortable"
          />
          <v-text-field
            v-model="newMachine.tipusnev"
            label="Típusnév"
            variant="outlined"
            density="comfortable"
          />
          <v-text-field
            v-model="newMachine.g_cikkszam"
            label="Cikkszám"
            variant="outlined"
            density="comfortable"
          />
          <v-text-field
            v-model="newMachine.gyartasiev"
            label="Gyártási év"
            type="number"
            variant="outlined"
            density="comfortable"
          />
        </v-card-text>
        <v-card-actions class="justify-end">
          <v-btn variant="tonal" @click="addMachineDialog = false">
            Mégse
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            :loading="addingMachine"
            :disabled="addingMachine || !canAddMachine"
            @click="addMachine"
          >
            Hozzáadás
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import * as Vue from 'vue'
import { useRoute, useRouter } from 'vue-router'

async function request (path, { method = 'GET', body } = {}) {
  const url = `/api${path}`
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json'
  }
  try {
    const tk =
      localStorage.getItem('auth_token') ||
      localStorage.getItem('token') ||
      localStorage.getItem('AUTH_TOKEN')
    if (tk) headers.Authorization = `Bearer ${tk}`
  } catch {}
  const res = await fetch(
    url +
      (method === 'GET' && body
        ? `?${new URLSearchParams(body)}`
        : ''),
    {
      method,
      headers,
      body: method === 'GET' ? undefined : JSON.stringify(body ?? {}),
      credentials: 'include'
    }
  )
  if (!res.ok) {
    throw new Error(
      `HTTP ${res.status}: ${await res.text().catch(() => '')}`
    )
  }
  const ct = res.headers.get('content-type') || ''
  return ct.includes('application/json') ? res.json() : null
}

const route = useRoute()
const router = useRouter()
const id = Vue.computed(() => route.params.id)

const snackbar = Vue.ref(false)
const snackbarText = Vue.ref('')
const snackbarColor = Vue.ref('success')
function setSnack (text, color = 'success') {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

const Ugyfel = Vue.ref(null)
const UgyfelItems = Vue.ref([])
const UgyfelSearch = Vue.ref('')
const UgyfelLoading = Vue.ref(false)

const gep = Vue.ref(null)
const gepItems = Vue.ref([])
const gepSearch = Vue.ref('')
const gepLoading = Vue.ref(false)

const hibaleiras = Vue.ref('')
const megjegyzes = Vue.ref('')
const saving = Vue.ref(false)
const errorMsg = Vue.ref('')

const addMachineDialog = Vue.ref(false)
const addingMachine = Vue.ref(false)
const newMachine = Vue.reactive({
  gyarto: '',
  tipusnev: '',
  g_cikkszam: '',
  gyartasiev: ''
})
const canAddMachine = Vue.computed(
  () =>
    !!(
      newMachine.gyarto &&
      newMachine.tipusnev &&
      newMachine.g_cikkszam &&
      String(newMachine.gyartasiev || '').length >= 4
    )
)

function gepItemTitle (item) {
  if (!item) return '-'
  return [item.gyarto, item.tipusnev, item.g_cikkszam]
    .filter(Boolean)
    .join(' - ')
}

let tU = null
async function onSearchUgyfel (q) {
  UgyfelSearch.value = q
  if (tU) clearTimeout(tU)
  tU = setTimeout(async () => {
    UgyfelLoading.value = true
    try {
      UgyfelItems.value = await request('/felhasznalok', {
        method: 'GET',
        body: { q: q || '', limit: '20' }
      })
    } catch (e) {
      console.error(e)
    } finally {
      UgyfelLoading.value = false
    }
  }, 200)
}

let tG = null
async function onSearchGep (q) {
  gepSearch.value = q
  if (tG) clearTimeout(tG)
  tG = setTimeout(async () => {
    gepLoading.value = true
    try {
      gepItems.value = await request('/gepek', {
        method: 'GET',
        body: { q: q || '', limit: '20' }
      })
    } catch (e) {
      console.error(e)
    } finally {
      gepLoading.value = false
    }
  }, 200)
}

async function addMachine () {
  if (!canAddMachine.value) return
  addingMachine.value = true
  try {
    const created = await request('/gepek', {
      method: 'POST',
      body: {
        gyarto: newMachine.gyarto,
        tipusnev: newMachine.tipusnev,
        g_cikkszam: newMachine.g_cikkszam,
        gyartasiev: newMachine.gyartasiev
      }
    })
    gep.value = created
    setSnack('Gép hozzáadva', 'success')
    addMachineDialog.value = false
  } catch (e) {
    errorMsg.value = e?.message || 'Nem sikerült létrehozni a gépet.'
  } finally {
    addingMachine.value = false
  }
}

const disableSave = Vue.computed(() => {
  const hasCustomer = !!(Ugyfel.value && (Ugyfel.value.id || Ugyfel.value.ID))
  const hasMachine = !!(gep.value && (gep.value.ID || gep.value.id))
  return saving.value || !hasCustomer || !hasMachine
})

async function loadExisting () {
  if (!id.value) return
  try {
    const data = await request(`/munkalapok/${id.value}`)
    // ügyfél
    if (data.Ugyfel || data.ugyfel) {
      Ugyfel.value = data.Ugyfel || data.ugyfel
    }
    // gép
    if (data.gep) {
      gep.value = data.gep
    }
    hibaleiras.value = data.hibaleiras || ''
    megjegyzes.value = data.megjegyzes || ''
  } catch (e) {
    errorMsg.value =
      e?.message || 'Nem sikerült betölteni a munkalap adatait.'
  }
}

async function updateWorkorder () {
  if (!id.value) return
  saving.value = true
  errorMsg.value = ''
  try {
    const payload = {
      user_id: Ugyfel.value?.id || Ugyfel.value?.ID || null,
      gep_id: gep.value?.ID || gep.value?.id || null,
      hibaleiras: hibaleiras.value ?? '',
      megjegyzes: megjegyzes.value ?? ''
    }
    await request(`/munkalapok/${id.value}`, {
      method: 'PATCH',
      body: payload
    })
    setSnack('Munkalap módosítva', 'success')
    router.push({ name: 'AdminWorkorderDetail', params: { id: id.value } })
  } catch (e) {
    errorMsg.value = e?.message || 'Nem sikerült módosítani a munkalapot.'
  } finally {
    saving.value = false
  }
}

function goBack () {
  try {
    router.back()
  } catch {
    router.push('/admin/munkalapok')
  }
}

Vue.onMounted(() => {
  loadExisting()
  onSearchUgyfel('')
  onSearchGep('')
})
</script>

<style scoped>
.create-btn {
  min-width: 140px;
}
</style>

