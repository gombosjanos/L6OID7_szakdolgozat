<template>
  <v-container fluid class="pa-4">
      <v-toolbar density="comfortable" color="white" elevation="0" class="mb-3">
        <v-btn icon="mdi-arrow-left" variant="text" @click="goBack" />
        <v-toolbar-title>Munkalap #{{ id }}</v-toolbar-title>
        <v-spacer />
        <v-chip size="small" :color="statusColor(detail.statusz)" class="mr-2" variant="flat">{{ displayStatus(detail.statusz) }}</v-chip>
        <v-btn color="primary" @click="saveAll" prepend-icon="mdi-content-save">Mentés</v-btn>
      </v-toolbar>

    <v-alert v-if="errorMsg" type="error" variant="tonal" class="mb-3">{{ errorMsg }}</v-alert>

    <v-row>
      <v-col cols="12" lg="4">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Alap adatok</v-card-title>
          <v-divider />
          <v-card-text>
            <div class="mb-2"><strong>Ügyfél:</strong> {{ getUgyfelNev(detail) }}</div>
            <div class="mb-2"><strong>Gép:</strong> {{ gepLabel(gepFromRow(detail)) }}</div>
            <div class="mb-2"><strong>Létrehozva:</strong> {{ fmtDate(detail.letrehozva || detail.created_at) }}</div>
            <div class="mb-2"><strong>Azonosító:</strong> {{ id }}</div>
          </v-card-text>
        </v-card>

        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Státusz</v-card-title>
          <v-divider />
          <v-card-text>
            <v-select v-model="detail.statusz" :items="statusOptions" label="Státusz" variant="outlined" density="comfortable" />
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1">Napló</v-card-title>
          <v-divider />
          <v-card-text>
            <v-list density="compact">
              <v-list-item v-for="n in naplo" :key="n.id || n.ID" :title="n.szoveg || n.megjegyzes" :subtitle="fmtDate(n.letrehozva || n.created_at)" />
            </v-list>
            <v-textarea v-model="note" rows="2" auto-grow label="Megjegyzés hozzáadása" variant="outlined" density="comfortable" />
            <div class="d-flex justify-end mt-2">
              <v-btn size="small" variant="tonal" color="primary" @click="addNote">Hozzáadás</v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="8">
        <v-card>
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
              label="Tétel hozzáadása"
              clear-on-select
              hide-details
              variant="outlined"
              density="comfortable"
              style="max-width: 420px"
            />
          </v-card-title>
          <v-divider />
          <v-card-text>
            <v-text-field v-model="offerUzenet" label="Üzenet az árajánlatban (opcionális)" variant="outlined" density="comfortable" class="mb-3" />
            <v-table class="elevation-0">
              <thead>
                <tr>
                  <th style="width: 28%">Megnevezés</th>
                  <th class="text-right" style="width: 12%">Db</th>
                  <th class="text-right" style="width: 20%">Nettó</th>
                  <th class="text-right" style="width: 14%">ÁFA%</th>
                  <th class="text-right" style="width: 20%">Bruttó</th>
                  <th style="width: 6%"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(t, i) in tetelek" :key="i">
                  <td><v-text-field v-model="t.megnevezes" hide-details variant="outlined" density="compact" class="name-input" /></td>
                  <td><v-text-field v-model.number="t.db" type="number" min="1" step="1" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.db,4,8)" /></td>
                  <td><v-text-field v-model.number="t.netto" type="number" step="0.01" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.netto,8,24)" /></td>
                  <td><v-text-field v-model.number="t.afa_kulcs" type="number" step="0.1" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.afa_kulcs,5,10)" /></td>
                  <td><v-text-field v-model.number="t.brutto" type="number" step="0.01" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.brutto,8,24)" /></td>
                  <td class="text-right"><v-btn :title="'Törlés'" size="small" variant="tonal" color="error" @click="removeTetel(i)"><v-icon icon="mdi-delete" /></v-btn></td>
                </tr>
              </tbody>
            </v-table>

            <v-divider class="my-3" />
            <div class="d-flex justify-end ga-4">
              <div class="text-right">
                <div class="text-caption">Összesen nettó</div>
                <div class="text-subtitle-2">{{ fmtCurrency(totalNetto) }}</div>
              </div>
              <div class="text-right">
                <div class="text-caption">Összesen bruttó</div>
                <div class="text-subtitle-2">{{ fmtCurrency(totalBrutto) }}</div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div class="detail-actions d-flex align-center justify-end ga-2 mt-4">
      <v-btn color="error" variant="tonal" prepend-icon="mdi-delete" :disabled="!isAdmin" @click="deleteWorkorder">Törlés</v-btn>
      <v-btn color="secondary" variant="tonal" prepend-icon="mdi-pencil" :disabled="!canEdit" @click="toggleEdit">Szerkesztés</v-btn>
      <v-btn color="primary" variant="elevated" prepend-icon="mdi-file-document-edit" :disabled="!canOffer" @click="saveAll">Árajánlat mentése</v-btn>
    </div>
  </v-container>
