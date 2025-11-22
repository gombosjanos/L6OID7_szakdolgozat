<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../../api.js'
import { ensureEuropeanPhone } from '../../utils/phone.js'

const items = ref([])
const search = ref('')
const loading = ref(false)
const error = ref('')

const dialog = ref(false)
const editing = ref(null)
const form = ref({
  nev: '',
  felhasznalonev: '',
  email: '',
  telefonszam: '',
  jogosultsag: 'Ugyfel',
  password: '',
})
const usernamePattern = /^[A-Za-z0-9._-]+$/

const roleLabels = {
  Ugyfel: 'Ügyfel',
  szerelo: 'Szerelő',
  admin: 'Admin',
}

const roleOptions = [
  { title: roleLabels.Ugyfel, value: 'Ugyfel' },
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
  form.value = {
    nev: '',
    felhasznalonev: '',
    email: '',
    telefonszam: '',
    jogosultsag: 'Ugyfel',
    password: '',
  }
  dialog.value = true
}

const openEdit = row => {
  editing.value = row
  form.value = {
    nev: row.nev || '',
    felhasznalonev: row.felhasznalonev || '',
    email: row.email || '',
    telefonszam: row.telefonszam || '',
    jogosultsag: row.jogosultsag || 'Ugyfel',
    password: '',
  }
  dialog.value = true
}

const close = () => {
  dialog.value = false
}

const save = async () => {
  error.value = ''
  try {
    const trimmedName = form.value.nev?.trim() || ''
    const trimmedUsername = form.value.felhasznalonev?.trim() || ''
    const trimmedEmail = form.value.email?.trim() || ''

    if (!trimmedName || trimmedName.length < 2) {
      error.value = 'A név megadása kötelező, legalább 2 karakterrel.'
      return
    }

    if (!trimmedUsername) {
      error.value = 'A felhasználónév megadása kötelező.'
      return
    }
    if (trimmedUsername.length < 4) {
      error.value = 'A felhasználónév legalább 4 karakter legyen.'
      return
    }
    if (!usernamePattern.test(trimmedUsername)) {
      error.value =
        'A felhasználónév csak betűt, számot, pontot, kötőjelet vagy aláhúzást tartalmazhat.'
      return
    }

    const payload = {
      nev: trimmedName,
      felhasznalonev: trimmedUsername,
      email: trimmedEmail,
      jogosultsag: form.value.jogosultsag,
    }

    const phone = ensureEuropeanPhone(form.value.telefonszam)
    if (!phone) {
      error.value =
        'Érvényes magyar vagy európai telefonszámot adj meg (pl. +36205012465).'
      return
    }

    payload.telefonszam = phone

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

const removeItem = async row => {
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
    <v-row class="align-center mb-3" no-gutters>
      <v-col cols="12" md="6">
        <div class="text-h6 font-weight-bold">Ügyfelek</div>
      </v-col>
      <v-col cols="12" md="6">
        <div class="header-controls">
          <v-text-field
            v-model="search"
            @input="onSearch"
            label="Keresés (név / email / felhasználónév)"
            prepend-inner-icon="mdi-magnify"
            class="header-search"
            density="comfortable"
            variant="outlined"
            clearable
          />
          <v-btn
            color="primary"
            class="header-create"
            @click="openAdd"
          >
            <v-icon icon="mdi-plus" class="me-1" />
            Új ügyfél
          </v-btn>
        </div>
      </v-col>
    </v-row>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3">
      {{ error }}
    </v-alert>

    <v-data-table :headers="headers" :items="filtered" :loading="loading" item-key="ID">
      <template #item.jogosultsag="{ item }">
        {{ roleLabels[item.raw?.jogosultsag] ?? item.raw?.jogosultsag ?? '-' }}
      </template>
      <template #item.actions="{ item }">
        <v-btn
          size="x-small"
          variant="tonal"
          color="primary"
          class="me-2"
          @click="openEdit(item)"
        >
          Szerkesztés
        </v-btn>
        <v-btn
          size="x-small"
          variant="tonal"
          color="error"
          @click="removeItem(item)"
        >
          Törlés
        </v-btn>
      </template>
    </v-data-table>

    <v-dialog v-model="dialog" max-width="600">
      <v-card>
        <v-card-title class="text-h6">
          {{ editing ? 'Ügyfél szerkesztése' : 'Új ügyfél' }}
        </v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.nev" label="Név" required />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.felhasznalonev"
                label="Felhasználónév"
                required
                hint="Engedélyezett: betű, szám, pont, kötőjel, aláhúzás"
                persistent-hint
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.email" type="email" label="Email" required />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.telefonszam"
                label="Telefonszám"
                required
                autocomplete="tel"
                placeholder="+36205012465"
                hint="Példa formátum: +36205012465"
                persistent-hint
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.jogosultsag"
                :items="roleOptions"
                item-title="title"
                item-value="value"
                label="Jogosultság"
              />
              <div class="text-error text-caption mt-1">
                A jogosultság módosítása az adminisztrátor jogköréhez tartozik.
              </div>
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.password"
                type="password"
                label="Jelszó (opcionális)"
              />
            </v-col>
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

<style scoped>
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
.header-create {
  white-space: nowrap;
}
@media (max-width: 960px) {
  .header-controls {
    justify-content: flex-start;
  }
  .header-search,
  .header-create {
    flex: 1 1 100%;
    max-width: 100% !important;
  }
}
</style>

