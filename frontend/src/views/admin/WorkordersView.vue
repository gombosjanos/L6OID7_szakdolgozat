<template>
  <v-container fluid class="pa-4">
    <v-row class="align-center mb-4" no-gutters>
  <v-col cols="12" md="6">
    <h2 class="text-h5 font-weight-medium">Munkalapok</h2>
  </v-col>
  <v-col cols="12" md="6">
    <div class="header-controls">
      <v-text-field
        v-model="search"
        variant="outlined"
        density="comfortable"
        label="Keresés (ügyfél, gép, azonosító, létrehozó)"
        prepend-inner-icon="mdi-magnify"
        clearable
        class="header-search"
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
        class="header-status"
      />
      <v-select
        v-model="limit"
        :items="[25, 50, 100]"
        variant="outlined"
        density="comfortable"
        label="Limit"
        class="header-limit"
      />
      <v-btn
        color="primary"
        variant="elevated"
        class="header-create"
        @click="startCreate"
      >
        Új munkalap
      </v-btn>
    </div>
  </v-col>
</v-row>

   

    <v-alert v-if="listError" type="error" variant="tonal" class="mb-3">
      {{ listError }}
    </v-alert>
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
          item-key="ID"
          density="comfortable"
          class="elevation-0 clickable-table"
          @click:row="openDetail"
        >
          <template #item.Azonosító="{ item }">
            <div class="row-link">
              <span>
                {{
                  item.munkalapsorsz ||
                  item.Azonosító ||
                  (fmtDate(item.letrehozva || item.created_at).slice(0, 4) + '-' + (item.ID || item.id))
                }}
              </span>
              <v-icon size="18" class="chev" icon="mdi-chevron-right" />
            </div>
          </template>
          <template #item.Ugyfel="{ item }">
            {{ getUgyfelNev(item) }}
          </template>
          <template #item.Letrehozta="{ item }">
            {{ getLetrehozoNev(item) }}
          </template>
          <template #item.gep="{ item }">
            {{ gepLabel(gepFromRow(item)) }}
          </template>
          <template #item.Letrehozva="{ item }">
            {{ fmtDate(item.letrehozva || item.created_at) }}
          </template>
          <template #item.statusz="{ item }">
            <v-chip size="x-small" :color="statusColor(getStatus(item))" variant="flat">
              {{ displayStatus(getStatus(item)) }}
            </v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-btn
              v-if="isAdmin"
              size="small"
              variant="text"
              color="error"
              icon="mdi-delete"
              @click.stop="deleteWorkorder(item)"
            />
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
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'

