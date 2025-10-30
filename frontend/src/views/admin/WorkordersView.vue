<template>
  <v-container fluid class="pa-4">
    <v-row class="align-center mb-4" no-gutters>
      <v-col cols="12" md="6">
        <h2 class="text-h5 font-weight-medium">Munkalapok</h2>
      </v-col>
      <v-col cols="12" md="6" class="d-flex justify-end">
        <v-text-field
          v-model="search"
          variant="outlined"
          density="comfortable"
          label="Keresés (ügyfél, gép, azonosító)"
          prepend-inner-icon="mdi-magnify"
          clearable
          class="mr-2"
          style="max-width: 420px"
        />
        <v-select
          v-model="limit"
          :items="[25,50,100]"
          variant="outlined"
          density="comfortable"
          label="Limit"
          style="max-width: 120px"
        />
      </v-col>
    </v-row>

    <v-alert v-if="listError" type="error" variant="tonal" class="mb-3">{{ listError }}</v-alert>

    <v-card>
      <div class="table-scroll">
        <v-data-table
          :items="workorders"
          :headers="headers"
          :loading="loadingList"
          item-key="id"
          density="comfortable"
          class="elevation-0"
          @click:row="onRowClick"
        >
          <template #item.id="{ item }">{{ getId(item) }}</template>
          <template #item.ugyfel="{ item }">{{ getUgyfelNev(item) }}</template>
          <template #item.gep="{ item }">{{ gepLabel(gepFromRow(item)) }}</template>
          <template #item.letrehozva="{ item }">{{ fmtDate(item.letrehozva || item.created_at) }}</template>
          <template #item.statusz="{ item }">
            <v-chip size="small" :color="statusColor(item.statusz)" variant="flat">{{ displayStatus(item.statusz) }}</v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-btn size="small" variant="text" icon="mdi-open-in-new" @click.stop="openDetail(item)" />
          </template>
          <template #no-data>
            <div class="pa-6 text-medium-emphasis">Nincs megjeleníthető munkalap.</div>
          </template>
        </v-data-table>
      </div>
    </v-card>

    <v-navigation-drawer
      v-model="detailOpen"
      location="right"
      :width="drawerWidth"
      :scrim="false"
      app
      touchless
      temporary
      class="detail-drawer"
    >
      <v-toolbar density="comfortable" color="white" elevation="0">
        <v-btn variant="text" class="icon-btn" @click="closeDetail" title="Bezárás"><span class="btn-ico">✕</span></v-btn>
        <v-toolbar-title>Munkalap #{{ currentId || '-' }}</v-toolbar-title>
        <v-spacer />
        <v-chip size="small" :color="statusColor(detail.statusz || current?.statusz)" class="mr-2" variant="flat">{{ displayStatus(detail.statusz || current?.statusz) }}</v-chip>
        <v-btn color="primary" @click="saveAll" prepend-icon="mdi-content-save">Mentés</v-btn>
      </v-toolbar>

      <v-divider />

      <v-container fluid>
        <v-alert v-if="detailError" type="error" variant="tonal" class="my-3">{{ detailError }}</v-alert>

        <v-row>
          <v-col cols="12" lg="5">
            <v-card class="mb-4">
              <v-card-title class="text-subtitle-1">Alap adatok</v-card-title>
              <v-divider />
              <v-card-text>
                <div class="mb-2"><strong>Ügyfél:</strong> {{ getUgyfelNev(detail) }}</div>
                <div class="mb-2"><strong>Gép:</strong> {{ gepLabel(gepFromRow(detail)) }}</div>
                <div class="mb-2"><strong>Létrehozva:</strong> {{ fmtDate(detail.letrehozva || detail.created_at) }}</div>
                <div class="mb-2"><strong>Azonosító:</strong> {{ currentId }}</div>
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
                <div class="d-flex justify-end mt-2"><v-btn size="small" variant="tonal" color="primary" @click="addNote">Hozzáadás</v-btn></div>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" lg="7">
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
                  v-model:menu="pickerMenu"
                  @focus="preloadParts(true)"
                  item-title="label"
                  item-value="value"
                  return-object
                  label="Tétel hozzáadása"
                  clear-on-select
                  hide-details
                  variant="outlined"
                  density="comfortable"
                  style="max-width: 640px"
                />
              </v-card-title>
              <v-divider />
              <v-card-text>
                <v-text-field v-model="offerUzenet" label="Üzenet az árajánlatban (opcionális)" variant="outlined" density="comfortable" class="mb-3" />

                <!-- Desktop/tablet fixed grid to avoid overlap -->
                <div v-if="!smAndDown" class="offer-grid">
                  <div class="offer-head">Megnevezés</div>
                  <div class="offer-head text-right">Db</div>
                  <div class="offer-head text-right">Nettó</div>
                  <div class="offer-head text-right">ÁFA%</div>
                  <div class="offer-head text-right">Bruttó</div>
                  <div class="offer-head text-right"></div>

                  <template v-for="(t,i) in tetelek" :key="i">
                    <div class="cell name-cell">
                      <v-text-field v-if="t.tipus==='egyedi'" v-model="t.megnevezes" hide-details variant="outlined" density="compact" class="name-input" />
                      <div v-else class="name-wrap" :title="t.megnevezes">{{ t.megnevezes }}</div>
                    </div>
                    <div class="cell">
                      <v-text-field v-model.number="t.db" type="number" min="1" step="1" hide-details variant="outlined" density="compact" class="num-input w-qty" />
                    </div>
                    <div class="cell">
                      <v-text-field v-model.number="t.netto" type="number" step="0.01" suffix=" Ft" hide-details variant="outlined" density="compact" class="num-input w-money" />
                    </div>
                    <div class="cell d-flex justify-end align-center">
                      <v-chip size="small" color="grey-darken-1" variant="tonal">27%</v-chip>
                    </div>
                    <div class="cell">
                      <v-text-field v-model.number="t.brutto" type="number" step="0.01" suffix=" Ft" hide-details variant="outlined" density="compact" class="num-input w-money" />
                    </div>
                    <div class="cell text-right">
                      <v-btn title="Tétel törlése" size="small" variant="elevated" color="error" class="del-btn" @click="removeTetel(i)">Törlés</v-btn>
                    </div>
                  </template>
                </div>

                <!-- Mobile stacked editor -->
                <div v-else class="mobile-items">
                  <v-card v-for="(t,i) in tetelek" :key="i" class="mb-3 pa-3" variant="outlined">
                    <v-row class="ga-2">
                      <v-col cols="12"><v-text-field v-model="t.megnevezes" :readonly="t.tipus!=='egyedi'" label="Megnevezés" variant="outlined" density="comfortable" /></v-col>
                      <v-col cols="4"><v-text-field v-model.number="t.db" type="number" min="1" step="1" label="Db" variant="outlined" density="comfortable" /></v-col>
                      <v-col cols="8" class="d-flex justify-end"><v-btn title="Tétel törlése" size="default" color="error" class="icon-btn" @click="removeTetel(i)"><span class="btn-ico">🗑️</span></v-btn></v-col>
                      <v-col cols="6"><v-text-field v-model.number="t.netto" type="number" step="0.01" label="Nettó" suffix=" Ft" variant="outlined" density="comfortable" /></v-col>
                      <v-col cols="6"><v-text-field :model-value="27" readonly type="number" label="ÁFA%" suffix=" %" variant="outlined" density="comfortable" /></v-col>
                      <v-col cols="12"><v-text-field v-model.number="t.brutto" type="number" step="0.01" label="Bruttó" suffix=" Ft" variant="outlined" density="comfortable" /></v-col>
                    </v-row>
                  </v-card>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <div class="detail-actions d-flex align-center justify-end ga-2">
        <v-btn color="error" variant="tonal" prepend-icon="mdi-delete" :disabled="!isAdmin" @click="deleteWorkorder">Törlés</v-btn>
        <v-btn color="secondary" variant="tonal" prepend-icon="mdi-pencil" :disabled="!canEdit" @click="toggleEdit">Szerkesztés</v-btn>
        <v-btn color="primary" variant="elevated" prepend-icon="mdi-file-document-edit" :disabled="!canOffer" @click="saveAll">Árajánlat</v-btn>
      </div>
    </v-navigation-drawer>
  </v-container>
