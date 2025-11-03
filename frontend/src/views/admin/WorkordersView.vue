<template>
  <v-container fluid class="pa-4">
    <v-row class="align-center mb-4" no-gutters>
      <v-col cols="12" md="6">
        <h2 class="text-h5 font-weight-medium">Munkalapok</h2>
      </v-col>
      <v-col cols="12" md="6" class="d-flex justify-end align-center ga-2">
        <v-text-field
          v-model="search"
          variant="outlined"
          density="comfortable"
          label="Keresés (Ügyfél, gép, azonosító)"
          prepend-inner-icon="mdi-magnify"
          clearable
          class="mr-2"
          style="max-width: 420px"
        />
        <v-select
          v-model="statusFilter"
          :items="statusFilterItems"
          item-title="title"
          item-value="value"
          variant="outlined"
          density="comfortable"
          label="Státusz"
          clearable
          style="max-width: 200px"
        />
        <v-select
          v-model="limit"
          :items="[25,50,100]"
          variant="outlined"
          density="comfortable"
          label="Limit"
          style="max-width: 120px"
        />
        <v-btn color="primary" variant="elevated" @click="startCreate">Új munkalap</v-btn>
      </v-col>
    </v-row>

    <v-alert v-if="listError" type="error" variant="tonal" class="mb-3">{{ listError }}</v-alert>
    <v-snackbar v-model="snackbar" :timeout="3000" :color="snackbarColor" location="top right">
      {{ snackbarText }}
      <template #actions>
        <v-btn variant="text" @click="snackbar = false">OK</v-btn>
      </template>
    </v-snackbar>

    <v-card>
      <div class="table-scroll">
        <v-data-table
          :items="filteredWorkorders"
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
            <v-chip size="small" :color="statusColor(getStatus(item))" variant="flat">{{ displayStatus(getStatus(item)) }}</v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-btn size="small" variant="text" icon="mdi-open-in-new" @click.stop="openDetail(item)" />
            <v-btn size="small" variant="text" color="error" icon="mdi-delete" :disabled="!isAdmin" @click.stop="deleteWorkorder(item)" />
          </template>
          <template #no-data>
            <div class="pa-6 text-medium-emphasis">Nincs megjeleníthető munkalap.</div>
          </template>
        </v-data-table>
      </div>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
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

const headers = [
  { title: 'Azonosító', value: 'id', sortable: true, width: 110 },
  { title: 'Ügyfél', value: 'ugyfel', sortable: false },
  { title: 'Gép', value: 'gep', sortable: false },
  { title: 'Létrehozva', value: 'letrehozva', sortable: true, width: 180 },
  { title: 'Státusz', value: 'statusz', sortable: true, width: 160 },
  { title: '', value: 'actions', align: 'end', sortable: false, width: 80 },
]

const workorders = ref([])
const loadingList = ref(false)
const listError = ref('')
const search = ref('')
const limit = ref(50)
let debounceT

const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')
function setSnack(text, color='success'){ snackbarText.value=text; snackbarColor.value=color; snackbar.value=true }

const statusFilter = ref('')
const statusFilterItems = computed(()=>[
  { title: 'Mind', value: '' },
  { title: 'Új', value: 'Új' },
  { title: 'Folyamatban', value: 'Folyamatban' },
  { title: 'Árajánlat elküldve', value: 'Árajánlat elküldve' },
  { title: 'Alkatrészre vár', value: 'Alkatrészre vár' },
  { title: 'Javítás kész', value: 'Javítás kész' },
  { title: 'Árajánlat elutasítva', value: 'Árajánlat elutasítva' },
  { title: 'Átadva/Lezárva', value: 'Átadva/Lezárva' },
  { title: 'Árajánlat elfogadva', value: 'Árajánlat elfogadva' },
])

const router = useRouter()
const isAdmin = computed(()=>{ const role=(localStorage.getItem('jogosultsag')||localStorage.getItem('role')||'').toLowerCase(); return role==='admin' })

