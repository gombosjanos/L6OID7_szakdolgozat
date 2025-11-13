
<template>
  <v-container fluid class="pa-4 has-bottom-bar">
    <v-toolbar density="comfortable" color="white" elevation="0" class="mb-3 detail-toolbar">
      <v-btn variant="elevated" color="primary" prepend-icon="mdi-arrow-left" @click="goBack">Vissza</v-btn>
      <v-toolbar-title>Munkalap #{{ displayId }}</v-toolbar-title>
      <v-spacer />
      <v-chip size="small" :color="statusColorX(statusModel)" class="mr-2" variant="flat">{{ displayStatusX(statusModel) }}</v-chip>
      <v-menu offset-y>
        <template #activator="{ props }">
          <v-btn v-bind="props" variant="flat" class="ml-2 actions-btn" prepend-icon="mdi-dots-vertical">Műveletek</v-btn>
        </template>
        <v-list class="menu-dark">
          <v-list-item @click="saveWorkorder"><v-list-item-title>Státusz mentése</v-list-item-title></v-list-item>
          <v-list-item @click="saveOffer"><v-list-item-title>Árajánlat mentése</v-list-item-title></v-list-item>
          <v-list-item :disabled="!canSendOffer || sendingOffer" @click="sendOffer"><v-list-item-title>Árajánlat küldése</v-list-item-title></v-list-item>
          <v-list-item @click="printOffer"><v-list-item-title>Nyomtatás</v-list-item-title></v-list-item>
        </v-list>
      </v-menu>
    </v-toolbar>

    <v-alert v-if="errorMsg" type="error" variant="tonal" class="mb-3">{{ errorMsg }}</v-alert>
    <v-snackbar v-model="snackbar" :timeout="3000" :color="snackbarColor" location="top right">
      {{ snackbarText }}
      <template #actions><v-btn variant="text" @click="snackbar = false">OK</v-btn></template>
    </v-snackbar>

    <v-row>
      <v-col cols="12" lg="6">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Alap adatok</v-card-title>
          <v-divider />
          <v-card-text>
            <div class="mb-2"><strong>Ügyfél:</strong> {{ getUgyfelNev(detail) }}</div>
            <div class="mb-2"><strong>E-mail:</strong> {{ getUgyfelEmail(detail) }}</div>
            <div class="mb-2"><strong>Telefon:</strong> {{ getUgyfelTelefon(detail) }}</div>
            <div class="mb-2"><strong>Felhasználónév:</strong> {{ getUgyfelFnev(detail) }}</div>
            <div class="mb-2"><strong>Gép:</strong> {{ gepLabel(gepFromRow(detail)) }}</div>
            <div class="mb-2"><strong>Létrehozva:</strong> {{ fmtDate(detail.letrehozva || detail.created_at) }}</div>
            <div class="mb-2"><strong>Azonosító:</strong> {{ displayId }}</div>
          </v-card-text>
        </v-card>

        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">Státusz</v-card-title>
          <v-divider />
          <v-card-text>
            <v-select v-model="statusModel" :items="statusItems" item-title="title" item-value="value" label="Státusz" variant="outlined" density="compact" style="max-width: 260px; margin-left: 12px;" @update:modelValue="() => saveWorkorder()" />
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1 d-flex align-center"><span>Napló</span><v-spacer /></v-card-title>
          <v-divider />
          <v-card-text>
            <v-list density="compact">
              <v-list-item v-for="n in naplo" :key="n.id || n.ID">
                <div class="d-flex align-start w-100">
                  <div class="flex-1">
                    <div class="note-text" v-html="formatNoteHtml(n)"></div>
                    <div class="text-caption text-medium-emphasis">{{ fmtDate(n.letrehozva || n.created_at) }}</div>
                  </div>
                  <div class="d-flex ga-1 ml-2">
                    <v-btn size="x-small" variant="text" color="primary" class="mr-1" @click.stop="editNote(n)">Szerkesztés</v-btn>
                    <v-btn size="x-small" variant="text" color="error" @click.stop="deleteNote(n)">Törlés</v-btn>
                  </div>
                </div>
              </v-list-item>
            </v-list>
            <v-textarea v-model="note" rows="2" auto-grow label="Megjegyzés hozzáadása" variant="outlined" density="comfortable" />
            <div class="d-flex justify-end mt-2"><v-btn size="small" variant="tonal" color="primary" @click="addNote">Hozzáadás</v-btn></div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="6">
        <v-card class="offer-card">
          <v-card-title class="d-flex align-center">
            <span class="text-subtitle-1">Árajánlat</span>
            <v-spacer />
            <v-autocomplete v-model="pickerSelected" :items="pickerItems" :loading="pickerLoading" :search="pickerSearch" @update:search="onPickerSearch" @focus="ensurePartsLoaded" @update:modelValue="onPickerSelect" item-title="label" item-value="value" return-object label="Tétel hozzáadása" clear-on-select hide-details variant="outlined" density="comfortable" style="max-width: 420px" :disabled="!canEditOffer" />
          </v-card-title>
          <v-card-text>
            <v-text-field v-model="offerUzenet" label="Üzenet az árajánlatban (opcionális)" variant="outlined" density="comfortable" class="mb-3" :disabled="!canEditOffer" />
            <div class="offer-scroll">
              <v-table density="compact">
                <thead>
                  <tr>
                    <th rowspan="2" style="min-width:420px; width:420px">Megnevezés</th>
                    <th class="text-center" style="min-width:220px; width:220px">Db</th>
                    <th class="text-center" style="min-width:220px; width:220px">ÁFA</th>
                    <th class="text-center" rowspan="2" style="min-width:120px; width:120px">Törlés</th>
                  </tr>
                  <tr>
                    <th class="text-center">Nettó</th>
                    <th class="text-center">Bruttó</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(t,i) in tetelek" :key="'t'+i">
                    <td>
                      <v-text-field
                        v-model="t.megnevezes"
                        hide-details
                        variant="outlined"
                        density="compact"
                        class="name-input"
                        :disabled="!canEditOffer || nameLocked(t)"
                      />
                    </td>
                    <td>
                      <div class="stack-col">
                        <div class="stack-row">
                          <span class="stack-label">db</span>
                          <v-text-field v-model.number="t.db" type="number" min="1" step="1" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.db,4,8)" :disabled="!canEditOffer" />
                        </div>
                        <div class="stack-row">
                          <span class="stack-label">nettó</span>
                          <v-text-field v-model.number="t.netto" type="number" step="0.01" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.netto,8,24)" :disabled="!canEditOffer" />
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="stack-col">
                        <div class="stack-row">
                          <span class="stack-label">ÁFA%</span>
                          <v-text-field v-model.number="t.afa_kulcs" type="number" step="1" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.afa_kulcs,3,6)" :disabled="!canEditOffer" />
                        </div>
                        <div class="stack-row">
                          <span class="stack-label">bruttó</span>
                          <v-text-field v-model.number="t.brutto" type="number" step="0.01" hide-details variant="outlined" density="compact" class="num-input" :style="numStyle(t.brutto,8,24)" :disabled="!canEditOffer" />
                        </div>
                      </div>
                    </td>
                    <td class="text-right align-top">
                      <v-btn size="small" variant="outlined" color="error" prepend-icon="mdi-delete" @click="removeTetel(i)" :disabled="!canEditOffer">Törlés</v-btn>
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </div>
            <v-divider class="my-3" />
            <div class="d-flex justify-end ga-4">
              <div class="text-right"><div class="text-caption">Összesen Nettó</div><div class="text-subtitle-2">{{ fmtCurrency(totalNetto) }}</div></div>
              <div class="text-right"><div class="text-caption">Összesen Bruttó</div><div class="text-subtitle-2">{{ fmtCurrency(totalBrutto) }}</div></div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1 d-flex align-center">
            <span>Képek</span><v-spacer />
            <template v-if="canManageImages">
              <v-btn color="primary" variant="tonal" size="small" prepend-icon="mdi-upload" :loading="uploadingImage" :disabled="uploadingImage" @click="triggerImageSelect">Feltöltés</v-btn>
            </template>
            <input ref="fileInput" type="file" accept="image/*" multiple class="d-none" @change="handleImageSelection" />
          </v-card-title>
          <v-divider />
          <v-card-text>
            <div v-if="imagesLoading" class="text-medium-emphasis d-flex align-center ga-2"><v-progress-circular indeterminate color="primary" size="20" /><span>Képek betöltése...</span></div>
            <div v-else-if="!hasImages" class="text-medium-emphasis">Még nincs feltöltött kép ehhez a munkalaphoz.</div>
            <v-row v-else class="images-grid ga-3">
              <v-col v-for="img in images" :key="img._key" cols="12" sm="6" md="6" lg="4" xl="3">
                <v-card variant="outlined" class="image-card">
                  <v-img :src="thumbSrc(img)" :alt="img.eredeti_nev || 'Munkalap kép'" aspect-ratio="4/3" class="rounded cursor-pointer" @click="openImageModal(img)" :lazy-src='thumbPlaceholder' cover />
                  <v-card-text class="py-2 px-3 image-meta"><div class="text-caption text-medium-emphasis">{{ fmtDate(img.letrehozva || img.created_at) }}</div></v-card-text>
                  <v-card-actions class="py-2 px-3"><v-btn variant="text" size="small" color="primary" prepend-icon="mdi-magnify-plus" @click="openImageModal(img)">Nagyítás</v-btn><v-spacer /><v-btn v-if="canManageImages" variant="text" size="small" color="error" prepend-icon="mdi-delete" @click="deleteImage(img)">Törlés</v-btn></v-card-actions>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="lightboxOpen" fullscreen transition="dialog-bottom-transition">
      <v-card>
        <v-toolbar density="comfortable"><v-btn icon="mdi-close" @click="lightboxOpen = false" /><v-spacer /><v-btn icon="mdi-chevron-left" @click="prevImage" /><v-btn icon="mdi-chevron-right" @click="nextImage" /></v-toolbar>
        <v-card-text class="d-flex align-center justify-center"><img :src="lightboxUrl" alt="Kép" style="max-width:100%; max-height: calc(100vh - 64px);" /></v-card-text>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import * as Vue from 'vue'
