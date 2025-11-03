export function statusColor(s){
  const k=(s||'').toLowerCase()
  switch(k){
    case 'új': case 'uj': return 'grey'
    case 'folyamatban': return 'blue'
    case 'árajánlat elküldve': case 'ajánlat elküldve': case 'ajanlat_elkuldve': return 'purple'
    case 'alkatrészre vár': case 'alkatreszre_var': return 'orange'
    case 'javítás kész': case 'javitas_kesz': return 'green'
    case 'árajánlat elfogadva': case 'ajánlat elfogadva': case 'ajanlat_elfogadva': return 'indigo'
    case 'átadva/lezárva': case 'atadva': case 'lezarva': case 'atadva_lezarva': return 'teal'
    case 'árajánlat elutasítva': case 'ajánlat elutasítva': case 'ajanlat_elutasitva': return 'red'
    default: return 'grey'
  }
}

export function displayStatus(s){
  const key=(s||'').toLowerCase()
  const map={
    'új':'Új','uj':'Új',
    'folyamatban':'Folyamatban',
    'árajánlat elküldve':'Árajánlat elküldve','ajánlat elküldve':'Árajánlat elküldve','ajanlat_elkuldve':'Árajánlat elküldve',
    'alkatrészre vár':'Alkatrészre vár','alkatreszre_var':'Alkatrészre vár',
    'javítás kész':'Javítás kész','javitas_kesz':'Javítás kész',
    'árajánlat elutasítva':'Árajánlat elutasítva','ajánlat elutasítva':'Árajánlat elutasítva','ajanlat_elutasitva':'Árajánlat elutasítva',
    'átadva/lezárva':'Átadva/Lezárva','atadva':'Átadva/Lezárva','lezarva':'Átadva/Lezárva','atadva_lezarva':'Átadva/Lezárva',
    'árajánlat elfogadva':'Árajánlat elfogadva','ajánlat elfogadva':'Árajánlat elfogadva','ajanlat_elfogadva':'Árajánlat elfogadva',
  }
  return map[key] || s || '-'
}

export function fmtDate(v){ 
  try { 
    return v ? new Date(v).toLocaleString('hu-HU') : '' 
  } catch { 
    return v || '' 
  } 
}

export function gepLabel(g){ 
  if(!g) return '-'
  const parts=[g.gyarto,g.tipusnev,g.g_cikkszam].filter(Boolean)
  return parts.join(' - ') 
}

export function gepFromRow(row){ 
  if(row?.gep) return row.gep
  if(row?.gep_adatok) return row.gep_adatok
  const gyarto=row?.gyarto||row?.gep_gyarto
  const tipusnev=row?.tipusnev||row?.gep_tipus
  const g_cikkszam=row?.g_cikkszam||row?.cikkszam||row?.gep_cikkszam
  if(gyarto||tipusnev||g_cikkszam) return {gyarto,tipusnev,g_cikkszam}
  return null 
}

export function getId(row){ 
  return row?.id ?? row?.ID ?? row?.azonosito ?? row?.munkalap_id ?? null 
}

export function getUgyfelNev(row){ 
  return row?.ugyfel?.nev ?? row?.ugyfel_nev ?? row?.nev ?? row?.ugyfel_adatok?.nev ?? '-' 
}

export function getStatus(row){ 
  return (row?.statusz ?? row?.status ?? row?.allapot ?? '').toString() 
}
