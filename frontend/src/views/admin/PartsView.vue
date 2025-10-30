<template>
  <v-container fluid class="pa-4">
    <v-row class="align-center mb-4" no-gutters>
      <v-col cols="12" md="6">
        <h2 class="text-h5 font-weight-medium">Alkatrészek</h2>
      </v-col>
      <v-col cols="12" md="6" class="d-flex justify-end">
        <v-btn color="primary" @click="openCreate()" prepend-icon="mdi-plus">Új alkatrész</v-btn>
      </v-col>
    </v-row>

    <v-row class="mb-3" no-gutters>
      <v-col cols="12" md="6" class="pr-md-2">
        <v-text-field
          v-model="search"
          variant="outlined"
          density="comfortable"
          label="Keresés (cikkszám vagy megnevezés)"
          prepend-inner-icon="mdi-magnify"
          clearable
        />
      </v-col>
      <v-col cols="6" md="3" class="pr-md-2">
        <v-select
          v-model="limit"
          :items="[25,50,100,200]"
          variant="outlined"
          density="comfortable"
          label="Limit"
        />
      </v-col>
      <v-col cols="6" md="3" class="d-flex align-center">
        <v-btn variant="outlined" color="secondary" @click="fetchParts">Frissítés</v-btn>
      </v-col>
    </v-row>

    <v-card>
      <v-data-table
        :headers="headers"
        :items="items"
        :loading="loading"
        item-key="ID"
        class="elevation-0"
      >
        <template #top>
          <v-alert v-if="errorMsg" type="error" variant="tonal" class="ma-3">{{ errorMsg }}</v-alert>
        </template>
        <template #item.nettoar="{ item }">{{ fmtCurrency(item.nettoar) }}</template>
        <template #item.bruttoar="{ item }">{{ fmtCurrency(item.bruttoar) }}</template>
        <template #item.actions="{ item }">
          <v-btn icon="mdi-pencil" size="small" variant="text" @click="openEdit(item)" />
          <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="remove(item)" />
        </template>
        <template #no-data>
          <div class="pa-6 text-medium-emphasis">
            <div>Nincs megjeleníthető adat.</div>
            <div v-if="!loading && !errorMsg" class="mt-2">Próbáljon új alkatrészt felvenni a "Új alkatrész" gombbal.</div>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="dialog" max-width="640">
      <v-card>
        <v-card-title class="d-flex align-center">
          <span class="text-h6">{{ form.id ? 'Alkatrész szerkesztése' : 'Új alkatrész' }}</span>
        </v-card-title>
        <v-divider />
        <v-card-text>
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field v-model="form.a_cikkszam" label="Cikkszám" variant="outlined" density="comfortable" />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field v-model="form.alaktresznev" label="Megnevezés" variant="outlined" density="comfortable" />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model.number="form.nettoar" type="number" step="0.01" label="Nettó ár" variant="outlined" density="comfortable" />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model.number="form.bruttoar" type="number" step="0.01" label="Bruttó ár" variant="outlined" density="comfortable" />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model.number="form.afa_kulcs" type="number" step="0.1" label="ÁFA %" variant="outlined" density="comfortable" />
            </v-col>
          </v-row>
        </v-card-text>
        <v-divider />
        <v-card-actions class="justify-end">
          <v-btn variant="text" @click="dialog=false">Mégse</v-btn>
          <v-btn color="primary" @click="save">Mentés</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import * as Vue from 'vue'

const headers = [
  { title: 'Cikkszám', value: 'a_cikkszam', sortable: true },
  { title: 'Megnevezés', value: 'alaktresznev', sortable: true },
  { title: 'Nettó egységár', value: 'nettoar', sortable: true, align: 'end' },
  { title: 'Bruttó egységár', value: 'bruttoar', sortable: true, align: 'end' },
  { title: '', value: 'actions', sortable: false, align: 'end' },
]

const items = Vue.ref([])
const loading = Vue.ref(false)
const search = Vue.ref('')
const limit = Vue.ref(50)
let searchDebounce = null

const dialog = Vue.ref(false)
const form = Vue.ref({ id: null, a_cikkszam: '', alaktresznev: '', nettoar: null, bruttoar: null, afa_kulcs: 27 })
const errorMsg = Vue.ref('')

function fmtCurrency(v) {
  if (v === null || v === undefined || v === '') return ''
  return new Intl.NumberFormat('hu-HU', { style: 'currency', currency: 'HUF', maximumFractionDigits: 0 }).format(Number(v))
}

async function request(path, { method = 'GET', body } = {}) {
  const url = `/api${path}`
  const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' }
  try {
    const tk =
      localStorage.getItem('auth_token') ||
      localStorage.getItem('token') ||
      localStorage.getItem('AUTH_TOKEN')
    if (tk) headers['Authorization'] = `Bearer ${tk}`
  } catch (_) { /* ignore */ }
  const res = await fetch(url + (method === 'GET' && body ? `?${new URLSearchParams(body)}` : ''), {
    method,
    headers,
    body: method === 'GET' ? undefined : JSON.stringify(body ?? {}),
    credentials: 'include',
  })
  if (!res.ok) {
    const txt = await res.text().catch(() => '')
    throw new Error(`HTTP ${res.status}: ${txt}`)
  }
  const ct = res.headers.get('content-type') || ''
  if (ct.includes('application/json')) return res.json()
  return null
}

async function fetchParts() {
  loading.value = true
  errorMsg.value = ''
  try {
    const data = await request('/alkatreszek', { method: 'GET', body: { q: search.value, limit: String(limit.value) } })
    items.value = Array.isArray(data) ? data : []
    // Helpful debug for dev console
    console.debug('Alkatrészek betöltve:', items.value.length)
  } finally {
    loading.value = false
  }
}

// Wrap request to capture errors into errorMsg for the UI
async function safe(fn) {
  try {
    await fn()
  } catch (e) {
    console.error(e)
    errorMsg.value = e?.message || 'Ismeretlen hiba történt.'
  }
}

function openCreate() {
  form.value = { id: null, a_cikkszam: '', alaktresznev: '', nettoar: null, bruttoar: null, afa_kulcs: 27 }
  dialog.value = true
}

function openEdit(item) {
  form.value = {
    id: item.ID,
    a_cikkszam: item.a_cikkszam || '',
    alaktresznev: item.alaktresznev || '',
    nettoar: item.nettoar ?? null,
    bruttoar: item.bruttoar ?? null,
    afa_kulcs: 27,
  }
  dialog.value = true
}

async function save() {
  const payload = {
    a_cikkszam: form.value.a_cikkszam,
    alaktresznev: form.value.alaktresznev,
    nettoar: form.value.nettoar,
    bruttoar: form.value.bruttoar,
    afa_kulcs: form.value.afa_kulcs,
  }
  if (form.value.id) {
    await request(`/alkatreszek/${form.value.id}`, { method: 'PUT', body: payload })
  } else {
    await request('/alkatreszek', { method: 'POST', body: payload })
  }
  dialog.value = false
  await safe(fetchParts)
}

async function remove(item) {
  if (!confirm('Biztosan törli?')) return
  await request(`/alkatreszek/${item.ID}`, { method: 'DELETE' })
  await safe(fetchParts)
}

Vue.watch(search, () => {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(fetchParts, 250)
})

Vue.watch(limit, () => safe(fetchParts))

safe(fetchParts)
</script>

<style scoped>
.text-h5 { color: #000; }
</style>
