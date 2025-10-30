<script setup>
import { ref, onMounted, computed } from 'vue'
import { api } from '../../api.js'

const items = ref([])
const search = ref('')
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const editing = ref(null)
const form = ref({ gyarto: '', tipusnev: '', g_cikkszam: '', gyartasiev: '' })

const headers = [
  { title: 'ID', key: 'ID' },
  { title: 'Gyártó', key: 'gyarto' },
  { title: 'Típus', key: 'tipusnev' },
  { title: 'Cikkszám', key: 'g_cikkszam' },
  { title: 'Gyártási év', key: 'gyartasiev' },
  { title: 'Műveletek', key: 'actions', sortable: false },
]

const load = async (q = '') => {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/gepek', { params: { q, limit: 50 } })
    items.value = data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nem sikerült betölteni a gépeket.'
  } finally {
    loading.value = false
  }
}
onMounted(() => load())

let searchTimer
const onSearch = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(search.value), 300)
}

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return items.value
  return items.value.filter(r =>
    String(r.ID).includes(q) ||
    (r.gyarto || '').toLowerCase().includes(q) ||
    (r.tipusnev || '').toLowerCase().includes(q) ||
    (r.g_cikkszam || '').toLowerCase().includes(q)
  )
})

const openAdd = () => { editing.value = null; form.value = { gyarto: '', tipusnev: '', g_cikkszam: '', gyartasiev: '' }; dialog.value = true }
const openEdit = (row) => { editing.value = row; form.value = { gyarto: row.gyarto, tipusnev: row.tipusnev, g_cikkszam: row.g_cikkszam, gyartasiev: row.gyartasiev }; dialog.value = true }
const close = () => { dialog.value = false }

const save = async () => {
  try {
    if (editing.value) {
      await api.put(`/gepek/${editing.value.ID}`, form.value)
    } else {
      await api.post('/gepek', form.value)
    }
    dialog.value = false
    load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Mentés sikertelen.'
  }
}

const removeItem = async (row) => {
  if (!confirm(`Biztosan törlöd: ${row.tipusnev}?`)) return
  try {
    await api.delete(`/gepek/${row.ID}`)
    load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Törlés sikertelen.'
  }
}
</script>

<template>
  <v-container class="py-4">
    <div class="d-flex align-center mb-3">
      <div class="text-h6 font-weight-bold">Gépek nyilvántartása</div>
      <v-spacer />
      <v-btn color="primary" @click="openAdd"><v-icon icon="mdi-plus"></v-icon> Új gép</v-btn>
    </div>

    <v-text-field v-model="search" @input="onSearch" label="Keresés (cikkszám/gyártó/típus)" prepend-inner-icon="mdi-magnify" class="mb-3" />
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>

    <v-data-table :headers="headers" :items="filtered" :loading="loading" item-key="ID">
      <template #item.actions="{ item }">
        <v-btn size="x-small" variant="tonal" color="primary" class="me-2" @click="openEdit(item)">Szerkeszt</v-btn>
        <v-btn size="x-small" variant="tonal" color="error" @click="removeItem(item)">Töröl</v-btn>
      </template>
    </v-data-table>

    <v-dialog v-model="dialog" max-width="600">
      <v-card>
        <v-card-title class="text-h6">{{ editing ? 'Gép szerkesztése' : 'Új gép' }}</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" sm="6"><v-text-field v-model="form.gyarto" label="Gyártó" required /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.tipusnev" label="Típusnév" required /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.g_cikkszam" label="Cikkszám" required /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.gyartasiev" label="Gyártási év" required /></v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Mégse</v-btn>
          <v-btn color="primary" @click="save">Mentés</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