</template>

<script setup>
import * as Vue from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

async function request(path, { method = 'GET', body } = {}) {
  const url = `/api${path}`
  const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' }
  try { const tk = localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('AUTH_TOKEN'); if (tk) headers['Authorization'] = `Bearer ${tk}` } catch {}
  const res = await fetch(url + (method === 'GET' && body ? `?${new URLSearchParams(body)}` : ''), { method, headers, body: method === 'GET' ? undefined : JSON.stringify(body ?? {}), credentials: 'include' })
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${await res.text().catch(() => '')}`)
  const ct = res.headers.get('content-type') || ''
  return ct.includes('application/json') ? res.json() : null
}

const headers = [
  { title: 'Azonosító', value: 'id', sortable: true, width: 110 },
  { title: 'Ügyfél', value: 'ugyfel', sortable: false },
  { title: 'Gép', value: 'gep', sortable: false },
  { title: 'Létrehozva', value: 'letrehozva', sortable: true, width: 180 },
  { title: 'Státusz', value: 'statusz', sortable: true, width: 160 },
  { title: '', value: 'actions', align: 'end', sortable: false, width: 80 },
]

const workorders = Vue.ref([])
const loadingList = Vue.ref(false)
const listError = Vue.ref('')
const search = Vue.ref('')
const limit = Vue.ref(50)
let debounceT = null

const detailOpen = Vue.ref(false)
const current = Vue.ref(null)
const detail = Vue.ref({})
const currentId = Vue.computed(() => getId(current.value))
const detailError = Vue.ref('')
const naplo = Vue.ref([])
const note = Vue.ref('')
const tetelek = Vue.ref([])
const offer = Vue.ref(null)
const editMode = Vue.ref(false)
const offerUzenet = Vue.ref('')

const pickerSelected = Vue.ref(null)
const pickerSearch = Vue.ref('')
const pickerItems = Vue.ref([{ label:'Munkadíj', value:'munkadij' }, { label:'Egyedi tétel…', value:'egyedi' }])
const pickerLoading = Vue.ref(false)
const pickerMenu = Vue.ref(false)

const { smAndDown } = useDisplay()

const statusOptions = ['Új', 'Folyamatban', 'Ajánlat kész', 'Vár alkatrészre', 'Javítás alatt', 'Kész', 'Átadva', 'Elutasítva']

function statusColor(s){ switch((s||'').toLowerCase()){ case 'új': case 'uj': return 'grey'; case 'folyamatban': return 'blue'; case 'ajanlat_kesz': case 'ajánlat elkészült': case 'ajanlat elkészült': case 'ajánlat kész': return 'purple'; case 'vár alkatrészre': return 'orange'; case 'javítás alatt': return 'indigo'; case 'kész': return 'green'; case 'átadva': return 'teal'; case 'elutasítva': return 'red'; default: return 'grey' } }
function displayStatus(s){ const key=(s||'').toLowerCase(); const map={ 'uj':'Új','új':'Új','folyamatban':'Folyamatban','ajanlat_kesz':'Ajánlat kész','ajánlat elkészült':'Ajánlat kész','ajanlat elkészült':'Ajánlat kész','ajánlat kész':'Ajánlat kész','vár alkatrészre':'Vár alkatrészre','javítás alatt':'Javítás alatt','kész':'Kész','átadva':'Átadva','elutasítva':'Elutasítva' }; return map[key] || s || '-' }

function fmtDate(v){ try { return v ? new Date(v).toLocaleString('hu-HU') : '' } catch { return v || '' } }
function fmtCurrency(v){ if (v==null||v==='') return ''; return new Intl.NumberFormat('hu-HU',{style:'currency',currency:'HUF',maximumFractionDigits:0}).format(Number(v)) }

function gepLabel(g){ if(!g) return '-'; const parts=[g.gyarto,g.tipusnev,g.g_cikkszam].filter(Boolean); return parts.join(' — ') }
function gepFromRow(row){ if(row?.gep) return row.gep; if(row?.gep_adatok) return row.gep_adatok; const gyarto=row?.gyarto||row?.gep_gyarto; const tipusnev=row?.tipusnev||row?.gep_tipus; const g_cikkszam=row?.g_cikkszam||row?.cikkszam||row?.gep_cikkszam; if(gyarto||tipusnev||g_cikkszam) return {gyarto,tipusnev,g_cikkszam}; return null }
function getId(row){ return row?.id ?? row?.ID ?? row?.azonosito ?? row?.munkalap_id ?? null }
function getUgyfelNev(row){ return row?.ugyfel?.nev ?? row?.ugyfel_nev ?? row?.nev ?? row?.ugyfel_adatok?.nev ?? '-' }

async function fetchList(){
  loadingList.value=true; listError.value=''
  try {
    const data = await request('/munkalapok', { method: 'GET', body: { q: search.value, limit: String(limit.value) } })
    workorders.value = Array.isArray(data) ? data : []
    await enrichRows()
  } catch(e){ listError.value=e?.message||'Lista betöltési hiba.' }
  finally{ loadingList.value=false }
}

function onRowClick(_, row){ const r=row?.item||row; const id=getId(r); if(!id) return; openDetail(r) }
// Prefer route-based detail if router is available
const router = (()=>{ try { return useRouter() } catch { return null } })()
function goToDetail(id){
  if (!id) return false
  if (router) {
    try { router.push({ path: `/admin/workorders/${id}` }); return true } catch {}
  }
  try { window.location.href = `/admin/workorders/${id}`; return true } catch {}
return false
  }
  function onRowClickRouter(_, row){
  const r = row?.item || row
  const id = getId(r)
  if (!id) return
  if (!goToDetail(id)) openDetail(r)
}
function openDetail(item){ current.value=item; detailOpen.value=true; loadDetail(getId(item)) }
function closeDetail(){ detailOpen.value=false; current.value=null; detail.value={}; naplo.value=[]; tetelek.value=[]; offer.value=null; offerUzenet.value=''; editMode.value=false; detailError.value=''; note.value='' }

async function loadDetail(id){
  detailError.value=''
  try{
    const d = await request(`/munkalapok/${id}`); detail.value = d || {}
    try{
      if(!detail.value.ugyfel && (detail.value.ugyfel_id || detail.value.felhasznalo_id)){
        const uid = detail.value.ugyfel_id || detail.value.felhasznalo_id; const u = await request(`/felhasznalok/${uid}`); if(u) detail.value.ugyfel=u
      }
      if(!detail.value.gep && detail.value.gep_id){ const g = await request(`/gepek/${detail.value.gep_id}`); if(g) detail.value.gep=g }
    }catch{}
    const n = await request(`/munkalapok/${id}/naplo`); naplo.value = Array.isArray(n)?n:[]
    const a = await request(`/munkalapok/${id}/ajanlat`); offer.value = a || null; applyAjanlat(a)
    await preloadParts()
  }catch(e){ detailError.value=e?.message||'Munkalap betöltési hiba.' }
}

async function enrichRows(){
  const toFetch = workorders.value.filter(r=>!getUgyfelNev(r) || !gepFromRow(r))
  await Promise.all(toFetch.map(async (r)=>{ const rid=getId(r); if(!rid) return; try{ const d=await request(`/munkalapok/${rid}`); if(d?.ugyfel) r.ugyfel=d.ugyfel; if(d?.gep) r.gep=d.gep; else if(d){ r.gyarto=d.gyarto||r.gyarto; r.tipusnev=d.tipusnev||r.tipusnev; r.g_cikkszam=d.g_cikkszam||r.g_cikkszam } }catch{} }))
}

function applyAjanlat(a){ const rows=a?.tetelek||[]; tetelek.value = rows.map(r=>({ tipus:r.tipus||(r.alkatresz_id?'alkatresz':(r.megnevezes==='Munkadíj'?'munkadij':'egyedi')), alkatresz_id:r.alkatresz_id||null, megnevezes:r.megnevezes||r.alaktresznev||'', db:Number(r.mennyiseg||1), netto:Number(r.netto??r.nettoar??0), brutto:Number(r.brutto??r.bruttoar??0), afa_kulcs:27 })) }
const totalNetto = Vue.computed(()=>tetelek.value.reduce((s,t)=>s+((Number(t.netto)||0)*(Number(t.db)||1)),0))
const totalBrutto = Vue.computed(()=>tetelek.value.reduce((s,t)=>s+((Number(t.brutto)||0)*(Number(t.db)||1)),0))
function removeTetel(i){ tetelek.value.splice(i,1) }

async function addNote(){ if(!currentId.value || !note.value?.trim()) return; try{ await request(`/munkalapok/${currentId.value}/naplo`,{method:'POST',body:{szoveg:note.value}}); const n=await request(`/munkalapok/${currentId.value}/naplo`); naplo.value=Array.isArray(n)?n:[]; note.value='' }catch(e){ detailError.value=e?.message||'Napló frissítési hiba.' } }

async function saveAll(){ if(!currentId.value) return; try{ await request(`/munkalapok/${currentId.value}`,{method:'PATCH',body:{statusz:detail.value.statusz}}); const base={ tipus:'ajanlat', uzenet: (offerUzenet.value && offerUzenet.value.trim()) ? offerUzenet.value : '-', tetelek: tetelek.value.map(t=>({tipus:t.tipus,alkatresz_id:t.alkatresz_id,megnevezes:t.megnevezes,netto:t.netto,brutto:t.brutto,afa_kulcs:27,mennyiseg:(t.db||1)})) }; const payload={ ...base, ajanlat: base }; await request(`/munkalapok/${currentId.value}/ajanlat`,{method:'POST',body:payload}); const a=await request(`/munkalapok/${currentId.value}/ajanlat`); offer.value=a||null; await fetchList() }catch(e){ detailError.value=e?.message||'Mentési hiba.' } }

async function onPickerSearch(val){
  pickerSearch.value=val
  try{
    pickerLoading.value=true
    const res=await request('/alkatreszek',{method:'GET',body:{q:val||'',limit:'20'}})
    const partItems=(Array.isArray(res)?res:[]).map(p=>({ label:`${p.a_cikkszam||''} — ${(p.alaktresznev||p.alkatresznev||'')}`.trim(), value:{kind:'alkatresz',id:p.ID,name:(p.alaktresznev||p.alkatresznev),netto:p.nettoar,brutto:p.bruttoar} }))
    pickerItems.value=[{label:'Munkadíj',value:'munkadij'},{label:'Egyedi tétel…',value:'egyedi'},...partItems]
  } catch(e){
    // Swallow transient fetch errors to avoid red banner
    console.warn('Parts search failed:', e)
  } finally{
    pickerLoading.value=false
  }
}
async function preloadParts(openMenu=false){ if(pickerItems.value.length<=2){ await onPickerSearch('') } if(openMenu) pickerMenu.value=true }
function onPickerSelect(val){ if(!val) return; const v=val.value??val; if(v==='munkadij'){ tetelek.value.push({tipus:'munkadij',megnevezes:'Munkadíj (óra)',db:1,netto:Math.round((2500/1.27)*100)/100,brutto:2500,afa_kulcs:27}) } else if(v==='egyedi'){ tetelek.value.push({tipus:'egyedi',megnevezes:'',db:1,netto:0,brutto:0,afa_kulcs:27}) } else if(typeof v==='object' && v.kind==='alkatresz'){ tetelek.value.push({tipus:'alkatresz',alkatresz_id:v.id,megnevezes:v.name||'Alkatrész',db:1,netto:v.netto||0,brutto:v.brutto||0,afa_kulcs:27}) } pickerSelected.value=null }

const isAdmin = Vue.computed(()=>{ const role=(localStorage.getItem('jogosultsag')||localStorage.getItem('role')||'').toLowerCase(); return role==='admin' })
const hasOffer = Vue.computed(()=>!!(offer.value && ((offer.value.id)||(Array.isArray(offer.value.tetelek)&&offer.value.tetelek.length>0))))
const offerSent = Vue.computed(()=>{ const o=offer.value||{}; return !!(o.elkuldve===true || o.statusz==='elkuldve' || o.elkuldve_at) })
const canEdit = Vue.computed(()=>!hasOffer.value)
const canOffer = Vue.computed(()=>!offerSent.value)
function toggleEdit(){ editMode.value=!editMode.value }
async function deleteWorkorder(){ if(!isAdmin.value||!currentId.value) return; if(!confirm('Biztosan törli a munkalapot?')) return; try{ await request(`/munkalapok/${currentId.value}`,{method:'DELETE'}); closeDetail(); await fetchList() }catch(e){ detailError.value=e?.message||'Törlés sikertelen.' } }

Vue.watch(search, ()=>{ if(debounceT) clearTimeout(debounceT); debounceT=setTimeout(fetchList,250) })
Vue.watch(limit, fetchList)

fetchList()
</script>

<style scoped>
.detail-actions{ position: sticky; bottom: 0; background: #fff; border-top: 1px solid #eee; padding: 8px 16px; }
.detail-drawer{ }
.table-scroll{ overflow-x: auto; }
.icon-btn{ min-width: 36px; padding: 0 8px; }
.btn-ico{ font-size: 16px; line-height: 1; }

/* Offer grid (desktop) */
.offer-grid{ display: grid; grid-template-columns: 1fr 80px 170px 80px 170px 100px; align-items: center; gap: 10px 12px; }
.offer-head{ font-size: 12px; color: rgba(0,0,0,0.6); padding: 4px 2px; }
.offer-grid .cell{ min-height: 40px; }
.name-cell{ min-width: 240px; }
.name-wrap{ white-space: normal; word-break: break-word; line-height: 1.35; max-height: 3.9em; overflow: hidden; padding: 8px 10px; border: 1px solid rgba(0,0,0,0.12); border-radius: 8px; background: #fff; }
.w-qty:deep(.v-field){ width: 80px; }
.w-money:deep(.v-field){ width: 170px; }
.num-input:deep(input){ text-align: right; font-weight: 600; color: #111; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; }
.num-input:deep(input)::-webkit-outer-spin-button,
.num-input:deep(input)::-webkit-inner-spin-button{ -webkit-appearance: none; margin: 0; }
.num-input:deep(input){ -moz-appearance: textfield; }
.name-input:deep(.v-field){ min-width: 360px; }
.del-btn{ min-width: 84px; font-weight: 600; }

@media (max-width: 600px){
  .detail-actions{ position: fixed; left: 0; right: 0; bottom: 0; padding-bottom: max(8px, env(safe-area-inset-bottom)); z-index: 8; }
  .detail-drawer{ width: 100vw !important; }
}
</style>
// Compute drawer width responsively: full width unless a left sidebar is visible
const drawerWidth = Vue.ref(1280)
function detectSidebarWidth() {
  try {
    const candidates = Array.from(document.querySelectorAll('aside.v-navigation-drawer'))
    // pick the first left drawer (exclude right drawers)
    const left = candidates.find(el => !el.classList.contains('v-navigation-drawer--right'))
    if (!left) return 0
    const style = getComputedStyle(left)
    if (style.display === 'none' || style.visibility === 'hidden') return 0
    return left.clientWidth || 0
  } catch { return 0 }
}
function recalcDrawerWidth() {
  const vw = window.innerWidth || document.documentElement.clientWidth || 1280
  const sidebar = detectSidebarWidth()
  const padding = 0
  const width = Math.max(360, vw - sidebar - padding)
  drawerWidth.value = Math.min(vw, width)
}
if (typeof window !== 'undefined') {
  window.addEventListener('resize', recalcDrawerWidth)
}
Vue.onMounted(() => { recalcDrawerWidth() })
Vue.onBeforeUnmount(() => { if (typeof window !== 'undefined') window.removeEventListener('resize', recalcDrawerWidth) })
