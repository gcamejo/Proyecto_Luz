import React, { useEffect, useRef, useState } from 'react'
import { useSelector } from 'react-redux'
import { Link, useLocation } from 'react-router-dom'
import useSWR from 'swr'
import axios from '../api/axios'
import fetcher from '../api/fetcher'
import '../Styles/Legal.css'

const LegalConsentGate = ({ children }) => {
  const { token } = useSelector(state => state.token)
  const location = useLocation()
  const publicRecoveryPaths = ['/privacidad', '/terminos', '/login', '/cargarFicha', '/cargarFicha/']
  const isPublicRecoveryPath = publicRecoveryPaths.includes(location.pathname)
  const dialogRef = useRef(null)
  const [accepted, setAccepted] = useState(false)
  const [saving, setSaving] = useState(false)
  const [actionError, setActionError] = useState('')
  const { data, error, isLoading, mutate } = useSWR(token && !isPublicRecoveryPath ? 'api/legal-consents/status' : null, fetcher, {
    revalidateOnFocus: true,
  })

  useEffect(() => {
    if (token && data && !data.accepted && !isPublicRecoveryPath) {
      dialogRef.current?.focus()
    }
  }, [data, isPublicRecoveryPath, token])

  if (!token || isPublicRecoveryPath) return children

  if (isLoading || (!data && !error)) {
    return <div className='legal-gate-status' role='status'>Verificando aceptación de documentos…</div>
  }

  if (error) {
    return (
      <div className='legal-gate-status' role='alert'>
        <h1>No se pudo verificar la aceptación</h1>
        <p>Para continuar, volvé a intentar la consulta.</p>
        <button type='button' onClick={() => mutate()}>Reintentar</button>
        <p><Link to='/login'>Volver a iniciar sesión</Link></p>
      </div>
    )
  }

  if (data?.accepted) return children

  const acceptCurrentVersion = async event => {
    event.preventDefault()
    if (!accepted || saving) return

    setSaving(true)
    setActionError('')
    try {
      const response = await axios.post('api/legal-consents/accept', { acepta: true, tipos: data.pending })
      await mutate(response.data, { revalidate: false })
      setAccepted(false)
    } catch (requestError) {
      setActionError(requestError.response?.data?.message || 'No se pudo guardar la aceptación. Intentá nuevamente.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className='legal-gate-backdrop' onKeyDown={event => {
      if (event.key !== 'Tab') return
      const focusable = [...event.currentTarget.querySelectorAll('a[href], input:not(:disabled), button:not(:disabled)')]
      const first = focusable[0]
      const last = focusable[focusable.length - 1]
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last?.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first?.focus()
      }
    }}>
      <section ref={dialogRef} className='legal-gate-dialog' role='dialog' aria-modal='true' aria-labelledby='legal-gate-title' aria-describedby='legal-gate-description' tabIndex={-1}>
        <p className='legal-draft-notice' role='note'>Los documentos están identificados como borrador para revisión legal.</p>
        <h1 id='legal-gate-title'>Necesitamos registrar tu aceptación</h1>
        <p id='legal-gate-description'>La Política de Privacidad o los Términos y Condiciones cambiaron. Revisalos para continuar usando la cuenta.</p>
        <p><Link to='/login'>Volver a iniciar sesión</Link></p>
        <nav className='legal-gate-links' aria-label='Documentos pendientes'>
          {(data?.pending || ['privacidad', 'terminos']).includes('privacidad') && <a href='/privacidad' target='_blank' rel='noopener noreferrer'>Política de Privacidad</a>}
          {(data?.pending || ['privacidad', 'terminos']).includes('terminos') && <a href='/terminos' target='_blank' rel='noopener noreferrer'>Términos y Condiciones</a>}
        </nav>
        <form onSubmit={acceptCurrentVersion}>
          <label className='legal-consent-field'>
            <input type='checkbox' checked={accepted} onChange={event => setAccepted(event.target.checked)} required />
            <span>Leí y acepto los documentos pendientes en su versión {data?.version}.</span>
          </label>
          {actionError && <p className='legal-gate-error' role='alert'>{actionError}</p>}
          <button type='submit' disabled={!accepted || saving}>{saving ? 'Guardando…' : 'Aceptar y continuar'}</button>
        </form>
      </section>
    </div>
  )
}

export default LegalConsentGate