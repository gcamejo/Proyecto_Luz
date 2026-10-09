import assert from 'node:assert/strict'
import test from 'node:test'
import { createWhatsAppHref, createWhatsAppMessage, getWhatsAppLinkProps } from '../src/utils/whatsappLink.js'

test('builds a wa.me URL with the message encoded', () => {
  const message = 'Hola, quisiera consultar por Yoga & horarios?'

  assert.equal(
    createWhatsAppHref('5491123456789', message),
    'https://wa.me/5491123456789?text=Hola%2C%20quisiera%20consultar%20por%20Yoga%20%26%20horarios%3F'
  )
})

test('does not provide link props when the phone number is missing or invalid', () => {
  assert.equal(getWhatsAppLinkProps(undefined, 'Consulta'), null)
  assert.equal(getWhatsAppLinkProps('', 'Consulta'), null)
  assert.equal(getWhatsAppLinkProps('+5491123456789', 'Consulta'), null)
})

test('opens safely in a new tab', () => {
  assert.deepEqual(getWhatsAppLinkProps('5491123456789', 'Consulta'), {
    href: 'https://wa.me/5491123456789?text=Consulta',
    target: '_blank',
    rel: 'noopener noreferrer',
  })
})

test('includes the activity name in the request message', () => {
  assert.equal(
    createWhatsAppMessage('Cuencoterapia'),
    'Hola, quisiera consultar por la actividad Cuencoterapia.'
  )
})
