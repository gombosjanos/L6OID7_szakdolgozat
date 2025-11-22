<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'

const router = useRouter()
const loading = ref(false)
const error = ref('')
const workorders = ref([])

const user = JSON.parse(localStorage.getItem('user') || 'null')
const userId = user?.id ?? user?.ID ?? null

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/munkalapok', { params: { limit: 200 } })
    workorders.value = Array.isArray(data) ? data : []
  } catch (e) {
    error.value =
      e?.response?.data?.message || e.message || 'Nem sikerült betölteni a munkalapokat.'
    workorders.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)

function normalizeStatus(s) {
  try {
    return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  } catch {
    return (s || '').toString().toLowerCase()
  }
}

const ownWorkorders = computed(() => {
  const items = workorders.value || []
  if (!userId) return items
  return items.filter(w => (w.letrehozta ?? w.letrehozo_id ?? null) === userId)
})

const ownStatusCounts = computed(() => {
  const base = {
    total: 0,
    uj: 0,
    folyamatban: 0,
    ajanlat_elkuldve: 0,
    ajanlat_elfogadva: 0,
    ajanlat_elutasitva: 0,
    atadva_lezarva: 0,
  }
  for (const w of ownWorkorders.value || []) {
    base.total += 1
    const k = normalizeStatus(w.statusz || w.status || w.allapot)
    if (k.includes('atadva') || k.includes('lezarva')) {
      base.atadva_lezarva += 1
    } else if (k.includes('elfogadva')) {
      base.ajanlat_elfogadva += 1
    } else if (k.includes('elutasitva')) {
      base.ajanlat_elutasitva += 1
    } else if (k.includes('elkuldve')) {
      base.ajanlat_elkuldve += 1
    } else if (k.includes('folyamatban')) {
      base.folyamatban += 1
    } else if (k.includes('uj')) {
      base.uj += 1
    }
  }
  return base
})

const todayNew = computed(() => {
  const now = new Date()
  const today = now.toISOString().slice(0, 10)
  return (ownWorkorders.value || []).filter(w => {
    const d = new Date(w.letrehozva || w.created_at || 0)
    return d.toISOString().slice(0, 10) === today
  }).length
})

const activeWorkorders = computed(() => {
  const items = ownWorkorders.value || []
  return [...items]
    .filter(w => {
      const k = normalizeStatus(w.statusz || w.status || w.allapot)
      return !k.includes('atadva') && !k.includes('lezarva') && !k.includes('elutasitva')
    })
    .sort((a, b) => {
      const da = new Date(a.letrehozva || a.created_at || 0).getTime()
      const db = new Date(b.letrehozva || b.created_at || 0).getTime()
      return da - db
    })
    .slice(0, 10)
})

function fmtDate(v) {
  try {
    return v ? new Date(v).toLocaleString('hu-HU') : ''
  } catch {
    return v || ''
  }
}

function displayStatus(s) {
  const map = {
    uj: 'Új',
    folyamatban: 'Folyamatban',
    ajanlat_elkuldve: 'Ajánlat elküldve',
    ajanlat_elfogadva: 'Ajánlat elfogadva',
    ajanlat_elutasitva: 'Ajánlat elutasítva',
    atadva_lezarva: 'Átadva / lezárva',
  }
  const k = normalizeStatus(s)
  return map[k] || s || '-'
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

function openWorkorder(id) {
  if (!id) return
  router.push(`/admin/munkalapok/${id}`)
}
</script>

<template>
  <v-container class="py-4">
    <v-row class="mb-4" align="center">
      <v-col cols="12">
        <div class="text-h5 font-weight-bold">Szerelő műhely áttekintés</div>
        <div class="text-body-2 text-medium-emphasis">
          Saját munkalapjaid állapota és a mai teendők.
        </div>
      </v-col>
    </v-row>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
    </v-alert>

    <v-row>
      <v-col cols="12" md="8">
        <v-card elevation="1" class="pa-4 mb-4">
          <div class="text-subtitle-1 font-weight-medium mb-3">Saját összefoglaló</div>
          <v-row>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Összes saját munkalap</div>
              <div class="text-h5 font-weight-bold">{{ ownStatusCounts.total }}</div>
            </v-col>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Aktív</div>
              <div class="text-h5 font-weight-bold text-blue-darken-1">
                {{ ownStatusCounts.uj + ownStatusCounts.folyamatban + ownStatusCounts.ajanlat_elkuldve }}
              </div>
            </v-col>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Ma érkezett</div>
              <div class="text-h5 font-weight-bold text-indigo">
                {{ todayNew }}
              </div>
            </v-col>
          </v-row>
        </v-card>

        <v-card elevation="1" class="pa-4">
          <div class="d-flex align-center mb-3">
            <div class="text-subtitle-1 font-weight-medium">Aktív saját munkalapok</div>
            <v-spacer />
            <v-btn
              size="small"
              variant="text"
              color="primary"
              @click="router.push('/admin/munkalapok')"
            >
              Munkalap lista
            </v-btn>
          </div>
          <v-skeleton-loader v-if="loading" type="table" />
          <template v-else>
            <div v-if="activeWorkorders.length === 0" class="text-medium-emphasis">
              Jelenleg nincs aktív saját munkalapod.
            </div>
            <v-table v-else density="compact">
              <thead>
                <tr>
                  <th>Azonosító</th>
                  <th>Ügyfél</th>
                  <th>Állapot</th>
                  <th class="text-right">Létrehozva</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="w in activeWorkorders"
                  :key="w.ID || w.id"
                  class="cursor-pointer"
                  @click="openWorkorder(w.ID || w.id)"
                >
                  <td>{{ w.azonosito || w.munkalapsorsz || (w.ID || w.id) }}</td>
                  <td>{{ getUgyfelNev(w) }}</td>
                  <td>{{ displayStatus(w.statusz) }}</td>
                  <td class="text-right">{{ fmtDate(w.letrehozva || w.created_at) }}</td>
                </tr>
              </tbody>
            </v-table>
          </template>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card elevation="1" class="pa-4 mb-4">
          <div class="text-subtitle-1 font-weight-medium mb-2">Státusz bontás</div>
          <v-row>
            <v-col cols="6" class="mb-2">
              <div class="text-caption text-medium-emphasis">Új</div>
              <div class="text-h6 font-weight-bold text-grey-darken-1">{{ ownStatusCounts.uj }}</div>
            </v-col>
            <v-col cols="6" class="mb-2">
              <div class="text-caption text-medium-emphasis">Folyamatban</div>
              <div class="text-h6 font-weight-bold text-blue-darken-1">
                {{ ownStatusCounts.folyamatban }}
              </div>
            </v-col>
            <v-col cols="6" class="mb-2">
              <div class="text-caption text-medium-emphasis">Ajánlat elküldve</div>
              <div class="text-h6 font-weight-bold text-deep-purple">
                {{ ownStatusCounts.ajanlat_elkuldve }}
              </div>
            </v-col>
            <v-col cols="6" class="mb-2">
              <div class="text-caption text-medium-emphasis">Elfogadott ajánlat</div>
              <div class="text-h6 font-weight-bold text-green-darken-1">
                {{ ownStatusCounts.ajanlat_elfogadva }}
              </div>
            </v-col>
          </v-row>
        </v-card>

      </v-col>
    </v-row>
  </v-container>
</template>

