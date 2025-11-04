<script setup>
import { ref, onMounted, computed } from 'vue'
import { api } from '../api.js'

const user = JSON.parse(localStorage.getItem('user') || 'null')
const loading = ref(false)
const error = ref('')
const munkalapok = ref([])
const offers = ref({})

const load = async () => {
  error.value = ''
  loading.value = true
  try {
    if (!user?.id && !user?.ID) throw new Error('Nincs bejelentkezett felhasználó')
    const userId = user.id ?? user.ID
    const { data } = await api.get('/munkalapok', { params: { user_id: userId } })
    munkalapok.value = Array.isArray(data) ? data : []
    // Elő-töltjük az ajánlat státuszokat, hogy a listában is látszódjon
    const ids = (munkalapok.value||[]).map(r=> r.ID || r.id).filter(Boolean)
    for (const wId of ids) {
      try {
        const res = await api.get(`/munkalapok/${wId}/ajanlat`)
        offers.value[wId] = res?.data || null
      } catch {
        offers.value[wId] = null
      }
    }
  } catch (e) {
    error.value = e?.response?.data?.message || e.message || 'Nem sikerült betölteni a munkalapokat.'
    console.debug('Load munkalapok error', e?.response?.data || e)
  } finally {
    loading.value = false
  }
}

onMounted(load)

const accept = async (row) => {
  try {
    await api.post(`/munkalapok/${row.ID || row.id}/ajanlat/accept`)
    load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nem sikerült elfogadni az árajánlatot.'
  }
}

const reject = async (row) => {
  try {
    await api.post(`/munkalapok/${row.ID || row.id}/ajanlat/reject`)
    load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nem sikerült elutasítani az árajánlatot.'
  }
}

const headers = [
  { title: 'Azonosító', key: 'azonosito' },
  { title: 'Állapot', key: 'statusz' },
  { title: 'Hiba leírás', key: 'hibaleiras' },
  { title: 'Létrehozva', key: 'letrehozva' },
]

const hasData = computed(() => (munkalapok.value?.length || 0) > 0)

function normalizeStatus(s){
  try{ return (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'') }catch{ return (s||'').toString().toLowerCase() }
}
function coarseStatus(s){
  const n = normalizeStatus(s)
  if (n.includes('elkuldve')) return 'elkuldve'
  if (n.includes('elfogad')) return 'elfogadva'
  if (n.includes('elutasit')) return 'elutasitva'
  if (n.includes('ajanlat_elkuldve')) return 'elkuldve'
  if (n.includes('ajanlat_elfogadva')) return 'elfogadva'
  if (n.includes('ajanlat_elutasitva')) return 'elutasitva'
  return ''
}
function canDecide(item){
  const id = item?.ID || item?.id
  const os = coarseStatus(offers.value?.[id]?.statusz)
  if (os === 'elkuldve') return true
  const ws = coarseStatus(item?.statusz)
  return ws === 'elkuldve'
}
function getId(row){ return row?.ID ?? row?.id }
function openDetail(evtOrItem, maybeRow){
  const r = (maybeRow && (maybeRow.item || maybeRow)) || (evtOrItem?.item ? evtOrItem.item : evtOrItem)
  const id = getId(r)
  if(!id) return
  window.location.href = `/ugyfel/munkalapok/${id}`
}
</script>

<template>
  <v-container class="py-4">
    <div class="text-h6 font-weight-bold mb-2">Munkalapok</div>
    <div class="text-body-2 text-medium-emphasis mb-4">Itt követheted a javítás állapotát és dönthetsz az árajánlatról.</div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

    <v-skeleton-loader v-if="loading" type="table"></v-skeleton-loader>

    <template v-else>
      <v-card v-if="hasData" elevation="1">
        <v-data-table :headers="headers" :items="munkalapok" item-key="ID" class="clickable-table" @click:row="openDetail">
          <template #item.letrehozva="{ item }">{{ new Date(item.letrehozva || item.created_at).toLocaleString('hu-HU') }}</template>
          <template #item.statusz="{ item }">
            <v-chip
              :color="{
                'uj': 'grey',
                'folyamatban': 'primary',
                'ajanlat_elkuldve': 'purple',
                'ajanlat_elfogadva': 'success',
                'ajanlat_elutasitva': 'error',
                'kesz': 'success',
              }[item.statusz] || 'default'"
              variant="tonal"
              size="small"
            >
              {{ {
                'uj': 'Új',
                'folyamatban': 'Folyamatban',
                'ajanlat_kesz': 'Ajánlat kész',
                'ajanlat_elfogadva': 'Ajánlat elfogadva',
                'ajanlat_elutasitva': 'Ajánlat elutasítva',
                'kesz': 'Kész',
              }[item.statusz] || item.statusz }}
            </v-chip>
          </template>
        </v-data-table>
      </v-card>
      <v-card v-else elevation="1" class="pa-6 text-center text-medium-emphasis">
        Még nincs munkalapod.
      </v-card>
    </template>
  </v-container>
</template>



<style scoped>
.clickable-table:deep(tbody tr){ cursor: pointer; }
.clickable-table:deep(tbody tr:hover){ background-color: #f5f7fa; }
</style>


