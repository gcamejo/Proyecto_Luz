import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { pathToFileURL } from 'node:url'
import { POLICY_UPDATED_AT, POLICY_VERSION } from '../src/content/legalContent.js'

export const findUnresolvedLegalMarkers = content =>
  [...new Set(content.match(/\[(?:COMPLETAR|DEFINIR)[^\]]*\]/g) || [])]

export const checkLegalFile = path => {
  const content = readFileSync(path, 'utf8')
  return findUnresolvedLegalMarkers(content)
}

export const validateLegalConfiguration = ({ backendConfig, productionEnvironment }) => {
  const errors = []
  const backendVersion = backendConfig.match(/policy_version'\s*=>\s*env\('LEGAL_POLICY_VERSION',\s*'([^']+)'\)/)?.[1]
  const backendUpdatedAt = backendConfig.match(/updated_at'\s*=>\s*env\('LEGAL_POLICY_UPDATED_AT',\s*'([^']+)'\)/)?.[1]
  const productionVersion = productionEnvironment.match(/^LEGAL_POLICY_VERSION=(.+)$/m)?.[1]
  const productionUpdatedAt = productionEnvironment.match(/^LEGAL_POLICY_UPDATED_AT=(.+)$/m)?.[1]

  if (backendVersion !== POLICY_VERSION || productionVersion !== POLICY_VERSION) {
    errors.push('POLICY_VERSION differs between frontend, Laravel config and production env example.')
  }
  if (backendUpdatedAt !== POLICY_UPDATED_AT || productionUpdatedAt !== POLICY_UPDATED_AT) {
    errors.push('POLICY_UPDATED_AT differs between frontend, Laravel config and production env example.')
  }

  return errors
}

const contentPath = fileURLToPath(new URL('../src/content/legalContent.js', import.meta.url))
const invokedFile = process.argv[1] ? pathToFileURL(process.argv[1]).href : null

if (invokedFile === import.meta.url) {
  const projectRoot = new URL('../../../', import.meta.url)
  const backendConfigPath = process.env.LEGAL_API_CONFIG_PATH
    || fileURLToPath(new URL('api.untoquedeluz/config/legal.php', projectRoot))
  const productionEnvironmentPath = process.env.LEGAL_PRODUCTION_ENV_PATH
    || fileURLToPath(new URL('../../../api.untoquedeluz/.env.production.example', import.meta.url))
  const markers = checkLegalFile(contentPath)
  const configurationErrors = validateLegalConfiguration({
    backendConfig: readFileSync(backendConfigPath, 'utf8'),
    productionEnvironment: readFileSync(productionEnvironmentPath, 'utf8'),
  })
  const errors = [...markers.map(marker => `Unresolved legal marker: ${marker}`), ...configurationErrors]

  if (errors.length > 0) {
    console.error(`Legal release check failed with ${errors.length} issue(s):`)
    for (const error of errors) console.error(`- ${error}`)
    process.exitCode = 1
  } else {
    console.log('Legal release check passed.')
  }
}