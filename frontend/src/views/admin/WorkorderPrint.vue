<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../../api.js'

const route = useRoute()
const router = useRouter()

const id = computed(() => route.params.id)
const loading = ref(false)
const error = ref('')
const detail = ref(null)

function fmtDate(v) {
  try {
    return v ? new Date(v).toLocaleString('hu-HU') : ''
  } catch {
    return v || ''
  }
}

function getUgyfel(row) {
  return row?.Ugyfel || row?.ugyfel || null
}

function getGep(row) {
  if (!row) return null
  if (row.gep) return row.gep
  if (row.gep_adatok) return row.gep_adatok
  const gyarto = row.gyarto || row.gep_gyarto
  const tipusnev = row.tipusnev || row.gep_tipus
  const g_cikkszam = row.g_cikkszam || row.cikkszam || row.gep_cikkszam
  if (gyarto || tipusnev || g_cikkszam) return { gyarto, tipusnev, g_cikkszam }
  return null
}

function gepLabel(gep) {
  try {
    if (!gep) return '-'
    const parts = [
      gep.gyarto,
      gep.tipusnev,
      gep.g_cikkszam,
      gep.megnevezes,
      gep.nev,
      gep.tipus
    ].filter(Boolean)
    return parts.join(' - ') || '-'
  } catch {
    return '-'
  }
}

function displayId(row) {
  return row?.azonosito || row?.munkalapsorsz || row?.ID || row?.id || ''
}

async function load() {
  if (!id.value) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/munkalapok/${id.value}`)
    detail.value = data || null
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      'Nem sikerült betölteni a munkalapot.'
  } finally {
    loading.value = false
  }
}

function triggerPrint() {
  try {
    window.print()
  } catch {
    // ignore
  }
}

function goBack() {
  try {
    router.back()
  } catch {
    // ignore
  }
}

onMounted(async () => {
  await load()
})
</script>

<template>
  <div class="print-root">
    <div class="print-toolbar no-print">
      <button type="button" class="btn" @click="goBack">
        Vissza
      </button>
      <button type="button" class="btn primary" @click="triggerPrint">
        Nyomtatás
      </button>
    </div>

    <div v-if="error" class="print-error no-print">
      {{ error }}
    </div>

    <div v-if="detail" class="page">
      <div
        v-for="copyLabel in ['Szerviz példány', 'Ügyfél példány']"
        :key="copyLabel"
        class="sheet"
      >
        <header class="sheet-header">
          <div class="sheet-header-left">
            <div class="company-name">Kertigép Szerviz</div>
            <div class="company-sub">Munkafelvételi adatlap</div>
          </div>
          <div class="sheet-header-right">
            <div><strong>Munkalap azonosító:</strong> {{ displayId(detail) }}</div>
            <div><strong>Dátum:</strong> {{ fmtDate(detail.letrehozva || detail.created_at) }}</div>
            <div><strong>Példány:</strong> {{ copyLabel }}</div>
          </div>
        </header>

        <section class="section">
          <h2 class="section-title">Ügyfél adatai</h2>
          <div class="grid grid-2">
            <div class="field">
              <label>Név</label>
              <div class="value">
                {{ getUgyfel(detail)?.nev || '-' }}
              </div>
            </div>
            <div class="field">
              <label>Telefonszám</label>
              <div class="value">
                {{ getUgyfel(detail)?.telefonszam || '-' }}
              </div>
            </div>
            <div class="field">
              <label>E-mail</label>
              <div class="value">
                {{ getUgyfel(detail)?.email || '-' }}
              </div>
            </div>
            <div class="field">
              <label>Felhasználónév</label>
              <div class="value">
                {{ getUgyfel(detail)?.felhasznalonev || '-' }}
              </div>
            </div>
          </div>
        </section>

        <section class="section">
          <h2 class="section-title">Gép adatai</h2>
          <div class="grid grid-2">
            <div class="field">
              <label>Gép</label>
              <div class="value">
                {{ gepLabel(getGep(detail)) }}
              </div>
            </div>
            <div class="field">
              <label>Ügyfél azonosító</label>
              <div class="value">
                {{ getUgyfel(detail)?.id || getUgyfel(detail)?.ID || '-' }}
              </div>
            </div>
          </div>
        </section>

        <section class="section">
          <h2 class="section-title">Hiba leírása</h2>
          <div class="multiline-box">
            {{ detail.hibaleiras || '-' }}
          </div>
        </section>

        <section class="section">
          <h2 class="section-title">Megjegyzés</h2>
          <div class="multiline-box">
            {{ detail.megjegyzes || '-' }}
          </div>
        </section>

        <section class="section signatures">
          <div class="signature-box">
            <div class="line" />
            <div class="label">Ügyfél aláírása</div>
          </div>
          <div class="signature-box">
            <div class="line" />
            <div class="label">Szerviz átvevő</div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.print-root {
  padding: 16px;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #111;
}

.print-toolbar {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-bottom: 12px;
}

.btn {
  border: 1px solid #ccc;
  background: #f5f5f5;
  padding: 6px 12px;
  font-size: 0.9rem;
  border-radius: 4px;
  cursor: pointer;
}

.btn.primary {
  background: #1976d2;
  border-color: #1976d2;
  color: white;
}

.print-error {
  color: #b00020;
  margin-bottom: 12px;
}

.page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sheet {
  padding: 16px 20px;
  border: 1px solid #ccc;
  border-radius: 4px;
  page-break-inside: avoid;
}

.sheet-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.company-name {
  font-size: 1.2rem;
  font-weight: 700;
}

.company-sub {
  font-size: 0.95rem;
  margin-top: 2px;
}

.sheet-header-right {
  font-size: 0.85rem;
  text-align: right;
}

.section {
  margin-top: 10px;
}

.section-title {
  font-size: 0.95rem;
  font-weight: 600;
  margin-bottom: 6px;
}

.grid {
  display: grid;
  gap: 8px 16px;
}

.grid-2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.field label {
  display: block;
  font-size: 0.75rem;
  color: #555;
  margin-bottom: 2px;
}

.field .value {
  min-height: 20px;
  border-bottom: 1px solid #aaa;
  font-size: 0.9rem;
  padding-bottom: 2px;
}

.multiline-box {
  min-height: 64px;
  border: 1px solid #aaa;
  padding: 6px 8px;
  font-size: 0.9rem;
  white-space: pre-wrap;
}

.signatures {
  display: flex;
  justify-content: space-between;
  gap: 32px;
  margin-top: 18px;
}

.signature-box {
  flex: 1;
  text-align: center;
  font-size: 0.85rem;
}

.signature-box .line {
  border-bottom: 1px solid #000;
  margin: 24px 20px 6px;
}

.signature-box .label {
  color: #555;
}

@media print {
  .no-print {
    display: none !important;
  }

  .print-root {
    padding: 0;
  }

  .sheet {
    border: none;
    border-radius: 0;
    margin-bottom: 12mm;
    page-break-inside: avoid;
  }
}
</style>
