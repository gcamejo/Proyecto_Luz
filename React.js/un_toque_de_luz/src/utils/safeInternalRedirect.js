export const safeInternalRedirect = (candidate, fallback = '/', origin = globalThis.location?.origin) => {
  if (!origin || typeof candidate !== 'string' || !candidate.startsWith('/') || candidate.startsWith('//') || candidate.includes('\\')) {
    return fallback
  }

  try {
    const base = new URL(origin)
    const destination = new URL(candidate, base)
    if (destination.origin !== base.origin) return fallback
    return `${destination.pathname}${destination.search}${destination.hash}`
  } catch {
    return fallback
  }
}
