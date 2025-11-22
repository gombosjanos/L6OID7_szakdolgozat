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
const offer = ref(null)
const rows = ref([])

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

function displayId(row) {
  return row?.azonosito || row?.munkalapsorsz || row?.ID || row?.id || ''
}

function deaccent(input) {
  try {
    return String(input || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
  } catch {
    return String(input || '').toLowerCase()
  }
}

function mapAjanlatToRows(a) {
  if (!a) return []
  const list = Array.isArray(a.tetelek) ? a.tetelek : []
  return list.map(r => {
    const meg = r.megnevezes || r.Megnevezes || r.nev || 'Tétel'
    const qty = Number(
      r.mennyiseg ?? r.Mennyiseg ?? r.db ?? r.DB ?? r.menny ?? r.mennyiseg_db ?? 1
    )
    const rawNetto =
      r.netto_egyseg_ar ??
      r.NettoEgysegAr ??
      r.netto ??
      r.Netto ??
      r.egyseg_ar ??
      r.EgysegAr ??
      (r.osszeg || r.Osszeg ? Number(r.osszeg ?? r.Osszeg) / (qty || 1) : 0)
    const afa = Number(
      r.afa_kulcs ?? r.AFA_kulcs ?? r.AfaKulcs ?? r.afa ?? r.AFA ?? 27
    )
    const netto = Number(rawNetto ?? 0)
    const brutto = Number(
      r.brutto_egyseg_ar ??
        r.BruttoEgysegAr ??
        r.brutto ??
        r.Brutto ??
        Math.round(netto * (1 + afa / 100))
    )
    const alkatreszId =
      r.alkatresz_id ?? r.AlkatreszID ?? r.alkatreszId ?? null
    let tipus = r.tipus ?? r.Tipus ?? null
    if (typeof tipus === 'string') {
      const t = tipus.toLowerCase()
      if (t.includes('alkatr')) tipus = 'alkatresz'
      else if (t.includes('munkad')) tipus = 'munkadij'
      else if (t.includes('egyedi')) tipus = 'egyedi'
    }
    if (!tipus) {
      const nameNorm = (deaccent(meg) || '').toLowerCase()
      tipus = alkatreszId
        ? 'alkatresz'
        : nameNorm.includes('munkadij')
          ? 'munkadij'
          : 'egyedi'
    }
    return {
      tipus,
      megnevezes: meg,
      db: qty,
      netto,
      brutto,
      afa_kulcs: afa
    }
  })
}

function fmtFt(v) {
  const n = Number(v || 0)
  return n.toLocaleString('hu-HU', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  })
}

const totalNetto = computed(() =>
  rows.value.reduce((sum, r) => sum + Number(r.netto || 0) * Number(r.db || 1), 0)
)
const totalBrutto = computed(() =>
  rows.value.reduce(
    (sum, r) => sum + Number(r.brutto || 0) * Number(r.db || 1),
    0
  )
)

async function load() {
  if (!id.value) return
  loading.value = true
  error.value = ''
  try {
    const [workorderRes, offerRes] = await Promise.all([
      api.get(`/munkalapok/${id.value}`),
      api.get(`/munkalapok/${id.value}/ajanlat`)
    ])
    detail.value = workorderRes.data || null
    offer.value = offerRes.data || null
    rows.value = mapAjanlatToRows(offer.value)
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      'Nem sikerült betölteni az árajánlatot.'
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

    <div v-if="detail && offer" class="sheet">
      <header class="sheet-header">
        <div class="sheet-header-left">
          <div class="company-name">Kertigép Szerviz</div>
          <div class="company-sub">Árajánlat</div>
        </div>
        <div class="sheet-header-right">
          <div><strong>Munkalap azonosító:</strong> {{ displayId(detail) }}</div>
          <div><strong>Dátum:</strong> {{ fmtDate(detail.letrehozva || detail.created_at) }}</div>
          <div><strong>Ügyfél:</strong> {{ getUgyfel(detail)?.nev || '-' }}</div>
        </div>
      </header>

      <section class="section">
        <h2 class="section-title">Tételek</h2>
        <table class="offer-table">
          <thead>
            <tr>
              <th style="width: 8%">Típus</th>
              <th>Megnevezés</th>
              <th style="width: 8%">Menny.</th>
              <th style="width: 12%">Nettó egységár</th>
              <th style="width: 8%">ÁFA %</th>
              <th style="width: 12%">Bruttó egységár</th>
              <th style="width: 12%">Bruttó összeg</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, idx) in rows" :key="idx">
              <td>
                {{ r.tipus === 'munkadij' ? 'Munkadíj' : r.tipus === 'alkatresz' ? 'Alkatrész' : 'Egyedi' }}
              </td>
              <td>{{ r.megnevezes }}</td>
              <td class="num">{{ r.db }}</td>
              <td class="num">{{ fmtFt(r.netto) }} Ft</td>
              <td class="num">{{ r.afa_kulcs }}%</td>
              <td class="num">{{ fmtFt(r.brutto) }} Ft</td>
              <td class="num">
                {{ fmtFt((Number(r.brutto || 0) || 0) * (Number(r.db || 0) || 0)) }} Ft
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="7" class="empty">Nincs tétel az árajánlatban.</td>
            </tr>
          </tbody>
          <tfoot v-if="rows.length">
            <tr>
              <td colspan="3" />
              <td class="num label">Összesen nettó:</td>
              <td colspan="3" class="num">
                {{ fmtFt(totalNetto) }} Ft
              </td>
            </tr>
            <tr>
              <td colspan="3" />
              <td class="num label">Összesen bruttó:</td>
              <td colspan="3" class="num">
                {{ fmtFt(totalBrutto) }} Ft
              </td>
            </tr>
          </tfoot>
        </table>
      </section>

      <section class="section">
        <h2 class="section-title">Megjegyzés</h2>
        <div class="multiline-box">
          {{ offer.megjegyzes || offer.uzenet || '-' }}
        </div>
      </section>

      <section class="section signatures">
        <div class="signature-box">
          <div class="line" />
          <div class="label">Ügyfél jóváhagyása</div>
        </div>
        <div class="signature-box">
          <div class="line" />
          <div class="label">Szerviz képviselő</div>
        </div>
      </section>
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

.sheet {
  padding: 16px 20px;
  border: 1px solid #ccc;
  border-radius: 4px;
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

.offer-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.offer-table th,
.offer-table td {
  border: 1px solid #aaa;
  padding: 4px 6px;
}

.offer-table th {
  background: #f5f5f5;
  text-align: left;
}

.offer-table td.num {
  text-align: right;
}

.offer-table td.empty {
  text-align: center;
  padding: 10px 6px;
}

.offer-table tfoot td.label {
  font-weight: 600;
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
    page-break-inside: avoid;
  }
}
</style>
