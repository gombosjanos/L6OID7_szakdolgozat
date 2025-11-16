<template>
  <v-container fluid class="pa-4">
    <v-toolbar density="comfortable" color="white" elevation="0" class="mb-3">
      <v-btn variant="elevated" color="primary" prepend-icon="mdi-arrow-left" @click="goBack">Vissza</v-btn>
      <v-toolbar-title>Munkalap #{{ displayId }}</v-toolbar-title>
      <v-spacer />
      <v-chip size="small" :color="statusColor(detail.statusz)" variant="flat">{{ displayStatus(detail.statusz) }}</v-chip>
    </v-toolbar>

    <v-alert v-if="errorMsg" type="error" variant="tonal" class="mb-3">{{ errorMsg }}</v-alert>

    <v-row>
      <v-col cols="12" md="5">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1 font-weight-bold">Alap adatok</v-card-title>
          <v-divider />
          <v-card-text>
            <div class="mb-2"><strong>Azonosí­tó:</strong> {{ displayId }}</div>
            <div class="mb-2"><strong>Gép:</strong> {{ gepLabel(gepFromRow(detail)) }}</div>
            <div class="mb-2"><strong>Létrehozva:</strong> {{ fmtDate(detail.letrehozva || detail.created_at) }}</div>
            <div class="mb-2"><strong>Állapot:</strong> {{ displayStatus(detail.statusz) }}</div>
            <div class="mb-2" v-if="detail.hibaleiras"><strong>Hiba leírás:</strong> {{ detail.hibaleiras }}</div>
            <div class="mb-2" v-if="detail.megjegyzes"><strong>Megjegyzés:</strong> {{ detail.megjegyzes }}</div>
          </v-card-text>
        </v-card>

        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1 font-weight-bold">Csatolt képek</v-card-title>
          <v-divider />
          <v-card-text>
            <div v-if="!hasImages" class="text-medium-emphasis">Nem érkezett még kép ehhez a munkalaphoz.</div>
            <v-row v-else class="ga-2">
              <v-col v-for="img in images" :key="img._key" cols="12" sm="6">
                <v-card variant="outlined" class="image-card">
                  <v-img
                    :src="thumbSrc(img)"
                    :alt="img.eredeti_nev || 'Munkalap kép'"
                    aspect-ratio="4/3"
                    class="rounded cursor-pointer"
                    :lazy-src="thumbPlaceholder"
                    cover
                  />
                  <v-card-text class="py-2 px-3 image-meta">
                    <div class="text-body-2 text-truncate" :title="img.eredeti_nev">{{ img.eredeti_nev || 'Feltöltött kép' }}</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ fmtDate(img.letrehozva || img.created_at) }}
                      <span v-if="img.meret"> â€˘ {{ formatFileSize(img.meret) }}</span>
                    </div>
                  </v-card-text>
                  <v-card-actions class="py-2 px-3">
                    <v-btn variant="text" size="small" color="primary" prepend-icon="mdi-magnify-plus" @click="openImage(img)">
                      Nagyítás
                    </v-btn>
                  </v-card-actions>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="7">
        <v-card>
          <v-card-title class="d-flex align-center">
            <span class="text-subtitle-1 font-weight-bold">Árajánlat</span>
            <v-spacer />
            <v-chip v-if="offerStatusUI" size="small" :color="offerStatusColor(offerStatusUI)" variant="tonal">{{ displayOfferStatus(offerStatusUI) }}</v-chip>
          </v-card-title>
          <v-divider />
          <v-card-text>
            <v-alert v-if="offerNote" type="warning" variant="elevated" class="mb-3">
              <div class="text-subtitle-1 mb-2 font-weight-bold">Megjegyzés</div>
              <div class="prewrap">{{ offerNote }}</div>
            </v-alert>
            <div v-if="offerRows.length===0" class="text-medium-emphasis">Még nincs elérhető árajánlat.</div>
            <template v-else>
              <v-table density="compact">
                <thead>
                  <tr>
                    <th>Megnevezés</th>
                    <th class="text-right">Db</th>
                    <th class="text-right">Egységár (nettó)</th>
                    <th class="text-right">ÁFA%</th>
                    <th class="text-right">Összesen (bruttó)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(t,i) in offerRows" :key="i">
                    <td>{{ t.megnevezes }}</td>
                    <td class="text-right">{{ t.mennyiseg }}</td>
                    <td class="text-right">{{ fmtCurrency(unitNetto(t)) }}</td>
                    <td class="text-right">{{ t.afa_kulcs ?? 27 }}</td>
                    <td class="text-right">{{ fmtCurrency(lineTotalBrutto(t)) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="2" class="text-right">Összesen:</th>
                    <th class="text-right">{{ fmtCurrency(totalNetto) }}</th>
                    <th></th>
                    <th class="text-right">{{ fmtCurrency(totalBrutto) }}</th>
                  </tr>
                </tfoot>
              </v-table>

              <div class="mt-3 d-flex align-center" v-if="canDecide">
                <v-btn color="success" variant="tonal" prepend-icon="mdi-check" class="me-2" :loading="processing" :disabled="processing" @click="accept">Elfogadom</v-btn>
                <v-btn color="error" variant="tonal" prepend-icon="mdi-close" :loading="processing" :disabled="processing" @click="reject">ElutasĂ­tom</v-btn>
              </div>
            </template>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
    <v-dialog v-model="lightboxOpen" max-width="98vw" scrim="rgba(0,0,0,0.8)">
    <v-card class="pa-0 lb-card" color="black">
  <button class="lb-close" @click="lightboxOpen=false" aria-label="Bezárás">
    <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" role="img" aria-hidden="true">
      <path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
    </svg>
  </button>
  <div class="lb-counter">{{ (currentIndex+1) }} / {{ images.length }}</div>
  <button class="lb-nav lb-left" @click="prevImage" :disabled="images.length<=1" aria-label="Előző">
    <svg viewBox="0 0 24 24" width="34" height="34" fill="currentColor" role="img" aria-hidden="true">
      <path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
    </svg>
  </button>
  <button class="lb-nav lb-right" @click="nextImage" :disabled="images.length<=1" aria-label="Következő">
    <svg viewBox="0 0 24 24" width="34" height="34" fill="currentColor" role="img" aria-hidden="true">
      <path d="m8.59 16.59 4.58-4.59-4.58-4.59L10 6l6 6-6 6z"/>
    </svg>
  </button>
  <div class="lb-body">
    <img :src="lightboxUrl" alt="Kép" />
  </div>
</v-card>
  </v-dialog>
  <v-snackbar v-model="snackbar" :timeout="3000" :color="snackbarColor" location="top right">{{ snackbarText }}</v-snackbar>
</template>

<script setup>
import * as Vue from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../../api.js'

const route = useRoute()
const router = useRouter()
const id = Vue.computed(()=> route.params.id)

const detail = Vue.ref({})
const offer = Vue.ref(null)
const errorMsg = Vue.ref('')
const processing = Vue.ref(false)

const images = Vue.ref([])
const lightboxOpen = Vue.ref(false)
const lightboxUrl = Vue.ref('')
const currentIndex = Vue.ref(0)
const imgUrlCache = Vue.ref({})
const thumbPlaceholder = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="12"><rect width="100%" height="100%" fill="#f3f3f3"/></svg>'

function thumbSrc(img){
  if(!img) return thumbPlaceholder
  return imgUrlCache.value[img.id] || thumbPlaceholder
}

async function getObjectUrl(img){
  if(!img?.url || !img?.id) return ''
  const cached = imgUrlCache.value[img.id]
  if (cached) return cached
  const headers = {}
  try{ const tk = localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('AUTH_TOKEN'); if(tk) headers['Authorization'] = `Bearer ${tk}` }catch{}
  const res = await fetch(img.url, { method: 'GET', headers, credentials: 'include' })
  if(!res.ok){ throw new Error(`HTTP ${res.status}`) }
  const blob = await res.blob()
  const objUrl = URL.createObjectURL(blob)
  imgUrlCache.value = { ...imgUrlCache.value, [img.id]: objUrl }
  return objUrl
}

async function openImage(img){
  if(!img) return
  try{
    const idx = images.value.findIndex(i => (i.id ?? i.ID) === (img.id ?? img.ID))
    currentIndex.value = idx >= 0 ? idx : 0
    lightboxUrl.value = await getObjectUrl(img)
    lightboxOpen.value = true
  }catch(e){ snack('Kep megnyitasa nem sikerult.', 'error') }
}

Vue.watch(images, (list)=>{
  try{ (Array.isArray(list)?list:[]).forEach(it => { getObjectUrl(it).catch(()=>{}) }) }catch{}
}, { immediate: true })

async function showAt(index){
  if (images.value.length === 0) return
  const len = images.value.length
  const i = ((index % len) + len) % len
  currentIndex.value = i
  const img = images.value[i]
  try{ lightboxUrl.value = await getObjectUrl(img) }catch(e){}
}
function prevImage(){ showAt(currentIndex.value - 1) }
function nextImage(){ showAt(currentIndex.value + 1) }

const snackbar = Vue.ref(false)
const snackbarText = Vue.ref('')
const snackbarColor = Vue.ref('success')
function snack(t,c='success'){ snackbarText.value=t; snackbarColor.value=c; snackbar.value=true }

const displayId = Vue.computed(()=> detail.value?.azonosito || id.value)

function fmtDate(v){ try { return v ? new Date(v).toLocaleString('hu-HU') : '' } catch { return v || '' } }
function fmtCurrency(v){ if (v==null||v==='') return ''; return new Intl.NumberFormat('hu-HU',{style:'currency',currency:'HUF',maximumFractionDigits:0}).format(Number(v)) }

function statusColor(s){
  const k=(s||'').toLowerCase()
  switch(k){
    case 'uj': return 'grey'
    case 'folyamatban': return 'blue'
    case 'ajanlat_elkuldve': return 'purple'
    case 'javitas_kesz': return 'green'
    case 'atadva_lezarva': return 'teal'
    case 'ajanlat_elfogadva': return 'indigo'
    case 'ajanlat_elutasitva': return 'red'
    default: return 'grey'
  }
}
function displayStatus(s){
  const map={
    'uj':'új',
    'folyamatban':'Folyamatban',
    'ajanlat_elkuldve':'Árajánlat elküldve',
    'alkatreszre_var':'Alkatrészre vár',
    'javitas_kesz':'JavĂ­tás kész',
    'atadva_lezarva':'Átadva/Lezárva',
    'ajanlat_elfogadva':'Árajánlat elfogadva',
    'ajanlat_elutasitva':'Árajánlat elutasĂ­tva',
  }
  const k=(s||'').toLowerCase()
  return map[k] || s || '-'
}
function normalizeStatus(s){ try{ return (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'') }catch{ return (s||'').toString().toLowerCase() } }

function gepFromRow(row){ if(row?.gep) return row.gep; if(row?.gep_adatok) return row.gep_adatok; const gyarto=row?.gyarto||row?.gep_gyarto; const tipusnev=row?.tipusnev||row?.gep_tipus; const g_cikkszam=row?.g_cikkszam||row?.cikkszam||row?.gep_cikkszam; if(gyarto||tipusnev||g_cikkszam) return {gyarto,tipusnev,g_cikkszam}; return null }
function gepLabel(gep){ if(!gep) return '-'; try{ const gyarto = gep.gyarto || gep.gep_gyarto || ''; const tipus = gep.tipusnev || gep.gep_tipus || ''; const cikkszam = gep.g_cikkszam || gep.cikkszam || gep.gep_cikkszam || ''; const head = [gyarto, tipus].filter(Boolean).join(' '); return [head, cikkszam].filter(Boolean).join(' â€˘ ') }catch{ return '-' } }

function normalizeImageEntry(entry, index = 0){
  if(!entry) return null
  const id = entry.id ?? entry.ID ?? entry.kep_id ?? index
  const relative = entry.fajlnev || entry.path || ''
  const url = entry.url || (relative ? `/storage/${relative.replace(/^\/+/, '')}` : '')
  return {
    ...entry,
    id,
    url,
    _key: id ?? `${relative || 'kep'}-${index}-${entry.letrehozva ?? Date.now()}`,
  }
}

function normalizeImages(list){
  return (Array.isArray(list) ? list : [])
    .map((entry, idx) => normalizeImageEntry(entry, idx))
    .filter(Boolean)
}

const hasImages = Vue.computed(()=> images.value.length > 0)

function formatFileSize(size){
  const bytes = Number(size)
  if(!bytes || Number.isNaN(bytes)) return ''
  const units = ['B','KB','MB','GB']
  let value = bytes
  let unitIndex = 0
  while(value >= 1024 && unitIndex < units.length - 1){
    value /= 1024
    unitIndex += 1
  }
  const formatted = unitIndex === 0 ? Math.round(value) : value.toFixed(1)
  return `${formatted} ${units[unitIndex]}`
}

async function oldOpenImage(img){
  if(!img?.url) return
  try{
    const headers = {}
    try{ const tk = localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('AUTH_TOKEN'); if(tk) headers['Authorization'] = `Bearer ${tk}` }catch{}
    const res = await fetch(img.url, { method: 'GET', headers, credentials: 'include' })
    if(!res.ok){ throw new Error(`HTTP ${res.status}`) }
    const blob = await res.blob()
    const objUrl = URL.createObjectURL(blob)
    const w = window.open(objUrl, '_blank')
    setTimeout(()=> URL.revokeObjectURL(objUrl), 60000)
    if(!w){ snack('A kép megnyitása blokkolva lett a böngésző által.', 'warning') }
  }catch(e){
    snack('Kép megnyitása nem sikerült.', 'error')
  }
}

const offerRows = Vue.computed(()=>{
  const raw = offer.value?.tetelek
  if (Array.isArray(raw)) return raw
  if (raw && typeof raw === 'object') return Object.values(raw)
  return []
})

function normKey(k){ try{ return (k||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'') }catch{ return (k||'').toString().toLowerCase() } }
function toNum(v){ if (v==null) return 0; if (typeof v==='number') return isFinite(v)?v:0; const s=String(v).replace(/\s+/g,'').replace(/\./g,'').replace(',', '.'); const n=parseFloat(s); return isFinite(n)?n:0 }
function pickVal(obj, candidates){ try{ const map={}; for(const k of Object.keys(obj||{})){ map[normKey(k)] = obj[k]; } for(const name of candidates){ const v = map[normKey(name)]; if (v!=null && v!=='') return v } }catch{} return undefined }
function qtyOf(t){ const v = pickVal(t,['mennyiseg','mennyiség','db','darab']); const n = toNum(v); return n>0?n:0 }
function vatOf(t){ const v = pickVal(t,['afa_kulcs','áfa_kulcs','afa','áfa']); const n = toNum(v); return n>0?n:27 }
function unitNetto(t){
  const n1 = pickVal(t,['netto_egyseg_ar','nettó_egyseg_ar','nettoegysegar','netto','nettó','egyseg_ar','egységár','egysegar'])
  if (n1!=null && n1!=='') { const n=toNum(n1); if(n>0) return n }
  const ub = pickVal(t,['brutto_egyseg_ar','bruttó_egyseg_ar','bruttoegysegar'])
  if (ub!=null && ub!=='') { const a=vatOf(t); const b=toNum(ub); if (b>0) return b/(1+a/100) }
  const qty = qtyOf(t); const sum = pickVal(t,['osszeg','összeg','osszeg_brutto','brutto_osszeg','brutto','bruttó']);
  if (qty>0 && sum!=null && sum!=='') { const a=vatOf(t); const bruttoEgys = toNum(sum)/qty; return bruttoEgys/(1+a/100) }
  return 0
}
function unitBrutto(t){
  const ub = pickVal(t,['brutto_egyseg_ar','bruttó_egyseg_ar','bruttoegysegar'])
  if (ub!=null && ub!=='') { const b=toNum(ub); if (b>0) return b }
  const n = unitNetto(t); const a = vatOf(t); return n*(1+a/100)
}
const totalNetto = Vue.computed(()=> offerRows.value.reduce((s,t)=> s + unitNetto(t)*qtyOf(t), 0))
const totalBrutto = Vue.computed(()=> offerRows.value.reduce((s,t)=> s + lineTotalBrutto(t), 0))
function lineTotalBrutto(t){ return unitBrutto(t) * Number(t.mennyiseg||0) }

function coarseStatus(s){
  const n = normalizeStatus(s)
  if (n.includes('elkuldve')) return 'elkuldve'
  if (n.includes('elfogad')) return 'elfogadva'
  if (n.includes('elutasit')) return 'elutasitva'
  return ''
}
const offerStatusUI = Vue.computed(()=>{
  const os = coarseStatus(offer.value?.statusz)
  if (os) return os
  return coarseStatus(detail.value?.statusz)
})
const canDecide = Vue.computed(()=> offerStatusUI.value === 'elkuldve')

const offerNote = Vue.computed(()=> (offer.value?.megjegyzes || '').toString().trim())

function offerStatusColor(s){
  const k=(s||'').toString().toLowerCase()
  switch(k){
    case 'elkuldve': return 'orange'
    case 'elfogadva': return 'green'
    case 'elutasitva': return 'red'
    default: return 'grey'
  }
}
function displayOfferStatus(s){
  const k=(s||'').toString().toLowerCase()
  const map={ elkuldve:'Elfogadásra vár', elfogadva:'Elfogadva', elutasitva:'ElutasĂ­tva' }
  return map[k] || '-'
}

async function load(){
  try{
    errorMsg.value = ''
    const d = await api.get(`/munkalapok/${id.value}`)
    detail.value = d?.data || {}
    images.value = normalizeImages(detail.value?.kepek); try{ const first = images.value.slice(0,8); for (const it of first){ /* fire-and-forget */ getObjectUrl(it).catch(()=>{}) } }catch{}
    const a = await api.get(`/munkalapok/${id.value}/ajanlat`)
    offer.value = a?.data || null
    try {
      const img = await api.get(`/munkalapok/${id.value}/kepek`)
      images.value = normalizeImages(img?.data); try{ const first = images.value.slice(0,8); for (const it of first){ getObjectUrl(it).catch(()=>{}) } }catch{}
    } catch {}
  }catch(e){
    errorMsg.value = e?.response?.data?.message || e?.message || 'Betöltési hiba.'
  }
}

async function accept(){
  try{
    processing.value = true
    await api.post(`/munkalapok/${id.value}/ajanlat/accept`)
    snack('Árajánlat elfogadva')
    await load()
  }catch(e){ errorMsg.value = e?.response?.data?.message || 'Elfogadás nem sikerült.' }
  finally{ processing.value=false }
}
async function reject(){
  try{
    processing.value = true
    await api.post(`/munkalapok/${id.value}/ajanlat/reject`)
    snack('Árajánlat elutasí­tva','warning')
    await load()
  }catch(e){ errorMsg.value = e?.response?.data?.message || 'Elutasítás nem sikerült.' }
  finally{ processing.value=false }
}

function goBack(){ try{ if(window.history && window.history.length>1) router.back(); else router.push('/Ugyfel') } catch{} }

Vue.onMounted(load)
</script>

<style scoped>
.text-right{ text-align:right }
.prewrap{ white-space: pre-wrap }
.image-card{ overflow:hidden; border-radius:12px; }
.image-card .v-img{ background:#f5f5f5; }
.image-card .image-meta{ background:rgba(0,0,0,0.02); }
.image-card .text-truncate{ max-width:100%; }
</style>






<style>
.image-card .v-card-actions{ display:flex; align-items:center; justify-content:space-between; gap:8px; }
.cursor-pointer{ cursor:pointer; }
</style>




<style>
.lightbox-card{ position: relative; background:#000; }
.lightbox-card .lightbox-btn{ color:#fff !important; background:rgba(255,255,255,.16) !important; backdrop-filter: blur(2px); }
.lightbox-card .v-card-actions{ color:#fff; }
.lightbox-card .v-card-actions:first-of-type{ position:absolute; top:0; left:0; right:0; background: linear-gradient(to bottom, rgba(0,0,0,.75), rgba(0,0,0,0)); z-index:2; }
.lightbox-card .v-card-actions:last-of-type{ position:absolute; bottom:0; left:0; right:0; background: linear-gradient(to top, rgba(0,0,0,.75), rgba(0,0,0,0)); z-index:2; }
.lightbox-card .lightbox-body{ max-height:80vh; height:80vh; background:#000; }
.lightbox-card .lightbox-body img{ max-width:100%; max-height:80vh; object-fit:contain; }
.lightbox-card .lightbox-counter{ color:#fff; font-weight:600; }
</style>

<style scoped>
.lb-card{ position:relative; background:#000; }
.lb-body{ height:80vh; max-height:80vh; display:flex; align-items:center; justify-content:center; background:#000; }
.lb-body img{ max-width:100%; max-height:80vh; object-fit:contain; }
.lb-close{ position:absolute; top:10px; right:10px; width:40px; height:40px; border:none; border-radius:10px; background:rgba(0,0,0,.55); color:#fff; display:grid; place-items:center; cursor:pointer; z-index:3; }
.lb-close:hover{ background:rgba(0,0,0,.75); }
.lb-counter{ position:absolute; top:14px; left:14px; z-index:3; color:#fff; font-weight:600; padding:2px 8px; border-radius:8px; background:rgba(0,0,0,.4); backdrop-filter: blur(2px); }
.lb-nav{ position:absolute; top:50%; transform:translateY(-50%); z-index:3; width:56px; height:56px; border:none; border-radius:50%; background:rgba(0,0,0,.55); color:#fff; display:grid; place-items:center; cursor:pointer; }
.lb-nav:hover{ background:rgba(0,0,0,.75); }
.lb-left{ left:14px; }
.lb-right{ right:14px; }
.lb-nav[disabled]{ opacity:.35; cursor:default; }
</style>


