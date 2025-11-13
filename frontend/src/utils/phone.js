export const canonicalizePhone = (value) => {
  const input = (value || '').trim()
  if (!input) return ''

  let digits = input.replace(/\D+/g, '')
  if (!digits) return ''

  if (digits.startsWith('0036')) {
    digits = `36${digits.slice(4)}`
  } else if (digits.startsWith('06')) {
    digits = `36${digits.slice(2)}`
  } else if (/^0(20|30|31|50|70|1)/.test(digits)) {
    digits = `36${digits.slice(1)}`
  } else if (digits.startsWith('00')) {
    digits = digits.slice(2)
  }

  return digits ? `+${digits}` : ''
}

export const ensureEuropeanPhone = (value) => {
  const canonical = canonicalizePhone(value)
  if (!canonical) return ''

  const digits = canonical.slice(1)
  if (digits.length < 8 || digits.length > 15) return ''

  const first = digits[0]
  if (!['3', '4', '5', '7'].includes(first)) return ''

  return canonical
}
