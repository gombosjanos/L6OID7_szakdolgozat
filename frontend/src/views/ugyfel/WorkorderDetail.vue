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
          <v-card-title class="text-subtitle-1">Alap adatok</v-card-title>
          <v-divider />
          <v-card-text>
            <div class="mb-2"><strong>Azonosító:</strong> {{ displayId }}</div>
            <div class="mb-2"><strong>Gép:</strong> {{ gepLabel(gepFromRow(detail)) }}</div>
            <div class="mb-2"><strong>Létrehozva:</strong> {{ fmtDate(detail.letrehozva || detail.created_at) }}</div>
            <div class="mb-2"><strong>Állapot:</strong> {{ displayStatus(detail.statusz) }}</div>
            <div class="mb-2" v-if="detail.hibaleiras"><strong>Hiba leírás:</strong> {{ detail.hibaleiras }}</div>
            <div class="mb-2" v-if="detail.megjegyzes"><strong>Megjegyzés:</strong> {{ detail.megjegyzes }}</div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="7">
        <v-card>
          <v-card-title class="d-flex align-center">
            <span class="text-subtitle-1">Árajánlat</span>
            <v-spacer />
            <v-chip v-if="offer && offer.statusz" size="small" :color="offerStatusColor(offer.statusz)" variant="tonal">{{ displayOfferStatus(offer.statusz) }}</v-chip>
          </v-card-title>
          <v-divider />
          <v-card-text>
            <div v-if="!offer || !Array.isArray(offer.tetelek) || offer.tetelek.length===0" class="text-medium-emphasis">Még nincs elérhető árajánlat.</div>
            <template v-else>
              <v-table density="compact">
                <thead>
                  <tr>
                    <th>Megnevezés</th>
                    <th class="text-right">Db</th>
                    <th class="text-right">Nettó</th>
                    <th class="text-right">ÁFA%</th>
                    <th class="text-right">Bruttó</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(t,i) in offer.tetelek" :key="i">
                    <td>{{ t.megnevezes }}</td>
                    <td class="text-right">{{ t.mennyiseg }}</td>
                    <td class="text-right">{{ fmtCurrency(t.netto_egyseg_ar) }}</td>
                    <td class="text-right">{{ t.afa_kulcs ?? 27 }}</td>
                    <td class="text-right">{{ fmtCurrency(lineBrutto(t)) }}</td>
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

              <div class="mt-3 d-flex align-center" v-if="offer.statusz === 'elkuldve'">
                <v-btn color="success" variant="tonal" prepend-icon="mdi-check" class="me-2" :loading="processing" :disabled="processing" @click="accept">Elfogadom</v-btn>
                <v-btn color="error" variant="tonal" prepend-icon="mdi-close" :loading="processing" :disabled="processing" @click="reject">Elutasítom</v-btn>
              </div>
            </template>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
  
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
    'uj':'Új',
    'folyamatban':'Folyamatban',
    'ajanlat_elkuldve':'Árajánlat elküldve',
    'alkatreszre_var':'Alkatrészre vár',
    'javitas_kesz':'Javítás kész',
    'atadva_lezarva':'Átadva/Lezárva',
    'ajanlat_elfogadva':'Árajánlat elfogadva',
    'ajanlat_elutasitva':'Árajánlat elutasítva',
  }
  const k=(s||'').toLowerCase()
  return map[k] || s || '-'
}

function gepFromRow(row){ if(row?.gep) return row.gep; if(row?.gep_adatok) return row.gep_adatok; const gyarto=row?.gyarto||row?.gep_gyarto; const tipusnev=row?.tipusnev||row?.gep_tipus; const g_cikkszam=row?.g_cikkszam||row?.cikkszam||row?.gep_cikkszam; if(gyarto||tipusnev||g_cikkszam) return {gyarto,tipusnev,g_cikkszam}; return null }
function gepLabel(gep){ if(!gep) return '-'; try{ const gyarto = gep.gyarto || gep.gep_gyarto || ''; const tipus = gep.tipusnev || gep.gep_tipus || ''; const cikkszam = gep.g_cikkszam || gep.cikkszam || gep.gep_cikkszam || ''; const head = [gyarto, tipus].filter(Boolean).join(' '); return [head, cikkszam].filter(Boolean).join(' • ') }catch{ return '-' } }

const totalNetto = Vue.computed(()=>{
  const rows = offer.value?.tetelek || []
  return rows.reduce((s,t)=> s + (Number(t.netto_egyseg_ar||0) * Number(t.mennyiseg||0)), 0)
})
const totalBrutto = Vue.computed(()=>{
  const rows = offer.value?.tetelek || []
  return rows.reduce((s,t)=> s + lineBrutto(t), 0)
})
function lineBrutto(t){
  const n = Number(t?.netto_egyseg_ar||0)
  const a = Number(t?.afa_kulcs ?? 27)
  const b = n * (1 + a/100)
  return b * Number(t?.mennyiseg||0)
}

async function load(){
  try{
    errorMsg.value = ''
    const d = await api.get(`/munkalapok/${id.value}`)
    detail.value = d?.data || {}
    const a = await api.get(`/munkalapok/${id.value}/ajanlat`)
    offer.value = a?.data || null
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
    snack('Árajánlat elutasítva','warning')
    await load()
  }catch(e){ errorMsg.value = e?.response?.data?.message || 'Elutasítás nem sikerült.' }
  finally{ processing.value=false }
}

function goBack(){ try{ if(window.history && window.history.length>1) router.back(); else router.push('/ugyfel') } catch{} }

Vue.onMounted(load)
</script>

<style scoped>
.text-right{ text-align:right }
</style>

