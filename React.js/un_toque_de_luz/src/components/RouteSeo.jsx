import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { applySeo, resolveSeo } from '../utils/seo'

const RouteSeo = () => {
  const { pathname } = useLocation()

  useEffect(() => {
    applySeo(resolveSeo(pathname, window.location.origin))
  }, [pathname])

  return null
}

export default RouteSeo
