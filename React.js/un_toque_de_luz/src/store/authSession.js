const sessionKey = 'un-toque-de-luz.auth-session'

const browserSessionStorage = () => {
  try {
    return typeof window === 'undefined' ? null : window.sessionStorage
  } catch {
    return null
  }
}

export const loadAuthSession = (storage = browserSessionStorage()) => {
  if (!storage) return null

  try {
    const session = JSON.parse(storage.getItem(sessionKey) || 'null')
    if (typeof session?.token !== 'string' || !session.token || !session.user || typeof session.user !== 'object') {
      storage.removeItem(sessionKey)
      return null
    }
    return session
  } catch {
    try { storage.removeItem(sessionKey) } catch {}
    return null
  }
}

export const saveAuthSession = (token, user, storage = browserSessionStorage()) => {
  if (!storage || typeof token !== 'string' || !token || !user || typeof user !== 'object') return

  try {
    storage.setItem(sessionKey, JSON.stringify({ token, user }))
  } catch {}
}

export const clearAuthSession = (storage = browserSessionStorage()) => {
  if (!storage) return
  try { storage.removeItem(sessionKey) } catch {}
}
