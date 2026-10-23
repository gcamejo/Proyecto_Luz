import assert from 'node:assert/strict'
import test from 'node:test'
import { readFileSync } from 'node:fs'
import { cycleRegistrationFlow, prioritizeCycle } from '../src/utils/cycleRegistrationFlow.js'
import { safeInternalRedirect } from '../src/utils/safeInternalRedirect.js'

const origin = 'https://untoquedeluz.com.ar'

test('cycle registration preserves the selected cycle and internal return route', () => {
  const flow = cycleRegistrationFlow(42)
  const register = new URL(flow.registerPath, origin)

  assert.equal(flow.bookingPath, '/reservarCiclo?ciclo_id=42')
  assert.equal(register.pathname, '/cargarFicha')
  assert.equal(safeInternalRedirect(register.searchParams.get('redirect'), '/', origin), flow.bookingPath)
  assert.equal(safeInternalRedirect(register.searchParams.get('volver'), '/', origin), '/comenzar-yoga')
})

test('selected cycle is displayed first without discarding other cycles', () => {
  const cycles = [{ id: 1 }, { id: 42 }, { id: 3 }]
  assert.deepEqual(prioritizeCycle(cycles, '42').map(cycle => cycle.id), [42, 1, 3])
  assert.equal(prioritizeCycle(cycles, 'missing'), cycles)
})

test('registration, return, and login components consume validated internal redirects', () => {
  const frontendRoot = new URL('../src/', import.meta.url)
  const registration = readFileSync(new URL('pages/FichaCreate.jsx', frontendRoot), 'utf8')
  const login = readFileSync(new URL('pages/Login.jsx', frontendRoot), 'utf8')
  const yoga = readFileSync(new URL('pages/YogaProgram.jsx', frontendRoot), 'utf8')

  assert.match(registration, /safeInternalRedirect\(registrationParams\.get\('redirect'\)\)/)
  assert.match(registration, /safeInternalRedirect\(registrationParams\.get\('volver'\)\)/)
  assert.match(registration, /returnPath}>Volver<\/Link>/)
  assert.match(login, /safeInternalRedirect\(new URLSearchParams\(location\.search\)\.get\('redirect'\)\)/)
  assert.match(yoga, /cycleRegistrationFlow\(cycle\.id\)/)
})