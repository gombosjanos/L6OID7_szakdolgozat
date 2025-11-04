<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../../api.js'

const items = ref([])
const search = ref('')
const loading = ref(false)
const error = ref('')

const dialog = ref(false)
const editing = ref(null)
const form = ref({ nev: '', felhasznalonev: '', email: '', telefonszam: '', jogosultsag: 'ugyfel', password: '' })

const roleLabels = {
  ugyfel: 'Ügyfél',
  szerelo: 'Szerelő',
  admin: 'Admin',
}

const roleOptions = [
  { title: roleLabels.ugyfel, value: 'ugyfel' },
  { title: roleLabels.szerelo, value: 'szerelo' },
  { title: roleLabels.admin, value: 'admin' },
]

const headers = [
  { title: 'ID', key: 'ID' },
  { title: 'Név', key: 'nev' },
  { title: 'Felhasználónév', key: 'felhasznalonev' },
  { title: 'Email', key: 'email' },
  { title: 'Telefon', key: 'telefonszam' },
  { title: 'Jogosultság', key: 'jogosultsag' },
  { title: 'Műveletek', key: 'actions', sortable: false },
]

const load = async (q = '') => {
  loading.value = true
  error.value = ''
  try {
  const { data } = await api.get('/felhasznalok', { params: { q, limit: 50 } })
  items.value = (data || []).map(r => ({ ID: r.ID ?? r.id, ...r }))
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nem sikerült betölteni az ügyfeleket.'
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
    (r.nev || '').toLowerCase().includes(q) ||
    (r.email || '').toLowerCase().includes(q) ||
    (r.felhasznalonev || '').toLowerCase().includes(q)
  )
})

const openAdd = () => {
  editing.value = null
  form.value = { nev: '', felhasznalonev: '', email: '', telefonszam: '', jogosultsag: 'ugyfel', password: '' }
  dialog.value = true
}
const openEdit = (row) => {
  editing.value = row
  form.value = {
    nev: row.nev || '',
    felhasznalonev: row.felhasznalonev || '',
    email: row.email || '',
    telefonszam: row.telefonszam || '',
  jogosultsag: row.jogosultsag || 'ugyfel',
    password: '',
  }
  dialog.value = true
}
const close = () => { dialog.value = false }

const save = async () => {
  try {
    const payload = {
      nev: form.value.nev?.trim(),
      email: form.value.email?.trim(),
      jogosultsag: form.value.jogosultsag,
    }

    if (form.value.felhasznalonev?.trim()) {
      payload.felhasznalonev = form.value.felhasznalonev.trim()
    }

    if (form.value.telefonszam?.trim()) {
      payload.telefonszam = form.value.telefonszam.trim()
    }

    if (form.value.password?.trim()) {
      payload.password = form.value.password
    }

    if (editing.value) {
      await api.put(`/felhasznalok/${editing.value.ID}`, payload)
    } else {
      await api.post('/felhasznalok', payload)
    }
    dialog.value = false
    load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Mentés sikertelen.'
  }
}

const removeItem = async (row) => {
  if (!confirm(`Biztosan törlöd: ${row.nev}?`)) return
  try {
    await api.delete(`/felhasznalok/${row.ID}`)
    load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Törlés sikertelen.'
  }
}
</script>

<template>
  <v-container class="py-4">
    <div class="d-flex align-center mb-3">
      <div class="text-h6 font-weight-bold">Ügyfelek</div>
      <v-spacer />
      <v-btn color="primary" @click="openAdd"><v-icon icon="mdi-plus"></v-icon> Új ügyfél</v-btn>
    </div>

    <v-text-field v-model="search" @input="onSearch" label="Keresés (név/email/felhasználónév)" prepend-inner-icon="mdi-magnify" class="mb-3" />
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>

    <v-data-table :headers="headers" :items="filtered" :loading="loading" item-key="ID">
      <template #item.jogosultsag="{ item }">
        {{ roleLabels[item.raw?.jogosultsag] ?? item.raw?.jogosultsag ?? '—' }}
      </template>
      <template #item.actions="{ item }">
        <v-btn size="x-small" variant="tonal" color="primary" class="me-2" @click="openEdit(item)">Szerkeszt</v-btn>
        <v-btn size="x-small" variant="tonal" color="error" @click="removeItem(item)">Töröl</v-btn>
      </template>
    </v-data-table>

    <v-dialog v-model="dialog" max-width="600">
      <v-card>
        <v-card-title class="text-h6">{{ editing ? 'Ügyfél szerkesztése' : 'Új ügyfél' }}</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" sm="6"><v-text-field v-model="form.nev" label="Név" required /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.felhasznalonev" label="Felhasználónév" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.email" type="email" label="Email" required /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.telefonszam" label="Telefonszám" /></v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.jogosultsag"
                :items="roleOptions"
                item-title="title"
                item-value="value"
                label="Jogosultság"
              />
              <div class="text-error text-caption mt-1">A jogosultság módosítása csak rendszergazda feladata.</div>
            </v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.password" type="password" label="Jelszó (opcionális)" /></v-col>
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