function statusColor(s){
  switch((s||'').toLowerCase()){
    case 'Új': case 'uj': return 'grey'
    case 'Folyamatban': return 'blue'
    case 'Árajanlat_elküldve': case 'Árajánlat elküldve': case 'Árajánlat elküldve': case 'Árajánlat elküldve': return 'purple'
    case 'Alkatrészre vár': return 'orange'
    case 'Árajánlat elfogadva': return 'indigo'
    case 'Javítás kész': return 'green'
    case 'Átadva/Lezárva': return 'teal'
    case 'Árajánlat elutasítva': return 'red'
    default: return 'grey'
  }
}
function displayStatus(s){
  const key=(s||'').toLowerCase()
  const map={ 'uj':'Új','új':'Új','folyamatban':'Folyamatban','ajanlat_kesz':'Ajánlat kész','ajánlat elkészült':'Ajánlat kész','ajanlat elkészült':'Ajánlat kész','ajánlat kész':'Ajánlat kész','vár alkatrészre':'Vár alkatrészre','javítás alatt':'Javítás alatt','kész':'Kész','átadva':'Átadva','elutasítva':'Elutasítva' }
  return map[key] || s || '-'
}
function fmtDate(v){ try { return v ? new Date(v).toLocaleString('hu-HU') : '' } catch { return v || '' } }
function gepLabel(g){ if(!g) return '-'; const parts=[g.gyarto,g.tipusnev,g.g_cikkszam].filter(Boolean); return parts.join(' - ') }
function gepFromRow(row){ if(row?.gep) return row.gep; if(row?.gep_adatok) return row.gep_adatok; const gyarto=row?.gyarto||row?.gep_gyarto; const tipusnev=row?.tipusnev||row?.gep_tipus; const g_cikkszam=row?.g_cikkszam||row?.cikkszam||row?.gep_cikkszam; if(gyarto||tipusnev||g_cikkszam) return {gyarto,tipusnev,g_cikkszam}; return null }
function getId(row){ return row?.id ?? row?.ID ?? row?.azonosito ?? row?.munkalap_id ?? null }
function getUgyfelNev(row){ return row?.ugyfel?.nev ?? row?.ugyfel_nev ?? row?.nev ?? row?.ugyfel_adatok?.nev ?? '-' }
function getStatus(row){ return (row?.statusz ?? row?.status ?? row?.allapot ?? '').toString() }

async function fetchList(){
  loadingList.value = true; listError.value = ''
  try{
    const data = await request('/munkalapok', { method: 'GET', body: { q: search.value, limit: String(limit.value) } })
    workorders.value = Array.isArray(data) ? data : []
  } catch(e){ listError.value = e?.message || 'Lista betöltési hiba.' }
  finally{ loadingList.value = false }
}

function onRowClick(_, row){ const r=row?.item||row; const id=getId(r); if(!id) return; router.push(`/admin/munkalapok/${id}`) }
function openDetail(item){ const id=getId(item); if(!id) return; router.push(`/admin/munkalapok/${id}`) }
function startCreate(){ router.push('/admin/munkalapok/uj') }

watch(search, ()=>{ if(debounceT) clearTimeout(debounceT); debounceT=setTimeout(fetchList, 250) })
watch(limit, fetchList)

const filteredWorkorders = computed(()=>{
  const sf=(statusFilter.value||'').toLowerCase()
  if(!sf) return workorders.value
  return (workorders.value||[]).filter(w=>{
    const disp = displayStatus(getStatus(w))
    return (disp||'').toLowerCase()===sf
  })
})

async function deleteWorkorder(item){
  const id = getId(item)
  if(!id) return
  if(!confirm('Biztosan törli a munkalapot?')) return
  try{
    await request(`/munkalapok/${id}`, { method: 'DELETE' })
    setSnack('Munkalap törölve')
    fetchList()
  }catch(e){ setSnack(e?.message || 'Törlés sikertelen', 'error') }
}

fetchList()
</script>

<style scoped>
.table-scroll{ overflow-x: auto; }
:deep(.v-btn:not(.v-btn--icon)) { text-align: center; }
:deep(.v-btn:not(.v-btn--icon) .v-btn__content) { justify-content: center; width: 100%; }
</style>
