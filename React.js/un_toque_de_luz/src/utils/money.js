// Los importes viajan como texto decimal ("12500.50"); toda la aritmética se hace en centavos enteros.
export const toCents = value => {
  const text = String(value ?? '').trim().replace(',', '.')
  if (!/^-?\d+(\.\d{1,2})?$/.test(text)) return null

  const negative = text.startsWith('-')
  const [whole, fraction = ''] = text.replace('-', '').split('.')
  const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'))

  return negative ? -cents : cents
}

export const fromCents = cents => {
  const absolute = Math.abs(cents)
  return `${cents < 0 ? '-' : ''}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`
}

export const formatMoney = value => {
  const cents = typeof value === 'number' ? value : toCents(value)
  if (cents === null) return '$ 0,00'

  const absolute = Math.abs(cents)
  const whole = String(Math.floor(absolute / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `${cents < 0 ? '-' : ''}$ ${whole},${String(absolute % 100).padStart(2, '0')}`
}

export const splitPayment = ({ amountCents, chargeBalanceCents = 0, spill = false, otherBalancesCents = [] }) => {
  const toCharge = Math.min(amountCents, chargeBalanceCents)
  let remaining = amountCents - toCharge
  let toOthers = 0

  if (spill) {
    for (const balance of otherBalancesCents) {
      const take = Math.min(remaining, balance)
      toOthers += take
      remaining -= take
    }
  }

  return { toCharge, toOthers, credit: remaining }
}
