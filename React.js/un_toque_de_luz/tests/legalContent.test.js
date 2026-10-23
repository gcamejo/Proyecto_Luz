import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import { findUnresolvedLegalMarkers, validateLegalConfiguration } from '../scripts/check-legal.mjs'
import { legalContent, POLICY_DRAFT, POLICY_UPDATED_AT, POLICY_VERSION } from '../src/content/legalContent.js'

test('legal marker checker detects completion and definition placeholders', () => {
  assert.deepEqual(findUnresolvedLegalMarkers('A [COMPLETAR DOMICILIO] y [DEFINIR EDAD]'), [
    '[COMPLETAR DOMICILIO]',
    '[DEFINIR EDAD]',
  ])
  assert.deepEqual(findUnresolvedLegalMarkers('Texto revisado sin marcas.'), [])
})

test('public legal pages, footer links, consent gate and registration are wired', () => {
  const frontendRoot = new URL('../src/', import.meta.url)
  const registration = readFileSync(new URL('pages/FichaCreate.jsx', frontendRoot), 'utf8')
  const login = readFileSync(new URL('pages/Login.jsx', frontendRoot), 'utf8')
  const yoga = readFileSync(new URL('pages/YogaProgram.jsx', frontendRoot), 'utf8')
  const app = readFileSync(new URL('pages/App.jsx', frontendRoot), 'utf8')
  const footer = readFileSync(new URL('components/Footer.jsx', frontendRoot), 'utf8')
  const consent = readFileSync(new URL('components/LegalConsentGate.jsx', frontendRoot), 'utf8')

  assert.match(registration, /name="acepta_legal"[\s\S]*required/)
  assert.match(registration, /acepta_legal: event\.target\.checked/)
  assert.equal((registration.match(/name="acepta_legal"/g) || []).length, 1)
  assert.match(registration, /href='\/privacidad'[\s\S]*target='_blank'/)
  assert.match(registration, /href='\/terminos'[\s\S]*target='_blank'/)
  assert.match(login, /<Link to='\/privacidad'>/)
  assert.match(login, /<Link to='\/terminos'>/)
  assert.match(yoga, /<Link to='\/privacidad'>/)
  assert.match(yoga, /<Link to='\/terminos'>/)
  assert.match(app, /path="\/privacidad"/)
  assert.match(app, /path="\/terminos"/)
  assert.match(footer, /href='\/privacidad'/)
  assert.match(footer, /href='\/terminos'/)
  assert.match(consent, /api\/legal-consents\/accept/)
  assert.match(consent, /publicRecoveryPaths = \['\/privacidad', '\/terminos', '\/login', '\/cargarFicha', '\/cargarFicha\/'\]/)
  assert.match(consent, /<Link to='\/login'>Volver a iniciar sesión<\/Link>/)
})

test('legal version matches backend defaults and production environment example', () => {
  const backendConfig = readFileSync(new URL('../../../api.untoquedeluz/config/legal.php', import.meta.url), 'utf8')
  const productionEnv = readFileSync(new URL('../../../api.untoquedeluz/.env.production.example', import.meta.url), 'utf8')
  const backendVersion = backendConfig.match(/policy_version'\s*=>\s*env\('LEGAL_POLICY_VERSION',\s*'([^']+)'\)/)?.[1]
  const productionVersion = productionEnv.match(/^LEGAL_POLICY_VERSION=(.+)$/m)?.[1]

  assert.equal(backendVersion, POLICY_VERSION)
  assert.equal(productionVersion, POLICY_VERSION)
  assert.deepEqual(validateLegalConfiguration({
    backendConfig,
    productionEnvironment: productionEnv,
  }), [])
})

test('legal content keeps one versioned source and is explicitly marked as draft', () => {
  assert.equal(POLICY_VERSION, 'borrador-2026-10-23-v1')
  assert.equal(POLICY_UPDATED_AT, '2026-10-23')
  assert.equal(POLICY_DRAFT, true)
  assert.ok(legalContent.privacidad.sections.length > 0)
  assert.ok(legalContent.terminos.sections.length > 0)
})

test('home practice titles overlay photos and footer contact details are environment-configurable', () => {
  const frontendRoot = new URL('../src/', import.meta.url)
  const home = readFileSync(new URL('pages/Inicio.jsx', frontendRoot), 'utf8')
  const homeStyles = readFileSync(new URL('Styles/Inicio.css', frontendRoot), 'utf8')
  const footer = readFileSync(new URL('components/Footer.jsx', frontendRoot), 'utf8')

  assert.match(home, /className="inicio-actividad-imagen"/)
  assert.match(homeStyles, /\.inicio-actividad-imagen h3/)
  assert.match(footer, /VITE_STUDIO_ADDRESS/)
  assert.match(footer, /VITE_GOOGLE_MAPS_URL/)
  assert.match(footer, /VITE_CONTACT_EMAIL/)
  assert.match(footer, /VITE_INSTAGRAM_URL/)
  assert.match(footer, /VITE_FACEBOOK_URL/)
  assert.match(footer, /VITE_WHATSAPP_NUMBER/)
})