async function request(path, { method = 'GET', body } = {}) {
  const url = `/api${path}`
  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' }
  try {
    const tk =
      localStorage.getItem('auth_token') ||
      localStorage.getItem('token') ||
      localStorage.getItem('AUTH_TOKEN')
    if (tk) headers.Authorization = `Bearer ${tk}`
  } catch {}
  const res = await fetch(
    url + (method === 'GET' && body ? `?${new URLSearchParams(body)}` : ''),
    {
      method,
      headers,
      body: method === 'GET' ? undefined : JSON.stringify(body ?? {}),
      credentials: 'include',
    }
  )
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${await res.text().catch(() => '')}`)
  const ct = res.headers.get('content-type') || ''
  return ct.includes('application/json') ? res.json() : null
}

const headers = [
  { title: 'Azonosító', value: 'Azonosító', sortable: true, width: 140 },
  { title: 'Ügyfél', value: 'Ugyfel', sortable: false },
  { title: 'Létrehozta', value: 'Letrehozta', sortable: false },
  { title: 'Gép', value: 'gep', sortable: false },
  { title: 'Létrehozva', value: 'Letrehozva', sortable: true, width: 180 },
  { title: 'Státusz', value: 'statusz', sortable: true, width: 160 },
  { title: '', value: 'actions', align: 'end', sortable: false, width: 80 },
]

const workorders = ref([])
const loadingList = ref(false)
const listError = ref('')
const search = ref('')
const limit = ref(50)
const statusFilter = ref('')
let debounceT

const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')
function setSnack(text, color = 'success') {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

const statusFilterItems = computed(() => [
  { title: 'Mind', value: '' },
  { title: 'Új', value: 'uj' },
  { title: 'Folyamatban', value: 'folyamatban' },
  { title: 'Ajánlat elküldve', value: 'ajanlat_elkuldve' },
  { title: 'Ajánlat elfogadva', value: 'ajanlat_elfogadva' },
  { title: 'Ajánlat elutasítva', value: 'ajanlat_elutasitva' },
  { title: 'Átadva / lezárva', value: 'atadva_lezarva' },
])

const router = useRouter()
const isClient = typeof window !== 'undefined'
const isAdmin = computed(() => {
  if (!isClient) return false
  const storage = window.localStorage
  const role = (storage.getItem('jogosultsag') || storage.getItem('role') || '').toLowerCase()
  return role === 'admin'
})

function statusColor(s) {
  const k = (s || '').toLowerCase()
  switch (k) {
    case 'uj':
      return 'grey'
    case 'folyamatban':
      return 'blue'
    case 'ajanlat_elkuldve':
      return 'purple'
    case 'alkatreszre_var':
      return 'orange'
    case 'javitas_kesz':
      return 'green'
    case 'ajanlat_elfogadva':
      return 'indigo'
    case 'atadva_lezarva':
      return 'teal'
    case 'ajanlat_elutasitva':
      return 'red'
    default:
      return 'grey'
  }
}

function displayStatus(s) {
  const key = (s || '').toLowerCase()
  const map = {
    uj: 'Új',
    folyamatban: 'Folyamatban',
    ajanlat_elkuldve: 'Ajánlat elküldve',
    alkatreszre_var: 'Alkatrészre vár',
    javitas_kesz: 'Javítás kész',
    ajanlat_elutasitva: 'Ajánlat elutasítva',
    atadva_lezarva: 'Átadva / lezárva',
    ajanlat_elfogadva: 'Ajánlat elfogadva',
  }
  return map[key] || s || '-'
}

function fmtDate(v) {
  try {
    return v ? new Date(v).toLocaleString('hu-HU') : ''
  } catch {
    return v || ''
  }
}

function gepLabel(g) {
  if (!g) return '-'
  const parts = [g.gyarto, g.tipusnev, g.g_cikkszam].filter(Boolean)
  return parts.join(' - ')
}

function gepFromRow(row) {
  if (row?.gep) return row.gep
  if (row?.gep_adatok) return row.gep_adatok
  const gyarto = row?.gyarto || row?.gep_gyarto
  const tipusnev = row?.tipusnev || row?.gep_tipus
  const g_cikkszam = row?.g_cikkszam || row?.cikkszam || row?.gep_cikkszam
  if (gyarto || tipusnev || g_cikkszam) return { gyarto, tipusnev, g_cikkszam }
  return null
}

function getId(row) {
  return row?.ID ?? row?.id ?? row?.Azonosító ?? row?.munkalap_id ?? null
}

function getUgyfelNev(row) {
  return (
    row?.ugyfel?.nev ??
    row?.Ugyfel?.nev ??
    row?.Ugyfel_nev ??
    row?.nev ??
    row?.Ugyfel_adatok?.nev ??
    '-'
  )
}

function getLetrehozoNev(row) {
  return (
    row?.letrehozo?.nev ??
    row?.Letrehozo?.nev ??
    row?.letrehozta_nev ??
    '-'
  )
}

function getStatus(row) {
  return (row?.statusz ?? row?.status ?? row?.allapot ?? '').toString()
}

async function fetchList() {
  loadingList.value = true
  listError.value = ''
  try {
    const data = await request('/munkalapok', {
      method: 'GET',
      body: { q: search.value, limit: String(limit.value) },
    })
    workorders.value = Array.isArray(data) ? data : []
  } catch (e) {
    console.error('Fetch error:', e)
    listError.value = e?.message || 'Lista betöltési hiba.'
    workorders.value = []
  } finally {
    loadingList.value = false
  }
}

const filteredWorkorders = computed(() => {
  const items = workorders.value || []
  const q = (search.value || '').toLowerCase().trim()
  const statusF = (statusFilter.value || '').toLowerCase().trim()

  return items.filter(item => {
    const name = (getUgyfelNev(item) || '').toLowerCase()
    const creator = (getLetrehozoNev(item) || '').toLowerCase()
    const machine = (gepLabel(gepFromRow(item)) || '').toLowerCase()
    const idText =
      (item.munkalapsorsz ||
        item.ID ||
        item.id ||
        '').toString().toLowerCase()

    const matchQ =
      !q ||
      name.includes(q) ||
      creator.includes(q) ||
      machine.includes(q) ||
      idText.includes(q)

    const s = (getStatus(item) || '').toLowerCase()
    const matchStatus =
      !statusF || s.includes(statusF) || displayStatus(s).toLowerCase().includes(statusF)

    return matchQ && matchStatus
  })
})

function openDetail(evtOrItem, maybeRow) {
  const r =
    (maybeRow && (maybeRow.item || maybeRow)) ||
    (evtOrItem?.item ? evtOrItem.item : evtOrItem)
  const id = getId(r)
  if (!id) return
  router.push(`/admin/munkalapok/${id}`)
}

function startCreate() {
  router.push('/admin/munkalapok/uj')
}

async function deleteWorkorder(item) {
  const id = getId(item)
  if (!id) return
  if (!window.confirm('Biztosan törli a munkalapot?')) return
  try {
    await request(`/munkalapok/${id}`, { method: 'DELETE' })
    setSnack('Munkalap törölve', 'success')
    await fetchList()
  } catch (e) {
    setSnack(e?.message || 'Törlési hiba.', 'error')
  }
}

watch(search, () => {
  if (debounceT) clearTimeout(debounceT)
  debounceT = setTimeout(fetchList, 250)
})
watch(limit, fetchList)

onMounted(fetchList)
</script>

<style scoped>
.table-scroll {
  overflow-x: auto;
}
.row-link {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.row-link .chev {
  opacity: 0.4;
}
.clickable-table:deep(tbody tr) {
  cursor: pointer;
}
.clickable-table:deep(tbody tr:hover) {
  background-color: #f5f7fa;
}
.header-controls {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
  align-items: center;
}

.header-search {
  max-width: 420px;
}
.header-status {
  max-width: 200px;
}
.header-limit {
  max-width: 120px;
}

@media (max-width: 960px) {
  .header-controls {
    justify-content: flex-start;
  }
  .header-search,
  .header-status,
  .header-limit,
  .header-create {
    flex: 1 1 100%;
    max-width: 100% !important;
  }
  .header-create {
    justify-content: center;
  }
}

</style>
