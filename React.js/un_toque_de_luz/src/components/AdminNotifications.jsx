import React, { useEffect, useRef, useState } from 'react'
import { BsBell } from 'react-icons/bs'
import useSWR from 'swr'
import axios from '../api/axios'
import fetcher from '../api/fetcher'
import '../Styles/AdminNotifications.css'

const formatDate = value => new Intl.DateTimeFormat('es-AR', {
  dateStyle: 'short',
  timeStyle: 'short',
}).format(new Date(value))

const AdminNotifications = () => {
  const rootRef = useRef(null)
  const [open, setOpen] = useState(false)
  const [marking, setMarking] = useState(null)
  const [actionError, setActionError] = useState('')
  const { data, error, isLoading, mutate } = useSWR('api/admin/notifications', fetcher, {
    refreshInterval: 60000,
    revalidateOnFocus: true,
  })
  const unreadCount = data?.unread_count || 0
  const notifications = data?.notifications || []

  useEffect(() => {
    if (!open) return undefined

    const closeOnOutsidePointerDown = event => {
      if (!rootRef.current?.contains(event.target)) setOpen(false)
    }
    const closeOnEscape = event => {
      if (event.key === 'Escape') {
        setOpen(false)
        rootRef.current?.querySelector('.admin-notifications-trigger')?.focus()
      }
    }

    document.addEventListener('pointerdown', closeOnOutsidePointerDown)
    document.addEventListener('keydown', closeOnEscape)

    return () => {
      document.removeEventListener('pointerdown', closeOnOutsidePointerDown)
      document.removeEventListener('keydown', closeOnEscape)
    }
  }, [open])

  const toggleList = () => {
    setOpen(current => !current)
    setActionError('')
    if (!open) mutate()
  }

  const markAsRead = async notificationId => {
    setMarking(notificationId)
    setActionError('')
    try {
      await axios.post(`api/admin/notifications/${notificationId}/read`)
      await mutate()
    } catch {
      setActionError('No se pudo marcar la notificación como leída.')
    } finally {
      setMarking(null)
    }
  }

  return (
    <li className="nav-item admin-notifications" ref={rootRef}>
      <button
        className="admin-notifications-trigger"
        type="button"
        title="Notificaciones de registros"
        aria-label={`Notificaciones de registros, ${unreadCount} sin leer`}
        aria-expanded={open}
        aria-controls="admin-notifications-panel"
        onClick={toggleList}
      >
        <BsBell aria-hidden="true" />
        {unreadCount > 0 && <span className="admin-notifications-count">{unreadCount > 99 ? '99+' : unreadCount}</span>}
      </button>

      {open && <section className="admin-notifications-panel" id="admin-notifications-panel" aria-label="Notificaciones de nuevos registros">
        <header className="admin-notifications-heading">
          <h2>Registros nuevos</h2>
          <span>{unreadCount} sin leer</span>
        </header>
        {actionError && <p className="admin-notifications-error" role="alert">{actionError}</p>}
        {error && <p className="admin-notifications-error" role="alert">No se pudieron cargar las notificaciones.</p>}
        {isLoading && <p className="admin-notifications-empty" role="status">Cargando notificaciones…</p>}
        {!isLoading && !error && notifications.length === 0 && <p className="admin-notifications-empty">No hay registros para mostrar.</p>}
        <ul className="admin-notifications-list">
          {notifications.map(notification => (
            <li className={notification.read_at ? 'is-read' : 'is-unread'} key={notification.id}>
              <div className="admin-notification-details">
                <strong>{notification.nombre}</strong>
                <a href={`mailto:${notification.email}`}>{notification.email}</a>
                <time dateTime={notification.fecha}>{formatDate(notification.fecha)}</time>
              </div>
              {!notification.read_at && <button type="button" onClick={() => markAsRead(notification.id)} disabled={marking === notification.id}>
                {marking === notification.id ? 'Guardando…' : 'Marcar como leída'}
              </button>}
            </li>
          ))}
        </ul>
      </section>}
    </li>
  )
}

export default AdminNotifications
