import assert from 'node:assert/strict'
import test from 'node:test'
import { resolveSeo } from '../src/utils/seo.js'

const origin = 'https://example.test'

test('public pages are indexable and mention the target topics', () => {
  const home = resolveSeo('/', origin)
  assert.equal(home.robots, 'index, follow')
  for (const keyword of ['yoga', 'constelaciones', 'cuencos', 'respiraciones']) {
    assert.match(`${home.title} ${home.description}`.toLowerCase(), new RegExp(keyword))
  }
})

test('the /yoga alias points its canonical URL to /comenzar-yoga', () => {
  assert.equal(resolveSeo('/yoga', origin).canonical, 'https://example.test/comenzar-yoga')
})

test('private and unknown routes are not indexed', () => {
  assert.equal(resolveSeo('/adminReservas', origin).robots, 'noindex, nofollow')
  assert.equal(resolveSeo('/login', origin).robots, 'noindex, nofollow')
})
