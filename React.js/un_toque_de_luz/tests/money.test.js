import assert from 'node:assert/strict'
import test from 'node:test'
import { formatMoney, fromCents, splitPayment, toCents } from '../src/utils/money.js'

test('formats pesos with es-AR separators', () => {
  assert.equal(formatMoney('12500.00'), '$ 12.500,00')
  assert.equal(formatMoney('0.5'), '$ 0,50')
  assert.equal(formatMoney('1234567.89'), '$ 1.234.567,89')
  assert.equal(formatMoney('-300.10'), '-$ 300,10')
})

test('parses amounts in cents without float errors', () => {
  assert.equal(toCents('0.10') + toCents('0.20'), toCents('0.30'))
  assert.equal(toCents('33.33') * 3, toCents('99.99'))
  assert.equal(toCents('12,5'), 1250)
  assert.equal(toCents('10.123'), null)
  assert.equal(toCents('abc'), null)
  assert.equal(fromCents(1999), '19.99')
})

test('previews how a payment is split between the charge and credit', () => {
  assert.deepEqual(splitPayment({ amountCents: 150000, chargeBalanceCents: 100000 }), { toCharge: 100000, toOthers: 0, credit: 50000 })
  assert.deepEqual(
    splitPayment({ amountCents: 110000, chargeBalanceCents: 50000, spill: true, otherBalancesCents: [30000, 20000] }),
    { toCharge: 50000, toOthers: 50000, credit: 10000 }
  )
})