</template>

<script setup>
import * as Vue from 'vue'
import { useRoute, useRouter } from 'vue-router'

// Fetch helper with token
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

const detail = Vue.ref({})
const naplo = Vue.ref([])
const note = Vue.ref('')
const tetelek = Vue.ref([])
const offer = Vue.ref(null)
const errorMsg = Vue.ref('')
const editMode = Vue.ref(false)
const offerUzenet = Vue.ref('')

const statusOptions = ['Új', 'Folyamatban', 'Ajánlat kész', 'Vár alkatrészre', 'Javítás alatt', 'Kész', 'Átadva', 'Elutasítva']

function fmtDate(v){ try { return v ? new Date(v).toLocaleString('hu-HU') : '' } catch { return v || '' } }
function fmtCurrency(v){ if (v==null||v==='') return ''; return new Intl.NumberFormat('hu-HU',{style:'currency',currency:'HUF',maximumFractionDigits:0}).format(Number(v)) }

function statusColor(s){ switch((s||'').toLowerCase()){ case 'új': case 'uj': return 'grey'; case 'folyamatban': return 'blue'; case 'ajanlat_kesz': case 'ajánlat elkészült': case 'ajanlat elkészült': case 'ajánlat kész': return 'purple'; case 'vár alkatrészre': return 'orange'; case 'javítás alatt': return 'indigo'; case 'kész': return 'green'; case 'átadva': return 'teal'; case 'elutasítva': return 'red'; default: return 'grey' } }
function displayStatus(s){ const key=(s||'').toLowerCase(); const map={ 'uj':'Új','új':'Új','folyamatban':'Folyamatban','ajanlat_kesz':'Ajánlat kész','ajánlat elkészült':'Ajánlat kész','ajanlat elkészült':'Ajánlat kész','ajánlat kész':'Ajánlat kész','vár alkatrészre':'Vár alkatrészre','javítás alatt':'Javítás alatt','kész':'Kész','átadva':'Átadva','elutasítva':'Elutasítva' }; return map[key] || s || '-' }

function gepLabel(g){ if(!g) return '-'; const parts=[g.gyarto,g.tipusnev,g.g_cikkszam].filter(Boolean); return parts.join(' — ') }
function gepFromRow(row){ if(row?.gep) return row.gep; if(row?.gep_adatok) return row.gep_adatok; const gyarto=row?.gyarto||row?.gep_gyarto; const tipusnev=row?.tipusnev||row?.gep_tipus; const g_cikkszam=row?.g_cikkszam||row?.cikkszam||row?.gep_cikkszam; if(gyarto||tipusnev||g_cikkszam) return {gyarto,tipusnev,g_cikkszam}; return null }
function getUgyfelNev(row){ return row?.ugyfel?.nev ?? row?.ugyfel_nev ?? row?.nev ?? row?.ugyfel_adatok?.nev ?? '-' }

async function loadAll(){
  errorMsg.value=''
  try {
    const d = await request(`/munkalapok/${id.value}`); detail.value = d || {}
    const n = await request(`/munkalapok/${id.value}/naplo`); naplo.value = Array.isArray(n)?n:[]
    const a = await request(`/munkalapok/${id.value}/ajanlat`); offer.value = a || null; applyAjanlat(a)
  } catch(e){ errorMsg.value = e?.message || 'Betöltési hiba.' }
}

function applyAjanlat(a){ const rows=a?.tetelek||[]; tetelek.value = rows.map(r=>({ tipus:r.tipus||(r.alkatresz_id?'alkatresz':(r.megnevezes==='Munkadíj'?'munkadij':'egyedi')), alkatresz_id:r.alkatresz_id||null, megnevezes:r.megnevezes||r.alaktresznev||'', netto:Number(r.netto??r.nettoar??0), brutto:Number(r.brutto??r.bruttoar??0), afa_kulcs:Number(r.afa_kulcs??27) })) }

const totalNetto = Vue.computed(()=>tetelek.value.reduce((s,t)=>s+((Number(t.netto)||0)*(Number(t.db)||1)),0))
const totalBrutto = Vue.computed(()=>tetelek.value.reduce((s,t)=>s+((Number(t.brutto)||0)*(Number(t.db)||1)),0))
function removeTetel(i){ tetelek.value.splice(i,1) }

async function addNote(){ if(!id.value || !note.value?.trim()) return; try{ await request(`/munkalapok/${id.value}/naplo`,{method:'POST',body:{szoveg:note.value}}); const n=await request(`/munkalapok/${id.value}/naplo`); naplo.value=Array.isArray(n)?n:[]; note.value='' }catch(e){ errorMsg.value=e?.message||'Napló frissítési hiba.' } }