import { useRoute, useRouter } from 'vue-router'

async function request(path, { method = 'GET', body } = {}) {
  const url = `/api${path}`
  const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' }
  try { const tk = localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('AUTH_TOKEN'); if (tk) headers['Authorization'] = `Bearer ${tk}` } catch {}
  const res = await fetch(url + (method === 'GET' && body ? `?${new URLSearchParams(body)}` : ''), { method, headers, body: method === 'GET' ? undefined : JSON.stringify(body ?? {}), credentials: 'include' })
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${await res.text().catch(() => '')}`)
  const ct = res.headers.get('content-type') || ''
  return ct.includes('application/json') ? res.json() : null
}

const route = useRoute()
const router = useRouter()
const id = Vue.computed(() => route.params.id)
  const detail = Vue.ref({})
  const displayId = Vue.computed(()=> (detail.value && (detail.value.azonosito || detail.value.identifier)) || id.value)

  const snackbar = Vue.ref(false)
  const snackbarText = Vue.ref('')
  const snackbarColor = Vue.ref('success')
  function setSnack(text, color = 'success'){ snackbarText.value = text; snackbarColor.value = color; snackbar.value = true }

  const errorMsg = Vue.ref('')
  const naplo = Vue.ref([])
  const note = Vue.ref('')

  // Role-based helpers
  const isClient = typeof window !== 'undefined'
  const canManageImages = Vue.computed(()=>{
    if(!isClient) return false
    try {
      const storage = window.localStorage
      const role = (storage.getItem('jogosultsag') || storage.getItem('role') || '').toLowerCase()
      return role === 'admin'
    } catch { return false }
  })

// Images / lightbox
const images = Vue.ref([])
const imagesLoading = Vue.ref(false)
const uploadingImage = Vue.ref(false)
const fileInput = Vue.ref(null)
const lightboxOpen = Vue.ref(false)
const lightboxUrl = Vue.ref('')
const currentIndex = Vue.ref(0)
const imgUrlCache = Vue.ref({})
const thumbPlaceholder = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="12"><rect width="100%" height="100%" fill="#f3f3f3"/></svg>'
function thumbSrc(img){ if (!img) return thumbPlaceholder; return imgUrlCache.value[img.id] || thumbPlaceholder }
async function getObjectUrl(img){ if (!img?.url || !img?.id) return ''; const cached = imgUrlCache.value[img.id]; if (cached) return cached; const headers = { }; try { const tk = localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('AUTH_TOKEN'); if (tk) headers['Authorization'] = `Bearer ${tk}` } catch {}; const res = await fetch(img.url, { method: 'GET', headers, credentials: 'include' }); if(!res.ok){ throw new Error(`HTTP ${res.status}`) } const blob = await res.blob(); const objUrl = URL.createObjectURL(blob); imgUrlCache.value = { ...imgUrlCache.value, [img.id]: objUrl }; return objUrl }
async function openImageModal(img){ if (!img) return; try{ const idx = images.value.findIndex(i => (i.id ?? i.ID) === (img.id ?? img.ID)); currentIndex.value = idx >= 0 ? idx : 0; lightboxUrl.value = await getObjectUrl(img); lightboxOpen.value = true }catch(e){ setSnack('Kep nagyitasa nem sikerult.', 'error') } }
async function showAt(index){ if (images.value.length === 0) return; const len = images.value.length; const i = ((index % len) + len) % len; currentIndex.value = i; const img = images.value[i]; try{ lightboxUrl.value = await getObjectUrl(img) }catch(e){} }
function prevImage(){ showAt(currentIndex.value - 1) }
function nextImage(){ showAt(currentIndex.value + 1) }
Vue.watch(images, (list)=>{ try{ (Array.isArray(list)?list:[]).forEach(it => { getObjectUrl(it).catch(()=>{}) }) }catch{} }, { immediate: true })

// Status / header
const statusItems = [
  { title: 'Új', value: 'uj' },
  { title: 'Folyamatban', value: 'folyamatban' },
  { title: 'Árajánlat elküldve', value: 'ajanlat_elkuldve' },
  { title: 'Alkatrészre vár', value: 'alkatreszre_var' },
  { title: 'Javítás kész', value: 'javitas_kesz' },
  { title: 'Árajánlat elutasítva', value: 'ajanlat_elutasitva' },
  { title: 'Átadva/Lezárva', value: 'atadva_lezarva' },
  { title: 'Árajánlat elfogadva', value: 'ajanlat_elfogadva' },
];
const originalStatus = Vue.ref('');
  function deaccent(input){ try { return String(input || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); } catch { return String(input || '').toLowerCase(); } }
  function normalizeStatus(s){ const n = deaccent(s); if (n.includes('uj')) return 'uj'; if (n.includes('folyamatban')) return 'folyamatban'; if (n.includes('elkuldve')) return 'ajanlat_elkuldve'; if (n.includes('var')) return 'alkatreszre_var'; if (n.includes('kesz')) return 'javitas_kesz'; if (n.includes('elutasit')) return 'ajanlat_elutasitva'; if (n.includes('atadva') || n.includes('lezarva')) return 'atadva_lezarva'; if (n.includes('elfogad')) return 'ajanlat_elfogadva'; return (s || '').toString().toLowerCase(); }
const statusModel = Vue.computed({ get(){ return normalizeStatus(detail.value.statusz || detail.value.status || detail.value.allapot) }, set(v){ detail.value.statusz = v } })
function statusColorX(s){ switch(normalizeStatus(s)){ case 'uj': return 'grey'; case 'folyamatban': return 'blue'; case 'ajanlat_elkuldve': return 'purple'; case 'alkatreszre_var': return 'orange'; case 'javitas_kesz': return 'green'; case 'ajanlat_elfogadva': return 'indigo'; case 'atadva_lezarva': return 'teal'; case 'ajanlat_elutasitva': return 'red'; default: return 'grey' } }
function displayStatusX(s){ const key=normalizeStatus(s); const map={ 'uj':'Új','folyamatban':'Folyamatban','ajanlat_elkuldve':'Árajánlat elküldve','alkatreszre_var':'Alkatrészre vár','javitas_kesz':'Javítás kész','ajanlat_elutasitva':'Árajánlat elutasítva','atadva_lezarva':'Átadva/Lezárva','ajanlat_elfogadva':'Árajánlat elfogadva', }; return map[key] || s || '-' }
async function saveWorkorder(){ if(!id.value) return; try{ const code = normalizeStatus(detail.value.statusz); await request(`/munkalapok/${id.value}`, { method:'PATCH', body:{ statusz: code, status: code, allapot: code } }); setSnack('Munkalap mentve') }catch(e){ errorMsg.value = e?.message || 'Mentesi hiba (munkalap).' } }

// Helpers / labels
function fmtDate(v){ try { return v ? new Date(v).toLocaleString('hu-HU') : '' } catch { return v || '' } }
function getUgyfelNev(row){ try{ return row?.Ugyfel?.nev ?? row?.Ugyfel_nev ?? row?.nev ?? '-' }catch{ return '-' } }
function getUgyfelEmail(row){ try{ return row?.Ugyfel?.email ?? row?.Ugyfel_email ?? row?.email ?? '-' }catch{ return '-' } }
function getUgyfelTelefon(row){ try{ return row?.Ugyfel?.telefonszam ?? row?.Ugyfel_telefon ?? row?.telefonszam ?? '-' }catch{ return '-' } }
function getUgyfelFnev(row){ try{ return row?.Ugyfel?.felhasznalonev ?? row?.Ugyfel_felhasznalonev ?? row?.felhasznalonev ?? '-' }catch{ return '-' } }
function gepFromRow(row){ try{ return row?.gep || row?.eszkoz || null }catch{ return null } }
function gepLabel(gep){ try{ if(!gep) return '-'; return gep.megnevezes || gep.nev || gep.tipus || '-' }catch{ return '-' } }

  // Images helpers
  function normalizeImageEntry(entry, index = 0){
    if(!entry) return null;
    const imageId = entry.id ?? entry.ID ?? entry.kep_id ?? null;
    const relative = entry.fajlnev || entry.path || '';
    let url = entry.url || '';
    if (!url) {
      if (imageId && id?.value) {
        url = `/api/munkalapok/${id.value}/kepek/${imageId}`;
      } else if (relative) {
        url = `/storage/${relative.replace(/^\/+/, '')}`;
      }
    }
    return { ...entry, id: imageId, url, _key: imageId ?? `${relative || 'image'}-${index}-${entry.letrehozva ?? Date.now()}` };
  }
function normalizeImageList(list){ return (Array.isArray(list) ? list : []).map((item, idx) => normalizeImageEntry(item, idx)).filter(Boolean) }
const hasImages = Vue.computed(()=> (images.value || []).length > 0)
async function fetchImages(silent = false){ if(!id.value) return; const showSpinner = !silent && images.value.length === 0; if(showSpinner) imagesLoading.value = true; try{ const data = await request(`/munkalapok/${id.value}/kepek`); images.value = normalizeImageList(data); try{ const first = images.value.slice(0,8); for (const it of first){ getObjectUrl(it).catch(()=>{}) } }catch{} }catch(e){ if(!silent){ setSnack(e?.message || 'Képek betöltése nem sikerült.', 'error') } }finally{ if(showSpinner) imagesLoading.value = false } }
function triggerImageSelect(){ try{ fileInput.value?.click() }catch{} }
async function handleImageSelection(event){ const files = Array.from(event?.target?.files || []); event.target.value = ''; if (!files.length || !id.value){ return } uploadingImage.value = true; try { const formData = new FormData(); for (const file of files){ formData.append('kepek[]', file) } const headers = { Accept: 'application/json' }; try { const token = localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('AUTH_TOKEN'); if (token) headers.Authorization = `Bearer ${token}` } catch {} const res = await fetch(`/api/munkalapok/${id.value}/kepek`, { method: 'POST', headers, body: formData, credentials: 'include' }); if (!res.ok){ const text = await res.text().catch(() => ''); throw new Error(text || 'Feltöltési hiba történt.') } const payload = await res.json().catch(() => ({})); const added = normalizeImageList(payload?.kepek ?? payload ?? []); images.value = [...added, ...images.value]; setSnack('Képek feltöltve') } catch (e) { setSnack(e?.message || 'A képfeltöltés nem sikerült.', 'error') } finally { uploadingImage.value = false } }
async function deleteImage(img){ if(!img || !id.value) return; const imageId = img.id ?? img.ID; if(!imageId) return; if(!confirm('Biztosan törlöd ezt a képet?')) return; try{ const headers = { Accept: 'application/json' }; try { const token = localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('AUTH_TOKEN'); if (token) headers.Authorization = `Bearer ${token}` } catch {} const res = await fetch(`/api/munkalapok/${id.value}/kepek/${imageId}`, { method: 'DELETE', headers, credentials: 'include' }); if (!res.ok){ const text = await res.text().catch(() => ''); throw new Error(text || 'Nem sikerült törölni a képet.') } images.value = images.value.filter(item => (item.id ?? item.ID) !== imageId); setSnack('Kép törölve', 'success') }catch(e){ setSnack(e?.message || 'Nem sikerült törölni a képet.', 'error') } }

// Notes helpers
function escapeHtml(str){ const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }; return String(str || '').replace(/[&<>"']/g, (m) => map[m]) }
function normalizeNewlines(str){ return String(str||'').replace(/\r\n|\r|\n/g, '\n') }
function formatNoteHtml(n){ const raw = n?.uzenet ?? n?.szoveg ?? n?.megjegyzes ?? ''; const safe = escapeHtml(normalizeNewlines(raw)).replace(/\n/g,'<br>'); return safe }
async function addNote(){ if(!id.value) return; const txt = (note.value || '').trim(); if(!txt) return; try{ await request(`/munkalapok/${id.value}/naplo`, { method:'POST', body:{ uzenet: txt } }); naplo.value.unshift({ id: Date.now(), uzenet: txt, letrehozva: new Date().toISOString() }); note.value = '' }catch(e){ setSnack(e?.message || 'Jegyzet mentése nem sikerült', 'error') } }
function noteId(n){ return n?.id ?? n?.ID }
async function editNote(n){ const idVal = noteId(n); if(!id.value || !idVal) return; const current = (n?.uzenet || n?.szoveg || n?.megjegyzes || "").toString(); const next = window.prompt("Jegyzet szerkesztése:", current); if(next==null) return; try{ await request(`/munkalapok/${id.value}/naplo/${idVal}`, { method:"PATCH", body:{ uzenet: next } }); n.uzenet = next }catch(e){ setSnack(e?.message||"Szerkesztés nem sikerült","error") } }
async function deleteNote(n){ const idVal = noteId(n); if(!id.value || !idVal) return; if(!window.confirm("Biztosan törlöd ezt a bejegyzést?")) return; try{ await request(`/munkalapok/${id.value}/naplo/${idVal}`, { method:"DELETE" }); naplo.value = naplo.value.filter(x=> noteId(x)!==idVal) }catch(e){ setSnack(e?.message||"Törlés nem sikerült","error") } }

// Offer / Arajanlat
const offer = Vue.ref(null)
const tetelek = Vue.ref([])
const pickerSelected = Vue.ref(null)
const pickerItems = Vue.ref([{ label:'Munkadij', value:'munkadij' },{ label:'Egyedi tetel', value:'egyedi' }])
const pickerLoading = Vue.ref(false)
const pickerSearch = Vue.ref('')
const offerUzenet = Vue.ref('')
const savingOffer = Vue.ref(false)
const sendingOffer = Vue.ref(false)
function nameLocked(t){
  try{
    const hasId = !!(t.alkatresz_id || t.AlkatreszID || t.alkatreszId)
    const typ = String(t.tipus || t.Tipus || '').toLowerCase()
    return hasId || typ.includes('alkatr')
  }catch{ return !!t.alkatresz_id }
}
  function numStyle(v,min=4,max=10){
    const w = Math.max(min, Math.min(max, String(v ?? '').length));
    return { width: `${w}ch` };
  }
function fmtCurrency(v){ if (v==null||v==='') return ''; return new Intl.NumberFormat('hu-HU',{style:'currency',currency:'HUF',maximumFractionDigits:0}).format(Number(v)) }
async function onPickerSearch(val){
  pickerSearch.value = val;
  try{
    pickerLoading.value = true;
    const res = await request('/alkatreszek', { method: 'GET', body: { q: (val || ''), limit: '20' } });
    const arr = Array.isArray(res) ? res : [];
    const partItems = arr.map(p => {
      const id = (p.ID ?? p.id);
      const name = (p.alkatresznev ?? p.alaktresznev ?? p.nev ?? p.megnevezes) || 'Alkatrész';
      const code = (p.a_cikkszam ?? p.cikkszam ?? p.code ?? '');
      const vat = Number(p.afa_kulcs ?? 27);
      const n = p.nettoar != null ? Number(p.nettoar) : (p.bruttoar != null ? Math.round(Number(p.bruttoar) / (1 + vat/100)) : 0);
      const b = p.bruttoar != null ? Number(p.bruttoar) : Math.round(n * (1 + vat/100));
      return {
        label: code ? `${name} (${code})` : name,
        value: { id, name, netto: n, brutto: b, afa_kulcs: vat }
      }
    });
    pickerItems.value = [
      { label:'Munkadíj', value:'munkadij' },
      { label:'Egyedi tétel', value:'egyedi' },
      ...partItems
    ]
  } finally {
    pickerLoading.value = false
  }
}
function ensurePartsLoaded(){
  try{
    if ((pickerItems.value || []).length <= 2 && !pickerLoading.value) {
      onPickerSearch('')
    }
  }catch{}
}
async function onPickerSelect(opt){
  if(!opt) return;
  if(typeof opt.value === 'string'){
    if(opt.value === 'munkadij'){
      tetelek.value.push({ tipus:'munkadij', megnevezes:'Munkadíj', db:1, netto:0, brutto:0, afa_kulcs:27 })
    } else if (opt.value === 'egyedi'){
      tetelek.value.push({ tipus:'egyedi', megnevezes:'Egyedi tétel', db:1, netto:0, brutto:0, afa_kulcs:27 })
    }
  } else if (opt.value && typeof opt.value === 'object'){
    const v = opt.value;
    const vat = Number(v.afa_kulcs ?? 27);
    const n = Number(v.netto ?? 0);
    const b = Number(v.brutto ?? Math.round(n * (1 + vat/100)));
    tetelek.value.push({ tipus:'alkatresz', alkatresz_id: v.id, megnevezes: (v.name || 'Alkatrész'), db: 1, netto: n, brutto: b, afa_kulcs: vat })
  }
  pickerSelected.value = null
}
function removeTetel(i){ if(i>=0) tetelek.value.splice(i,1) }
const totalNetto = Vue.computed(()=> (tetelek.value||[]).reduce((s,t)=> s + Number(t.netto||0) * Number(t.db||1), 0))
const totalBrutto = Vue.computed(()=> (tetelek.value||[]).reduce((s,t)=> s + Number(t.brutto||0) * Number(t.db||1), 0))
const canEditOffer = Vue.computed(()=> true)
  const canSendOffer = Vue.computed(()=> (tetelek.value||[]).length > 0)
  async function saveOffer(){
    if(!id.value) return;
    try{
      savingOffer.value = true;
      const payload = {
        tetelek: (tetelek.value||[]).map(t => ({
          id: t.id,
          tipus: t.tipus,
          alkatresz_id: t.alkatresz_id,
          megnevezes: t.megnevezes,
          mennyiseg: Number(t.db||1),
          netto_egyseg_ar: Number(t.netto||0),
          brutto_egyseg_ar: Number(t.brutto||0),
          afa_kulcs: Number(t.afa_kulcs||27),
        })),
        uzenet: offerUzenet.value,
      };
      await request(`/munkalapok/${id.value}/ajanlat`,{ method:'POST', body: payload });
      setSnack('Árajánlat mentve')
    }catch(e){
      errorMsg.value = e?.message || 'Mentési hiba (árajánlat).'
    } finally {
      savingOffer.value = false
    }
  }
  function mapAjanlatToTetelek(a){
    const rows = a?.tetelek ?? []
    const arr = Array.isArray(rows) ? rows : []
    return arr.map(r => {
      const meg = r.megnevezes || r.Megnevezes || r.nev || 'Tetel'
      const qty = Number(
        r.mennyiseg ?? r.Mennyiseg ?? r.db ?? r.DB ?? r.menny ?? r.mennyiseg_db ?? 1
      )
      const rawNetto = (
        r.netto_egyseg_ar ?? r.NettoEgysegAr ?? r.netto ?? r.Netto ?? r.egyseg_ar ?? r.EgysegAr ??
        (r.osszeg ?? r.Osszeg ? (Number(r.osszeg ?? r.Osszeg)/ (qty || 1)) : 0)
      )
      const afa = Number(r.afa_kulcs ?? r.AFA_kulcs ?? r.AfaKulcs ?? r.afa ?? r.AFA ?? 27)
      const netto = Number(rawNetto ?? 0)
      const brutto = Number(r.brutto_egyseg_ar ?? r.BruttoEgysegAr ?? r.brutto ?? r.Brutto ?? Math.round(netto * (1 + afa/100)))
      const alkatreszId = r.alkatresz_id ?? r.AlkatreszID ?? r.alkatreszId ?? null
      let tipus = r.tipus ?? r.Tipus ?? null
      if (typeof tipus === 'string'){
        const t = tipus.toLowerCase()
        if (t.includes('alkatr')) tipus = 'alkatresz'
        else if (t.includes('munkad')) tipus = 'munkadij'
        else if (t.includes('egyedi')) tipus = 'egyedi'
      }
      if (!tipus){
        const nameNorm = (deaccent(meg) || '').toLowerCase()
        tipus = alkatreszId ? 'alkatresz' : (nameNorm.includes('munkadij') ? 'munkadij' : 'egyedi')
      }
      return { tipus, alkatresz_id: alkatreszId, megnevezes: meg, db: qty, netto, brutto, afa_kulcs: afa }
    })
  }
  async function sendOffer(){
    if(!id.value) return;
    try{
      if(!confirm('Biztosan elkuldi az arajanlatot az Ugyfelnek? A kuldes utan nem modosithato.')) return;
      sendingOffer.value = true;
      await saveOffer();
      await request(`/munkalapok/${id.value}/ajanlat`,{ method:'POST', body:{ statusz: 'elkuldve' } });
      setSnack('Árajánlat elküldve');
    }catch(e){
      errorMsg.value = e?.message || 'Küldési hiba (árajánlat).';
    } finally {
      sendingOffer.value = false;
    }
  }
function printOffer(){ try{ window.print() }catch{} }
Vue.watch(tetelek, (list)=>{ try{ (list||[]).forEach(t=>{ t.db=Number(t.db||1); t.netto=Number(t.netto||0); t.afa_kulcs=Number(t.afa_kulcs||27); t.brutto = Math.round(t.netto * (1 + t.afa_kulcs/100)); }) }catch{} }, { deep:true })

function goBack(){ try{ router.back() }catch{} }
async function loadAll(){
  try {
    const d = await request(`/munkalapok/${id.value}`);
    detail.value = d || {};
    const s = normalizeStatus(detail.value.statusz || detail.value.status || detail.value.allapot);
    statusModel.value = s;
    originalStatus.value = s;
    const n = await request(`/munkalapok/${id.value}/naplo`);
    naplo.value = Array.isArray(n) ? n : [];
    await fetchImages(true);
    try {
      const a = await request(`/munkalapok/${id.value}/ajanlat`);
      offer.value = a || null;
      tetelek.value = mapAjanlatToTetelek(a);
      offerUzenet.value = (a?.uzenet || a?.megjegyzes || '');
    } catch {}
  } catch (e) {
    errorMsg.value = e?.message || 'Betoltesi hiba.';
  }
}
Vue.onMounted(()=>{ loadAll() })
</script>

<style scoped>
.detail-toolbar{ gap: 8px; }
.has-bottom-bar{ min-height: 100vh; padding-bottom: 60px; overflow-y: auto; }
.cursor-pointer{ cursor:pointer; }
.note-text{ white-space: normal; line-height: 1.35; word-break: break-word; }
.image-card{ overflow:hidden; border-radius:12px; }
.image-card .v-img{ background:#f5f5f5; }
.image-card .image-meta{ background:rgba(0,0,0,0.02); min-height:40px; }
.image-card .v-card-actions{ background:rgba(0,0,0,0.01); display:flex; align-items:center; justify-content:space-between; gap:8px; }
.actions-btn{ background:#616161 !important; color:#fff !important; }
.menu-dark{ background:#616161 !important; color:#fff !important; }
.menu-dark :is(.v-list-item-title,.v-list-item){ color:#fff !important; }
.menu-dark .v-list-item:hover{ background: rgba(255,255,255,0.08) !important; }
/* Offer table scrolling + column widths */
.offer-scroll{ overflow-x: auto; }
.offer-scroll .v-table__wrapper{ overflow-x: auto; }
.offer-scroll table{ min-width: 1120px; }
/* Inputs width inside cells */
.name-input{ min-width: 380px; }
.num-input{ min-width: 110px; }
.num-input input{ text-align: right; }
/* Stacked rows + inline labels next to inputs */
.stack-col{ display:flex; flex-direction:column; gap:6px; }
.stack-row{ display:flex; align-items:center; gap:8px; }
.stack-label{ width: 54px; text-align:right; font-size:12px; color: rgba(0,0,0,0.6); text-transform: lowercase; }
@media (max-width: 600px){
  .images-grid{ flex-wrap: nowrap !important; overflow-x: auto; padding-bottom: 4px; margin-bottom: -4px; scroll-snap-type: x mandatory; }
  .images-grid > .v-col{ flex: 0 0 80% !important; max-width: 80% !important; scroll-snap-align: start; }
}
</style>
