<script setup>
import * as Vue from 'vue'
import { useRouter } from 'vue-router'

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
const errorMsg = Vue.ref('')
const loading = Vue.ref(false)

const createState = Vue.reactive({
  ugyfel: null,
  ugyfelItems: [],
  ugyfelSearch: '',
  ugyfelLoading: false,
  gep: null,
  gepItems: [],
  gepSearch: '',
  gepLoading: false,
})

function gepItemTitle(it){ if(!it) return ''; return [it.gyarto, it.tipusnev, it.g_cikkszam].filter(Boolean).join(' — ') }

async function onSearchUgyfel(val){
  createState.ugyfelSearch = val
  createState.ugyfelLoading = true
  try{ const res = await request('/felhasznalok',{ method:'GET', body:{ q: val || '', limit:'20' }})
    createState.ugyfelItems = Array.isArray(res)?res:[]
  } catch(e){ console.warn('Ugyfel search failed', e) } finally { createState.ugyfelLoading=false }
}
async function onSearchGep(val){
  createState.gepSearch = val
  createState.gepLoading = true
  try{ const res = await request('/gepek',{ method:'GET', body:{ q: val || '', limit:'20' }})
    createState.gepItems = Array.isArray(res)?res:[]
  } catch(e){ console.warn('Gep search failed', e) } finally { createState.gepLoading=false }
}

async function createWorkorder(){
  if(!createState.ugyfel || !createState.gep){ errorMsg.value = 'Kérlek válassz ügyfelet és gépet.'; return }
  try{
    errorMsg.value = ''
    loading.value = true
    const payload = {
      ugyfel_id: createState.ugyfel.id ?? createState.ugyfel.ID ?? createState.ugyfel.ugyfel_id,
      gep_id: createState.gep.id ?? createState.gep.ID ?? createState.gep.gep_id,
      statusz: 'új',
    }
    const res = await request('/munkalapok',{ method:'POST', body: payload })
    const newId = res?.id ?? res?.ID ?? res?.munkalap_id
    if(newId){ router.push(`/admin/munkalapok/${newId}`) }
  } catch(e){ errorMsg.value = e?.message || 'Létrehozás sikertelen.' }
  finally{ loading.value=false }
}

Vue.onMounted(()=>{ onSearchUgyfel(''); onSearchGep('') })
</script>

<template>
  <v-container fluid class="pa-4">
    <v-toolbar density="comfortable" color="white" elevation="0" class="mb-3">
      <v-btn icon="mdi-arrow-left" variant="text" @click="$router.back()" />
      <v-toolbar-title>Új munkalap</v-toolbar-title>
      <v-spacer />
      <v-btn :loading="loading" color="primary" @click="createWorkorder" prepend-icon="mdi-plus">Létrehozás</v-btn>
    </v-toolbar>

    <v-alert v-if="errorMsg" type="error" variant="tonal" class="mb-3">{{ errorMsg }}</v-alert>

    <v-row>
      <v-col cols="12" md="6" lg="5">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Alap adatok</v-card-title>
          <v-divider />
          <v-card-text>
            <v-autocomplete
              v-model="createState.ugyfel"
              :items="createState.ugyfelItems"
              :loading="createState.ugyfelLoading"
              :search="createState.ugyfelSearch"
              @update:search="onSearchUgyfel"
              item-title="nev"
              item-value="id"
              label="Ügyfél kiválasztása"
              return-object
              clearable
              variant="outlined"
              density="comfortable"
              class="mb-3"
            />
            <v-autocomplete
              v-model="createState.gep"
              :items="createState.gepItems"
              :loading="createState.gepLoading"
              :search="createState.gepSearch"
              @update:search="onSearchGep"
              :item-title="gepItemTitle"
              item-value="id"
              label="Gép kiválasztása"
              return-object
              clearable
              variant="outlined"
              density="comfortable"
            />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

