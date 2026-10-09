import assert from 'node:assert/strict'
import test from 'node:test'
import { safeInternalRedirect } from '../src/utils/safeInternalRedirect.js'

const origin = 'http://localhost:5173'

test('accepts an internal route with query and hash', () => {
  assert.equal(safeInternalRedirect('/reservarCiclo?origen=yoga#horarios', '/', origin), '/reservarCiclo?origen=yoga#horarios')
})

test('rejects external and protocol-relative destinations', () => {
  assert.equal(safeInternalRedirect('https://evil.example/path', '/', origin), '/')
  assert.equal(safeInternalRedirect('//evil.example/path', '/', origin), '/')
  assert.equal(safeInternalRedirect('/\\\\evil.example/path', '/', origin), '/')
})
