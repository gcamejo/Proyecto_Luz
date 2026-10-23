import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import { clearAuthSession, loadAuthSession, saveAuthSession } from '../src/store/authSession.js'

const createStorage = () => {
  const values = new Map()
  return {
    getItem: key => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: key => values.delete(key),
  }
}

test('persists and restores an authenticated session', () => {
  const storage = createStorage()
  const user = { id: 12, nombre: 'Ana', perfil: 'user' }

  saveAuthSession('token-value', user, storage)

  assert.deepEqual(loadAuthSession(storage), { token: 'token-value', user })
})

test('removes stored auth when logging out', () => {
  const storage = createStorage()
  saveAuthSession('token-value', { id: 12 }, storage)

  clearAuthSession(storage)

  assert.equal(loadAuthSession(storage), null)
})

test('rejects and clears invalid stored session data', () => {
  const storage = createStorage()
  storage.setItem('un-toque-de-luz.auth-session', '{invalid')

  assert.equal(loadAuthSession(storage), null)
  assert.equal(storage.getItem('un-toque-de-luz.auth-session'), null)
})

test('login uses the bearer endpoint directly and propagates request failures', () => {
  const loginSource = readFileSync(new URL('../src/store/Slice/loginUsuario/loginUsuario.js', import.meta.url), 'utf8')

  assert.doesNotMatch(loginSource, /csrf-cookie/)
  assert.match(loginSource, /await axios\.post\(apiEndPoint, values\)/)
  assert.match(loginSource, /catch \(error\)[\s\S]*dispatch\(setError\(error\.message\)\)/)
})