async function saveAll(){ if(!id.value) return; try{ await request(`/munkalapok/${id.value}`,{method:'PATCH',body:{statusz:detail.value.statusz}}); const base={ tipus:'ajanlat', uzenet: (offerUzenet.value && offerUzenet.value.trim()) ? offerUzenet.value : '-', tetelek:tetelek.value.map(t=>({tipus:t.tipus,alkatresz_id:t.alkatresz_id,megnevezes:t.megnevezes,netto:t.netto,brutto:t.brutto,afa_kulcs:t.afa_kulcs,mennyiseg:(t.db||1)})) }; const payload={ ...base, ajanlat: base }; await request(`/munkalapok/${id.value}/ajanlat`,{method:'POST',body:payload}); const a=await request(`/munkalapok/${id.value}/ajanlat`); offer.value=a||null }catch(e){ errorMsg.value=e?.message||'Mentési hiba.' } }

const isAdmin = Vue.computed(()=>{ const role=(localStorage.getItem('jogosultsag')||localStorage.getItem('role')||'').toLowerCase(); return role==='admin' })
const hasOffer = Vue.computed(()=>!!(offer.value && ((offer.value.id)||(Array.isArray(offer.value.tetelek)&&offer.value.tetelek.length>0))))
const offerSent = Vue.computed(()=>{ const o=offer.value||{}; return !!(o.elkuldve===true || o.statusz==='elkuldve' || o.elkuldve_at) })
const canEdit = Vue.computed(()=>!hasOffer.value)
const canOffer = Vue.computed(()=>!offerSent.value)
function toggleEdit(){ editMode.value=!editMode.value }
async function deleteWorkorder(){ if(!isAdmin.value||!id.value) return; if(!confirm('Biztosan törli a munkalapot?')) return; try{ await request(`/munkalapok/${id.value}`,{method:'DELETE'}); goBack() }catch(e){ errorMsg.value=e?.message||'Törlés sikertelen.' } }

function goBack(){ try{ router.back() } catch{ /* noop */ } }

// Picker: default + dynamic parts
const pickerSelected = Vue.ref(null)
const pickerSearch = Vue.ref('')
const pickerItems = Vue.ref([{ label:'Munkadíj', value:'munkadij' }, { label:'Egyedi tétel…', value:'egyedi' }])
const pickerLoading = Vue.ref(false)

async function onPickerSearch(val){ pickerSearch.value=val; try{ pickerLoading.value=true; const res=await request('/alkatreszek',{method:'GET',body:{q:val||'',limit:'20'}}); const partItems=(Array.isArray(res)?res:[]).map(p=>({ label:`${p.a_cikkszam||''} — ${(p.alaktresznev||p.alkatresznev||'')}`.trim(), value:{kind:'alkatresz',id:p.ID,name:(p.alaktresznev||p.alkatresznev),netto:p.nettoar,brutto:p.bruttoar,afa_kulcs:27} })); pickerItems.value=[{label:'Munkadíj',value:'munkadij'},{label:'Egyedi tétel…',value:'egyedi'},...partItems] } finally{ pickerLoading.value=false } }
function onPickerSelect(val){ if(!val) return; const v = val.value ?? val; if(v==='munkadij'){ tetelek.value.push({tipus:'munkadij',megnevezes:'Munkadíj',netto:0,brutto:0,afa_kulcs:27}) } else if(v==='egyedi'){ tetelek.value.push({tipus:'egyedi',megnevezes:'',netto:0,brutto:0,afa_kulcs:27}) } else if(typeof v==='object' && v.kind==='alkatresz'){ tetelek.value.push({tipus:'alkatresz',alkatresz_id:v.id,megnevezes:v.name||'Alkatrész',netto:v.netto||0,brutto:v.brutto||0,afa_kulcs:v.afa_kulcs||27}) } pickerSelected.value=null }

Vue.watch(pickerSelected,(val)=>{ if(!val) return; const v = val.value ?? val; if(v==='munkadij'){ const brutto=2500; const netto=Math.round((brutto/1.27)*100)/100; tetelek.value.push({tipus:'munkadij',megnevezes:'Munkadíj',db:1,netto,brutto,afa_kulcs:27}) } else if(v==='egyedi'){ tetelek.value.push({tipus:'egyedi',megnevezes:'',db:1,netto:0,brutto:0,afa_kulcs:27}) } else if(typeof v==='object' && v.kind==='alkatresz'){ tetelek.value.push({tipus:'alkatresz',alkatresz_id:v.id,megnevezes:v.name||'Alkatrész',db:1,netto:v.netto||0,brutto:v.brutto||0,afa_kulcs:v.afa_kulcs||27}) } pickerSelected.value=null })

function numStyle(val, minCh = 8, maxCh = 24){
  const len = String(val ?? '').length || 1
  const ch = Math.min(Math.max(len + 2, minCh), maxCh)
  return { '--w': ch + 'ch' }
}

Vue.onMounted(loadAll)
</script>

<style scoped>
.detail-actions{ position: sticky; bottom: 0; background: #fff; border-top: 1px solid #eee; padding: 8px 16px; }
.num-input:deep(input){ text-align: right; font-weight: 600; color: #111; width: var(--w, 12ch); }
.name-input:deep(.v-field){ min-width: 260px; }
</style>
