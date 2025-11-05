<template>
  <v-container fluid class="pa-4 has-bottom-bar">
    <v-toolbar density="comfortable" color="white" elevation="0" class="mb-3 detail-toolbar">
      <v-btn variant="elevated" color="primary" prepend-icon="mdi-arrow-left" @click="goBack">Vissza</v-btn>
      <v-toolbar-title>Munkalap #{{ displayId }}</v-toolbar-title>
      <v-spacer />
      <div class="d-flex align-center ga-2" style="margin-left:12px">
        <v-chip v-if="isRegistered && hasOffer" size="small" :color="offerStatusColor(offerStatus)" variant="tonal">{{ displayOfferStatus(offerStatus) }}</v-chip>
      </div>
      <v-chip size="small" :color="statusColorX(statusModel)" class="mr-2" variant="flat">{{ displayStatusX(statusModel) }}</v-chip>
    </v-toolbar>

    <v-alert v-if="errorMsg" type="error" variant="tonal" class="mb-3 detail-toolbar">{{ errorMsg }}</v-alert>
    <v-snackbar v-model="snackbar" :timeout="3000" :color="snackbarColor" location="top right">
      {{ snackbarText }}
      <template #actions>
        <v-btn variant="text" @click="snackbar = false">OK</v-btn>
      </template>
    </v-snackbar>

    <v-row>
      <v-col cols="12" lg="4" class="left-col">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Alap adatok</v-card-title>
          <v-divider />
          <v-card-text>
            <div class="mb-2"><strong>Ügyfél:</strong> {{ getUgyfelNev(detail) }}</div>
            <div class="mb-2"><strong>Gép:</strong> {{ gepLabel(gepFromRow(detail)) }}</div>
            <div class="mb-2"><strong>Létrehozva:</strong> {{ fmtDate(detail.letrehozva || detail.created_at) }}</div>
            <div class="mb-2"><strong>Azonosító:</strong> {{ displayId }}</div>
          </v-card-text>
        </v-card>

        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Státusz</v-card-title>
          <v-divider />
          <v-card-text>
            <v-select
              v-model="statusModel"
              :items="statusItems"
              item-title="title"
              item-value="value"
              label="Státusz"
              variant="outlined"
              density="comfortable"
            />
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1">Napló</v-card-title>
          <v-divider />
          <v-card-text>
            <v-list density="compact">
              <v-list-item v-for="n in naplo" :key="n.id || n.ID" :title="n.uzenet || n.szoveg || n.megjegyzes" :subtitle="fmtDate(n.letrehozva || n.created_at)" />
            </v-list>
            <v-textarea v-model="note" rows="2" auto-grow label="Megjegyzés hozzáadása" variant="outlined" density="comfortable" />
            <div class="d-flex justify-end mt-2">
              <v-btn size="small" variant="tonal" color="primary" @click="addNote">Hozzáadás</v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="8">
        <v-card class="offer-card">
          <v-card-title class="d-flex align-center">
            <span class="text-subtitle-1">Árajánlat</span>
            <v-spacer />
            <v-autocomplete
              v-model="pickerSelected"
              :items="pickerItems"
              :loading="pickerLoading"
              :search="pickerSearch"
              @update:search="onPickerSearch"
              @update:modelValue="onPickerSelect"
              item-title="label"
              item-value="value"
              return-object
              @focus="onPickerSearch('')"
              label="Tétel hozzáadása"
              clear-on-select
              hide-details
              variant="outlined"
              density="comfortable"
              style="max-width: 420px"
              :disabled="!canEditOffer"
            />
          </v-card-title>

          <v-card-text>
            <v-text-field v-model="offerUzenet" label="Üzenet az árajánlatban (opcionális)" variant="outlined" density="comfortable" class="mb-3 detail-toolbar" :disabled="!canEditOffer" />

            <div class="offer-scroll">
              <v-table density="compact">
                <thead>
                  <tr>
                    <th style="width: 38%">Megnevezés</th>
                    <th class="text-right" style="width: 12%">Db</th>
                    <th class="text-right" style="width: 20%">Nettó</th>
                    <th class="text-right" style="width: 10%">ÁFA%</th>
                    <th class="text-right" style="width: 20%">Bruttó</th>
                    <th style="width: 12%"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(t,i) in tetelek" :key="'t'+i">
                    <td>
                      <v-text-field v-model="t.megnevezes" hide-details variant="outlined" density="compact" class="name-input" :disabled="!canEditOffer || nameLocked(t)" />
                      <div v-if="t.alkatresz_id" class="text-caption mt-1" :style="{color: shortageAtIndex(i) ? '#b00020' : '#666'}">
                        Készlet: {{ stockFor(t.alkatresz_id) }} | Igény: {{ demandMap[t.alkatresz_id] || 0 }}
                        <span v-if="shortageAtIndex(i)"> — Készlet kevés</span>
                      </div>
                    </td>
                    <td>
                      <v-text-field v-model.number="t.db" type="number" min="1" step="1" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.db,4,8)" :disabled="!canEditOffer" />
                    </td>
                    <td>
                      <template v-if="canEditOffer">
                        <v-text-field v-model.number="t.netto" type="number" step="0.01" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.netto,8,24)" />
                      </template>
                      <template v-else>
                        <div class="num-text">{{ fmtCurrency(t.netto) }}</div>
                      </template>
                    </td>
                    <td>
                      <v-text-field v-model.number="t.afa_kulcs" type="number" step="1" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.afa_kulcs,3,6)" :disabled="!canEditOffer" />
                    </td>
                    <td>
                      <template v-if="canEditOffer">
                        <v-text-field v-model.number="t.brutto" type="number" step="0.01" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.brutto,8,24)" />
                      </template>
                      <template v-else>
                        <div class="num-text">{{ fmtCurrency(t.brutto) }}</div>
                      </template>
                    </td>
                    <td class="text-right">
                      <v-btn size="small" variant="outlined" color="error" prepend-icon="mdi-delete" @click="removeTetel(i)" :disabled="!canEditOffer">Törlés</v-btn>
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </div>

            <v-divider class="my-3" />
            <div class="d-flex justify-end ga-4">
              <div class="text-right">
                <div class="text-caption">Összesen Nettó</div>
                <div class="text-subtitle-2">{{ fmtCurrency(totalNetto) }}</div>
              </div>
              <div class="text-right">
                <div class="text-caption">Összesen Bruttó</div>
                <div class="text-subtitle-2">{{ fmtCurrency(totalBrutto) }}</div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div class="detail-actions d-flex align-center justify-end ga-2">
      <v-btn class="action-btn" color="secondary" variant="elevated" prepend-icon="mdi-content-save" @click="saveWorkorder" :disabled="savingWorkorder" :loading="savingWorkorder">Munkalap mentése</v-btn>
      <v-btn class="action-btn" color="primary" variant="elevated" prepend-icon="mdi-content-save" @click="saveOffer" :disabled="savingOffer || !canEditOffer || hasShortage" :loading="savingOffer">Árajánlat mentése</v-btn>
      <v-btn class="action-btn" color="grey" variant="elevated" prepend-icon="mdi-printer" @click="printOffer">Nyomtatás</v-btn>
      <v-btn class="action-btn" color="success" variant="elevated" prepend-icon="mdi-send" @click="sendOffer" :disabled="sendingOffer || !canSendOffer" :loading="sendingOffer">Árajánlat küldése</v-btn>
      <v-btn v-if="isAdmin" class="action-btn" color="error" variant="elevated" prepend-icon="mdi-delete" @click="deleteWorkorder">Törlés</v-btn>
    </div>

    <!-- Print-only invoice layout -->
    <div class="print-invoice">
      <div class="pi-header">
        <div class="pi-title">Árajánlat</div>
        <div class="pi-meta">
          <div>Azonosító: {{ displayId }}</div>
          <div>Ügyfél: {{ getUgyfelNev(detail) }}</div>
          <div>Dátum: {{ fmtDate(detail.letrehozva || detail.created_at) }}</div>
          <div>Státusz: {{ displayStatus(detail.statusz || detail.status || detail.allapot) }}</div>
        </div>
      </div>
      <table class="pi-table">
        <thead>
          <tr>
            <th>Megnevezés</th>
            <th class="num">Db</th>
            <th class="num">Nettó (Ft)</th>
            <th class="num">ÁFA%</th>
            <th class="num">Bruttó (Ft)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(t,i) in tetelek" :key="'p'+i">
            <td>{{ t.megnevezes }}</td>
            <td class="num">{{ t.db }}</td>
            <td class="num">{{ Math.round(Number(t.netto)||0) }}</td>
            <td class="num">{{ t.afa_kulcs ?? 27 }}</td>
            <td class="num">{{ Math.round(Number(t.brutto)||0) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="right">Összesen:</th>
            <th class="num">{{ fmtCurrency(totalNetto) }}</th>
            <th></th>
            <th class="num">{{ fmtCurrency(totalBrutto) }}</th>
          </tr>
        </tfoot>
      </table>
      <div class="pi-note" v-if="offerUzenet && offerUzenet.trim()">Megjegyzés: {{ offerUzenet }}</div>
    </div>
  </v-container>
</template>

<script setup>
import * as Vue from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'

async function request(path, { method = 'GET', body } = {}) {
  const url = `/api${path}`
  const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' }
  try { const tk = localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('AUTH_TOKEN'); if (tk) headers['Authorization'] = `Bearer ${tk}` } catch {}
  const res = await fetch(url + (method === 'GET' && body ? `?${new URLSearchParams(body)}` : ''), { method, headers, body: method === 'GET' ? undefined : JSON.stringify(body ?? {}), credentials: 'include' })
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${await res.text().catch(() => '')}`)
  const ct = res.headers.get('content-type') || ''
  return ct.includes('application/json') ? res.json() : null
}

const route = useRoute()
const router = useRouter()
const id = Vue.computed(() => route.params.id)
const displayId = Vue.computed(()=> (detail.value && (detail.value.azonosito || detail.value.identifier)) || id.value)

const detail = Vue.ref({})
const naplo = Vue.ref([])
const note = Vue.ref('')
const tetelek = Vue.ref([])
const offer = Vue.ref(null)
const errorMsg = Vue.ref('')

const snackbar = Vue.ref(false)
const snackbarText = Vue.ref('')
const snackbarColor = Vue.ref('success')
function setSnack(text, color = 'success'){ snackbarText.value = text; snackbarColor.value = color; snackbar.value = true }

const offerUzenet = Vue.ref('')

// Status handling (normalized codes + labels)
const statusItems = [
  { title: 'Új', value: 'uj' },
  { title: 'Folyamatban', value: 'folyamatban' },
  { title: 'Árajánlat elküldve', value: 'ajanlat_elkuldve' },
  { title: 'Alkatrészre vár', value: 'alkatreszre_var' },
  { title: 'Javítás kész', value: 'javitas_kesz' },
  { title: 'Árajánlat elutasítva', value: 'ajanlat_elutasitva' },
  { title: 'Átadva/Lezárva', value: 'atadva_lezarva' },
  { title: 'Árajánlat elfogadva', value: 'ajanlat_elfogadva' },
]
const originalStatus = Vue.ref('')
function normalizeStatus(s){
  const t=(s||'').toString().toLowerCase()
  const n=t.normalize('NFD').replace(/[\u0300-\u036f]/g,'')
  if(n.includes('uj')) return 'uj'
  if(n.includes('folyamatban')) return 'folyamatban'
  if(n.includes('elkuldve')) return 'ajanlat_elkuldve'
  if(n.includes('var')) return 'alkatreszre_var'
  if(n.includes('kesz')) return 'javitas_kesz'
  if(n.includes('elutasit')) return 'ajanlat_elutasitva'
  if(n.includes('atadva') || n.includes('lezarva')) return 'atadva_lezarva'
  if(n.includes('elfogad')) return 'ajanlat_elfogadva'
  return t
}
const statusModel = Vue.computed({
  get(){ return normalizeStatus(detail.value.statusz || detail.value.status || detail.value.allapot) },
  set(v){ detail.value.statusz = v }
})
function statusColorX(s){
  switch(normalizeStatus(s)){
    case 'uj': return 'grey'
    case 'folyamatban': return 'blue'
    case 'ajanlat_elkuldve': return 'purple'
    case 'alkatreszre_var': return 'orange'
    case 'javitas_kesz': return 'green'
    case 'ajanlat_elfogadva': return 'indigo'
    case 'atadva_lezarva': return 'teal'
    case 'ajanlat_elutasitva': return 'red'
    default: return 'grey'
  }
}
function displayStatusX(s){
  const key=normalizeStatus(s)
  const map={
    'uj':'Új',
    'folyamatban':'Folyamatban',
    'ajanlat_elkuldve':'Árajánlat elküldve',
    'alkatreszre_var':'Alkatrészre vár',
    'javitas_kesz':'Javítás kész',
    'ajanlat_elutasitva':'Árajánlat elutasítva',
    'atadva_lezarva':'Átadva/Lezárva',
    'ajanlat_elfogadva':'Árajánlat elfogadva',
  }
  return map[key] || s || '-'
}

const statusOptions = [
  'Új',
  'Folyamatban',
  'Árajánlat elküldve',
  'Alkatrészre vár',
  'Javítás kész',
  'Árajánlat elutasítva',
  'Átadva/Lezárva',
  'Árajánlat elfogadva',
]
function fmtDate(v){ try { return v ? new Date(v).toLocaleString('hu-HU') : '' } catch { return v || '' } }
function fmtCurrency(v){ if (v==null||v==='') return ''; return new Intl.NumberFormat('hu-HU',{style:'currency',currency:'HUF',maximumFractionDigits:0}).format(Number(v)) }
function statusColor(s){
  const k=(s||'').toLowerCase()
  switch(k){
    case 'új': case 'uj': return 'grey'
    case 'folyamatban': return 'blue'
    case 'árajánlat elküldve': case 'ajanlat_elkuldve': return 'purple'
    case 'alkatrészre vár': case 'alkatreszre_var': return 'orange'
    case 'javítás kész': case 'javitas_kesz': return 'green'
    case 'árajánlat elfogadva': case 'ajanlat_elfogadva': return 'indigo'
    case 'átadva/lezárva': case 'atadva': case 'lezarva': case 'atadva_lezarva': return 'teal'
    case 'árajánlat elutasítva': case 'ajanlat_elutasitva': return 'red'
    default: return 'grey'
  }
}
function displayStatus(s){
  const key=(s||'').toLowerCase()
  const map={
    'új':'Új','uj':'Új',
    'folyamatban':'Folyamatban',
    'árajánlat elküldve':'Árajánlat elküldve','ajanlat_elkuldve':'Árajánlat elküldve',
    'alkatrészre vár':'Alkatrészre vár','alkatreszre_var':'Alkatrészre vár',
    'javítás kész':'Javítás kész','javitas_kesz':'Javítás kész',
    'árajánlat elutasítva':'Árajánlat elutasítva','ajanlat_elutasitva':'Árajánlat elutasítva',
    'átadva/lezárva':'Átadva/Lezárva','atadva':'Átadva/Lezárva','lezarva':'Átadva/Lezárva','atadva_lezarva':'Átadva/Lezárva',
    'árajánlat elfogadva':'Árajánlat elfogadva','ajanlat_elfogadva':'Árajánlat elfogadva',
  }
  return map[key] || s || '-'
}
function gepFromRow(row){ if(row?.gep) return row.gep; if(row?.gep_adatok) return row.gep_adatok; const gyarto=row?.gyarto||row?.gep_gyarto; const tipusnev=row?.tipusnev||row?.gep_tipus; const g_cikkszam=row?.g_cikkszam||row?.cikkszam||row?.gep_cikkszam; if(gyarto||tipusnev||g_cikkszam) return {gyarto,tipusnev,g_cikkszam}; return null }
function gepLabel(gep){ if(!gep) return '-'; try{ const gyarto = gep.gyarto || gep.gep_gyarto || ''; const tipus = gep.tipusnev || gep.gep_tipus || ''; const cikkszam = gep.g_cikkszam || gep.cikkszam || gep.gep_cikkszam || ''; const head = [gyarto, tipus].filter(Boolean).join(' '); return [head, cikkszam].filter(Boolean).join(' • ') }catch{ return '-' } }
function getUgyfelNev(row){ try{ return row?.ugyfel?.nev ?? row?.ugyfel_nev ?? row?.nev ?? row?.ugyfel_adatok?.nev ?? '-' }catch{ return '-' } }
function parseNum(v){ if (typeof v === 'number') return isFinite(v)?v:0; if (v==null) return 0; const s=String(v).replace(/\s+/g,'').replace(',', '.'); const n=parseFloat(s); return isFinite(n)?n:0 }

// Robust key/number helpers for mixed schemas
function normKey(k){ try{ return (k||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'') }catch{ return (k||'').toString().toLowerCase() } }
function toNum(v){ if (v==null) return 0; if (typeof v==='number') return isFinite(v)?v:0; const s=String(v).replace(/\s+/g,'').replace(/\./g,'').replace(',', '.'); const n=parseFloat(s); return isFinite(n)?n:0 }
function pickVal(obj, candidates){ try{ const map={}; for(const k of Object.keys(obj||{})){ map[normKey(k)] = obj[k]; } for(const name of candidates){ const v = map[normKey(name)]; if (v!=null && v!=='') return v } }catch{} return undefined }

const partStockById = Vue.ref({})
const partNameSet = new Set()
async function preloadPartsIndex(){
  try{
    const res = await request('/alkatreszek', { method: 'GET', body: { q: '', limit: '1000' } })
    const arr = Array.isArray(res) ? res : []
    for(const p of arr){
      const nm = p.alkatresznev ?? p.alaktresznev ?? p.nev ?? p.megnevezes
      if(nm) partNameSet.add(norm(nm))
      const pid = p.ID ?? p.id
      if(pid!=null) partStockById.value[pid] = p.keszlet ?? 0
    }
  }catch{}
}
function norm(s){ try{ return (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'') }catch{ return (s||'').toString().toLowerCase() } }
function isKnownPartName(name){ if(!name) return false; return partNameSet.has(norm(name)) }

async function loadAll(){
  try{
    await preloadPartsIndex()
    const d = await request(`/munkalapok/${id.value}`); detail.value = d || {}
    const s = normalizeStatus(detail.value.statusz || detail.value.status || detail.value.allapot)
    originalStatus.value = s
    statusModel.value = s
    const n = await request(`/munkalapok/${id.value}/naplo`); naplo.value = Array.isArray(n)?n:[]
    const a = await request(`/munkalapok/${id.value}/ajanlat`); offer.value = a || null; await applyAjanlat(a)
  }catch(e){ errorMsg.value = e?.message || 'Betöltési hiba.' }
}

async function applyAjanlat(a){
  const rows = a?.tetelek || []
  try{ offerUzenet.value = (a?.megjegyzes || '').toString() }catch{}
  tetelek.value = rows.map(r => {
    const tNorm = norm(r?.tipus)
    const name = r?.megnevezes || r?.alaktresznev || r?.alkatresznev || ''
    const nameNorm = norm(name)
    const isPart = tNorm.includes('alkatresz') || !!(r?.alkatresz_id || r?.a_cikkszam || r?.cikkszam || r?.alkatresznev || r?.alaktresznev) || isKnownPartName(name)
    const isMunkadij = tNorm.includes('munkadij') || nameNorm.includes('munkadij')
    const tipus = isPart ? 'alkatresz' : (isMunkadij ? 'munkadij' : 'egyedi')
    const db = parseNum(r?.mennyiseg ?? r?.db ?? 1) || 1
    const netto = parseNum(r?.netto_egyseg_ar ?? r?.netto ?? r?.nettoar ?? r?.netto_ar ?? r?.egyseg_ar ?? 0)
    let brutto = parseNum(r?.brutto_egyseg_ar ?? r?.brutto ?? r?.bruttoar ?? r?.brutto_ar ?? 0)
    if(!brutto){ const osszeg = parseNum(r?.osszeg ?? 0); if(osszeg && db){ brutto = osszeg / db } }
    const afa = parseNum(r?.afa_kulcs ?? r?.afa ?? 27)
    const pid = r?.alkatresz_id || null
    const keszlet = pid ? (partStockById.value?.[pid] ?? 0) : 0
    return { tipus, alkatresz_id: pid, megnevezes: name, db, netto, brutto, afa_kulcs: afa, keszlet }
  })
}

function syncOfferStocks(){ try{ tetelek.value = (tetelek.value||[]).map(t=> t?.alkatresz_id ? ({...t, keszlet: (partStockById.value?.[t.alkatresz_id] ?? t.keszlet ?? 0) }) : t) }catch{} }

const totalNetto = Vue.computed(()=>tetelek.value.reduce((s,t)=>s+((Number(t.netto)||0)*(Number(t.db)||1)),0))
const totalBrutto = Vue.computed(()=>tetelek.value.reduce((s,t)=>s+((Number(t.brutto)||0)*(Number(t.db)||1)),0))

// Stock demand vs available
const demandMap = Vue.computed(()=>{
  const m={}
  for(const t of (tetelek.value||[])){
    const pid=t?.alkatresz_id; if(!pid) continue
    const qty=Number(t?.db||0) || 0
    m[pid]=(m[pid]||0)+qty
  }
  return m
})
function stockFor(pid){ return pid ? (partStockById.value?.[pid] ?? 0) : 0 }
function shortageAtIndex(i){ const t=(tetelek.value||[])[i]; if(!t||!t.alkatresz_id) return false; const need=demandMap.value[t.alkatresz_id]||0; const have=stockFor(t.alkatresz_id); return need>have }
const hasShortage = Vue.computed(()=>{ const items=tetelek.value||[]; for(let i=0;i<items.length;i++){ if(shortageAtIndex(i)) return true } return false })

function removeTetel(i){ if(!canEditOffer.value) return; tetelek.value.splice(i,1) }

async function addNote(){ if(!id.value || !note.value?.trim()) return; try{ await request(`/munkalapok/${id.value}/naplo`,{method:'POST',body:{szoveg:note.value}}); const n=await request(`/munkalapok/${id.value}/naplo`); naplo.value=Array.isArray(n)?n:[]; note.value='' }catch(e){ errorMsg.value=e?.message||'Napló frissítési hiba.' } }

const savingWorkorder = Vue.ref(false)
const savingOffer = Vue.ref(false)
const sendingOffer = Vue.ref(false)
const workDirty = Vue.ref(false)
const offerDirty = Vue.ref(false)
const hydrating = Vue.ref(true)

// Admin check (from stored user)
const isAdmin = Vue.computed(()=>{
  try{
    const u = JSON.parse(localStorage.getItem('user')||'null')
    const role = (u?.jogosultsag || localStorage.getItem('jogosultsag') || localStorage.getItem('role') || '').toString().toLowerCase()
    return role==='admin'
  }catch{ return false }
})

// Delete current workorder (admin only)
async function deleteWorkorder(){
  if(!isAdmin.value || !id.value) return
  if(!confirm('Biztosan törli a munkalapot?')) return
  try{
    await request(`/munkalapok/${id.value}`, { method: 'DELETE' })
    setSnack('Munkalap törölve', 'success')
    router.push('/admin/munkalapok')
  }catch(e){ errorMsg.value = e?.message || 'Törlés sikertelen.' }
}

function buildOfferPayload(){
  return {
    megjegyzes: (offerUzenet.value && offerUzenet.value.trim()) ? offerUzenet.value.trim() : null,
    tetelek: (tetelek.value||[]).map(t=>({
      id: t.id ?? undefined,
      tipus: t.tipus,
      alkatresz_id: t.alkatresz_id ?? null,
      megnevezes: t.megnevezes,
      mennyiseg: (t.db || 1),
      netto_egyseg_ar: t.netto ?? 0,
      brutto_egyseg_ar: t.brutto ?? 0,
      afa_kulcs: t.afa_kulcs ?? 27,
    }))
  }
}

function validateOffer(){
  if(!Array.isArray(tetelek.value)){ return { ok: false, msg:'Nincs tétel az árajánlatban.' } }
  for(let i=0;i<tetelek.value.length;i++){
    const t=tetelek.value[i]
    if(!String(t?.megnevezes||'').trim()) return { ok: false, msg:`Hiányzó megnevezés a(z) ${i+1}. sorban.` }
    const db=Number(t?.db ?? 0); if(!isFinite(db)||db<1) return { ok: false, msg:`Érvénytelen darabszám a(z) ${i+1}. sorban.` }
    const netto=Number(t?.netto ?? 0), brutto=Number(t?.brutto ?? 0)
    if(!isFinite(netto) || !isFinite(brutto)) return { ok: false, msg:`Érvénytelen ár a(z) ${i+1}. sorban.` }
  }
  return { ok:true }
}

async function saveOffer(){
  if(!id.value) return;
  try{
    const valid = validateOffer(); if(!valid.ok){ setSnack(valid.msg || 'Érvénytelen árajánlat.', 'error'); return }
    savingOffer.value=true;
    await request(`/munkalapok/${id.value}/ajanlat`,{method:'POST',body:buildOfferPayload()});
    const a=await request(`/munkalapok/${id.value}/ajanlat`); offer.value=a||null; offerDirty.value=false; setSnack('Árajánlat mentve')
  }catch(e){ errorMsg.value=e?.message||'Mentési hiba (árajánlat).' }
  finally{ savingOffer.value=false }
}

async function sendOffer(){
  if(!id.value) return;
  try{
    if(!confirm('Biztosan elküldi az árajánlatot az ügyfélnek? A küldés után nem módosítható.')) return;
    sendingOffer.value=true;
    await saveOffer();
    await request(`/munkalapok/${id.value}/ajanlat`,{method:'POST',body:{ statusz: 'elkuldve' }});
    // Frissítsük a munkalap státuszt is, hogy az ügyfél listában is lássa
    try { await request(`/munkalapok/${id.value}`, { method:'PATCH', body:{ statusz: 'ajanlat_elkuldve' } }); detail.value.statusz = 'ajanlat_elkuldve' } catch {}
    const a=await request(`/munkalapok/${id.value}/ajanlat`); offer.value=a||null; setSnack('Árajánlat elküldve')
  }catch(e){ errorMsg.value=e?.message||'Küldési hiba (árajánlat).' }
  finally{ sendingOffer.value=false }
}

const hasOffer = Vue.computed(()=>!!(offer.value && ((offer.value.id)||(Array.isArray(offer.value.tetelek)&&offer.value.tetelek.length>0))))
const offerSent = Vue.computed(()=>{ const o=offer.value||{}; const s=(o.statusz||'').toLowerCase(); return !!(o.elkuldve===true || o.elkuldve_at || s==='elkuldve') })
const offerLocked = Vue.computed(()=>{ const o=offer.value||{}; const s=(o.statusz||'').toLowerCase(); return s==='elkuldve' || s==='elfogadva' || s==='elutasitva' || o.elkuldve===true || !!o.elkuldve_at })
const canEditOffer = Vue.computed(()=>!offerLocked.value)
const canSendOffer = Vue.computed(()=>!offerLocked.value && tetelek.value.length>0 && !hasShortage.value)

// Offer acceptance (only visible if registered)
const isRegistered = Vue.computed(()=>{ const d=detail.value||{}; try{ return !!(d.user_id || d.ugyfel?.id || d.ugyfel_id) }catch{ return false } })
const offerStatus = Vue.computed(()=> (offer.value?.statusz || '').toString().toLowerCase())
function offerStatusColor(s){ switch((s||'').toLowerCase()){ case 'elkuldve': return 'orange'; case 'elfogadva': return 'green'; case 'elutasitva': return 'red'; default: return 'grey' } }
function displayOfferStatus(s){ const k=(s||'').toLowerCase(); const map={ elkuldve:'Elfogadásra vár', elfogadva:'Elfogadva', elutasitva:'Elutasítva' }; return map[k]||'-' }
const processingOffer = Vue.ref(false)
async function setOfferStatus(code){ if(!id.value) return; try{ processingOffer.value=true; await request(`/munkalapok/${id.value}/ajanlat`,{method:'POST',body:{statusz:code}}); const a=await request(`/munkalapok/${id.value}/ajanlat`); offer.value=a||null; setSnack(displayOfferStatus(code)) }catch(e){ errorMsg.value=e?.message||'Árajánlat státusz frissítési hiba.' } finally{ processingOffer.value=false } }
function acceptOffer(){ setOfferStatus('elfogadva') }
function rejectOffer(){ setOfferStatus('elutasitva') }

function goBack(){ try{ if(window.history && window.history.length>1) router.back(); else router.push('/admin/munkalapok') } catch{} }
function printOffer(){ try{ window.print() }catch{} }

// Picker
const pickerSelected = Vue.ref(null)
const pickerSearch = Vue.ref('')
const pickerItems = Vue.ref([
  { label:'Munkadíj', value:'munkadij' },
  { label:'Egyedi tétel', value:'egyedi' },
])
const pickerLoading = Vue.ref(false)

async function onPickerSearch(val){
  pickerSearch.value = val
  try{
    pickerLoading.value = true
    const res = await request('/alkatreszek', { method: 'GET', body: { q: val || '', limit: '20' } })
    const partItems = (Array.isArray(res) ? res : []).map(p => {
      const id = p.ID ?? p.id
      const name = pickVal(p, ['alkatresznev','alaktresznev','nev','megnevezes']) || 'Alkatrész'
      const code = pickVal(p, ['a_cikkszam','cikkszam','code']) || ''
      const a = toNum(pickVal(p, ['afa_kulcs','áfa_kulcs','afa','áfa'])) || 27
      const nRaw = pickVal(p, ['netto_egyseg_ar','nettó_egyseg_ar','nettoar','netto','nettó','egyseg_ar','egységár','netto_ar','ar_netto'])
      const bRaw = pickVal(p, ['brutto_egyseg_ar','bruttó_egyseg_ar','bruttoar','brutto','bruttó','brutto_ar','ar_brutto'])
      let netto = toNum(nRaw)
      let brutto = toNum(bRaw)
      if (!netto && brutto) netto = brutto / (1 + a/100)
      if (!brutto && netto) brutto = netto * (1 + a/100)
      return { label: (code ? code + ' - ' : '') + name, value: { kind: 'alkatresz', id, name, netto, brutto, afa_kulcs: a } }
    })
    pickerItems.value = [
      { label:'Munkadíj', value:'munkadij' },
      { label:'Egyedi tétel', value:'egyedi' },
      ...partItems
    ]
  } finally { pickerLoading.value = false }
}
async function onPickerSelect(val){
  if (!val || !canEditOffer.value) return
  const v = val.value ?? val
  if (v === 'munkadij') {
    tetelek.value.push({ tipus: 'munkadij', megnevezes: 'Munkadíj', db: 1, netto: 0, brutto: 0, afa_kulcs: 27 })
  } else if (v === 'egyedi') {
    tetelek.value.push({ tipus: 'egyedi', megnevezes: '', db: 1, netto: 0, brutto: 0, afa_kulcs: 27 })
  } else if (typeof v === 'object' && v.kind === 'alkatresz') {
    let a = Number(v.afa_kulcs || 27)
    let n = toNum(v.netto)
    let b = toNum(v.brutto)
    if (!n && b) n = b/(1+a/100)
    if (!b && n) b = n*(1+a/100)
    // Fallback: töltünk részletes adatokból, ha mindkettő 0
    if (!n && !b && v.id){
      try{
        const det = await request(`/alkatreszek/${v.id}`, { method:'GET' })
        const p = det || {}
        const nRaw = pickVal(p, ['netto_egyseg_ar','nettó_egyseg_ar','netto','nettó','egyseg_ar','egységár','netto_ar','ar_netto'])
        const bRaw = pickVal(p, ['brutto_egyseg_ar','bruttó_egyseg_ar','brutto','bruttó','brutto_ar','ar_brutto'])
        const aRaw = pickVal(p, ['afa_kulcs','áfa_kulcs','afa','áfa'])
        a = toNum(aRaw) || a
        n = toNum(nRaw)
        b = toNum(bRaw)
        if (!n && b) n = b/(1+a/100)
        if (!b && n) b = n*(1+a/100)
      }catch{}
    }
    tetelek.value.push({ tipus: 'alkatresz', alkatresz_id: v.id, megnevezes: (v.name || 'Alkatrész'), db: 1, netto: n||0, brutto: b||0, afa_kulcs: a||27 })
  }
  pickerSelected.value = null
  try{ syncOfferStocks() }catch{}
}

function numStyle(val, minCh = 8, maxCh = 24){
  const len = String(val ?? '').length || 1
  const ch = Math.min(Math.max(len + 2, minCh), maxCh)
  return { '--w': ch + 'ch' }
}

function nameLocked(t){
  if (offerLocked.value) return true
  const typ = (t?.tipus || '').toString().toLowerCase()
  if (typ && typ !== 'egyedi') return true
  if (t?.alkatresz_id) return true
  if (isKnownPartName(t?.megnevezes)) return true
  return false
}

// Save basic workorder (status)
async function saveWorkorder(){
  if(!id.value) return
  try{
    savingWorkorder.value = true
    const code = normalizeStatus(detail.value.statusz)
    if (code === normalizeStatus(originalStatus.value)) { setSnack('Nincs változás'); return }
    await request(`/munkalapok/${id.value}`, { method:'PATCH', body:{ statusz: code, status: code, allapot: code } })
    workDirty.value = false
    originalStatus.value = code
    setSnack('Munkalap mentve')
  }catch(e){
    errorMsg.value = e?.message || 'Mentési hiba (munkalap).'
  }finally{
    savingWorkorder.value = false
  }
}

Vue.onMounted(loadAll)
Vue.watch(()=>detail.value.statusz, ()=>{ if(!hydrating.value) workDirty.value = normalizeStatus(detail.value.statusz) !== normalizeStatus(originalStatus.value) })
Vue.watch(tetelek, ()=>{ if(!hydrating.value) offerDirty.value = true }, { deep: true })
Vue.watch(()=>offerUzenet.value, ()=>{ if(!hydrating.value) offerDirty.value = true })
onBeforeRouteLeave((to,from,next)=>{ if(workDirty.value||offerDirty.value){ if(confirm('Vannak nem mentett változások. Biztosan kilépsz?')) next(); else next(false) } else next() })
if(typeof window !== 'undefined'){ window.addEventListener('beforeunload', (e)=>{ if(workDirty.value||offerDirty.value){ e.preventDefault(); e.returnValue=''; } }) }
</script>

<style scoped>
.detail-actions{ position: sticky; bottom: 0; left:0; right:0; z-index: 8; background:#fff; border-top:1px solid #eee; padding: 10px 12px; display:flex; gap:10px; align-items:center; justify-content:flex-end; flex-wrap:wrap; margin-top:16px; }
.has-bottom-bar{ min-height: 100vh; padding-bottom: 120px; overflow-y: auto; }
.num-input:deep(input){ text-align: right; font-weight: 600; color: #111; width: var(--w, 12ch); }
.name-input:deep(.v-field){ min-width: 260px; }
:deep(.v-btn:not(.v-btn--icon)) { text-align: center; }
:deep(.v-btn:not(.v-btn--icon) .v-btn__content) { justify-content: center; width: 100%; }
.offer-scroll{ overflow-x: auto; overflow-y: auto; max-height: 60vh; }
.offer-scroll::-webkit-scrollbar{ width: 8px; height: 8px; }
.offer-scroll::-webkit-scrollbar-thumb{ background: rgba(0,0,0,0.25); border-radius: 4px; }
.offer-scroll::-webkit-scrollbar-track{ background: transparent; }
.offer-scroll .v-table{ min-width: 780px; table-layout: fixed; }
.offer-scroll .v-table th:nth-child(1),
.offer-scroll .v-table td:nth-child(1){ width: 48%; }
.offer-scroll .v-table th:nth-child(2),
.offer-scroll .v-table td:nth-child(2){ width: 10%; }
.offer-scroll .v-table th:nth-child(3),
.offer-scroll .v-table td:nth-child(3){ width: 16%; }
.offer-scroll .v-table th:nth-child(4),
.offer-scroll .v-table td:nth-child(4){ width: 10%; }
.offer-scroll .v-table th:nth-child(5),
.offer-scroll .v-table td:nth-child(5){ width: 16%; }
.offer-scroll .v-table th:nth-child(6),
.offer-scroll .v-table td:nth-child(6){ width: 12%; }
.offer-scroll .v-table td:nth-child(1) .name-input{ width: 100%; }
.offer-scroll .v-table td:nth-child(1) .name-input:deep(.v-field){ width: 100%; }
.offer-scroll .v-table td:nth-child(1) .name-input:deep(.v-field__field){ padding-right: 12px; }
.offer-scroll .v-table td:nth-child(2){ padding-left: 12px; }
.num-text{ text-align:right; font-weight:600; color:#111; }
@media (max-width: 600px){
  .detail-toolbar{ flex-wrap: wrap; }
  .detail-toolbar .v-toolbar-title{ flex:1 0 100%; }
  .detail-actions{
    position: sticky;
    bottom: 0;
    box-shadow: 0 -6px 16px rgba(0,0,0,.08);
    padding: 8px 12px max(12px, env(safe-area-inset-bottom));
    flex-direction: column;
    align-items: stretch;
    justify-content: center;
    gap: 8px;
  }
  .has-bottom-bar{ padding-bottom: 160px; }
}
.detail-actions .v-btn.action-btn{ min-width: 128px; font-weight:600; }
@media (max-width: 600px){
  .detail-actions .v-btn.action-btn{
    width: 100%;
    min-width: unset;
    height: 40px;
    font-size: 0.88rem;
    padding-inline: 12px;
  }
}
@media (max-width: 380px){
  .detail-actions .v-btn.action-btn{
    flex-basis: 100%;
  }
  .offer-scroll{ max-height: 50vh; }
}

/* Print invoice */
.print-invoice{ display:none; font-family: Arial, "Segoe UI", "DejaVu Sans", sans-serif; color:#000; }
.print-invoice .pi-title{ font-size:20px; font-weight:700; margin-bottom:6px; }
.print-invoice .pi-meta{ font-size:12px; margin-bottom:10px; }
.print-invoice .pi-table{ width:100%; border-collapse:collapse; font-size:12px; }
.print-invoice .pi-table th, .print-invoice .pi-table td{ border:1px solid #333; padding:6px 8px; }
.print-invoice .pi-table th{ background:#f3f3f3; text-align:left; }
.print-invoice .num{ text-align:right; }
.print-invoice .right{ text-align:right; }
.print-invoice .pi-note{ margin-top:10px; font-size:12px; }

@media print{
  .detail-toolbar, .detail-actions, .v-alert, .v-snackbar{ display:none !important; }
  .left-col{ display:none !important; }
  .offer-card{ box-shadow:none !important; border:none !important; }
  .has-bottom-bar{ padding-bottom:0 !important; }
  .print-invoice{ display:block !important; }
}
</style>
