<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'

const router = useRouter()
const loading = ref(false)
const error = ref('')
const workorders = ref([])

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

const statusCounts = computed(() => {
  const base = {
    total: 0,
    uj: 0,
    folyamatban: 0,
    ajanlat_elkuldve: 0,
    ajanlat_elfogadva: 0,
    ajanlat_elutasitva: 0,
    atadva_lezarva: 0,
  }
  for (const w of workorders.value || []) {
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

const advancedStats = computed(() => {
  const now = new Date()
  const today = now.toISOString().slice(0, 10)
  const weekAgo = now.getTime() - 7 * 24 * 60 * 60 * 1000

  const res = {
    open: 0,
    todayNew: 0,
    last7New: 0,
    offersWaiting: 0,
    offersAccepted: 0,
    offersRejected: 0,
  }

  for (const w of workorders.value || []) {
    const createdAt = new Date(w.letrehozva || w.created_at || 0)
    const s = normalizeStatus(w.statusz || w.status || w.allapot)

    const isClosed = s === 'atadva_lezarva' || s.includes('lezarva') || s.includes('atadva')

    if (!isClosed) res.open += 1

    const dateStr = createdAt.toISOString().slice(0, 10)
    if (dateStr === today) res.todayNew += 1
    if (createdAt.getTime() >= weekAgo) res.last7New += 1

    if (s === 'ajanlat_elkuldve') res.offersWaiting += 1
    if (s === 'ajanlat_elfogadva') res.offersAccepted += 1
    if (s === 'ajanlat_elutasitva') res.offersRejected += 1
  }

  return res
})

const openWorkorders = computed(() => {
  const items = workorders.value || []
  return [...items]
    .filter(w => {
      const k = normalizeStatus(w.statusz || w.status || w.allapot)
      return !k.includes('atadva') && !k.includes('lezarva')
    })
    .sort((a, b) => {
      const da = new Date(a.letrehozva || a.created_at || 0).getTime()
      const db = new Date(b.letrehozva || b.created_at || 0).getTime()
      return db - da
    })
    .slice(0, 6)
})

function fmtDate(v) {
  try {
    return v ? new Date(v).toLocaleString('hu-HU') : ''
  } catch {
    return v || ''
  }
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

function openWorkorder(id) {
  if (!id) return
  router.push(`/admin/munkalapok/${id}`)
}

const topCustomers = computed(() => {
  const counts = new Map()
  for (const w of workorders.value || []) {
    const s = normalizeStatus(w.statusz || w.status || w.allapot)
    if (s === 'atadva_lezarva' || s.includes('atadva') || s.includes('lezarva')) continue
    const name = getUgyfelNev(w)
    if (!name || name === '-') continue
    counts.set(name, (counts.get(name) || 0) + 1)
  }
  return Array.from(counts.entries())
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5)
})
</script>

<template>
  <v-container class="py-4">
    <v-row class="mb-4" align="center">
      <v-col cols="12">
        <div class="text-h5 font-weight-bold">Admin irányítópult</div>

      </v-col>
    </v-row>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
    </v-alert>

    <v-row>
      <v-col cols="12" md="7">
        <v-card elevation="1" class="pa-4 mb-4">
          <div class="text-subtitle-1 font-weight-medium mb-3">Munkalap összefoglaló</div>
          <v-row>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Összes munkalap</div>
              <div class="text-h5 font-weight-bold">{{ statusCounts.total }}</div>
            </v-col>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Új</div>
              <div class="text-h5 font-weight-bold text-grey-darken-1">{{ statusCounts.uj }}</div>
            </v-col>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Folyamatban</div>
              <div class="text-h5 font-weight-bold text-blue-darken-1">
                {{ statusCounts.folyamatban }}
              </div>
            </v-col>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Ajánlat elküldve</div>
              <div class="text-h5 font-weight-bold text-deep-purple">
                {{ statusCounts.ajanlat_elkuldve }}
              </div>
            </v-col>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Elfogadott ajánlat</div>
              <div class="text-h5 font-weight-bold text-green-darken-1">
                {{ statusCounts.ajanlat_elfogadva }}
              </div>
            </v-col>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Lezárt / átadott</div>
              <div class="text-h5 font-weight-bold text-teal-darken-1">
                {{ statusCounts.atadva_lezarva }}
              </div>
            </v-col>
          </v-row>
        </v-card>

        <v-card elevation="1" class="pa-4 mb-4">
          <div class="text-subtitle-1 font-weight-medium mb-3">Napi aktivitás</div>
          <v-row>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Nyitott munkalap</div>
              <div class="text-h5 font-weight-bold text-orange-darken-2">
                {{ advancedStats.open }}
              </div>
            </v-col>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Ma létrehozva</div>
              <div class="text-h5 font-weight-bold text-indigo">
                {{ advancedStats.todayNew }}
              </div>
            </v-col>
            <v-col cols="6" sm="4" class="mb-3">
              <div class="text-caption text-medium-emphasis">Elmúlt 7 nap</div>
              <div class="text-h5 font-weight-bold text-brown-darken-1">
                {{ advancedStats.last7New }}
              </div>
            </v-col>
          </v-row>
        </v-card>

        <v-card elevation="1" class="pa-4">
          <div class="d-flex align-center mb-3">
            <div class="text-subtitle-1 font-weight-medium">Legutóbbi nyitott munkalapok</div>
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
            <div v-if="openWorkorders.length === 0" class="text-medium-emphasis">
              Jelenleg nincs nyitott munkalap.
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
                  v-for="w in openWorkorders"
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

      <v-col cols="12" md="5">
        <v-card elevation="1" class="pa-4 mb-4">
          <div class="text-subtitle-1 font-weight-medium mb-3">Ajánlat státuszok</div>
          <v-row>
            <v-col cols="6" class="mb-3">
              <div class="text-caption text-medium-emphasis">Ajánlat elküldve</div>
              <div class="text-h6 font-weight-bold text-deep-purple">
                {{ advancedStats.offersWaiting }}
              </div>
            </v-col>
            <v-col cols="6" class="mb-3">
              <div class="text-caption text-medium-emphasis">Elfogadott ajánlat</div>
              <div class="text-h6 font-weight-bold text-green-darken-1">
                {{ advancedStats.offersAccepted }}
              </div>
            </v-col>
            <v-col cols="6" class="mb-3">
              <div class="text-caption text-medium-emphasis">Elutasított ajánlat</div>
              <div class="text-h6 font-weight-bold text-red-darken-1">
                {{ advancedStats.offersRejected }}
              </div>
            </v-col>
          </v-row>
        </v-card>

        <v-card elevation="1" class="pa-4 mb-4">
          <div class="text-subtitle-1 font-weight-medium mb-3">Top ügyfelek (nyitott munkalapok)</div>
          <div v-if="topCustomers.length === 0" class="text-medium-emphasis">
            Nincs aktív munkalap.
          </div>
          <v-list v-else density="compact">
            <v-list-item
              v-for="c in topCustomers"
              :key="c.name"
            >
              <v-list-item-title>{{ c.name }}</v-list-item-title>
              <v-list-item-subtitle>{{ c.count }} nyitott munkalap</v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card>

        
      </v-col>
    </v-row>
  </v-container>
</template>

