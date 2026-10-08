import axios from 'axios'

let getToken = () => null
const apiBaseUrl = import.meta.env.VITE_API_URL || (import.meta.env.DEV ? `${window.location.protocol}//localhost:8000/` : undefined)

if (!apiBaseUrl) {
  throw new Error('VITE_API_URL must be configured for production builds.')
}

const api = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export const apiAssetUrl = path => new URL(`/${path.replace(/^\/+/, '')}`, apiBaseUrl || window.location.origin).toString()

api.interceptors.request.use(config => {
  const token = getToken()
  if (token) config.headers.set('Authorization', `Bearer ${token}`)
  else delete config.headers.Authorization
  return config
})

export const setTokenProvider = provider => {
  getToken = provider
}

export default api