import React from 'react'

export const requestError = error => {
  const validation = error.response?.data?.errors
  return validation ? Object.values(validation).flat().join(' ') : error.response?.data?.message || error.message || 'No se pudo completar la solicitud.'
}

export const ESTADOS = { pendiente: 'Pendiente', parcial: 'Parcial', pagado: 'Pagado', anulado: 'Anulado', vigente: 'Vigente' }

export const StatusBadge = ({ estado }) => (
  <span className={`booking-status payment-status payment-status-${estado}`}>{ESTADOS[estado] || estado}</span>
)

const todayParts = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Argentina/Buenos_Aires' }).format(new Date())

export const todayBuenosAires = () => todayParts()
export const currentPeriod = () => todayParts().slice(0, 7)

export const formatPeriod = periodo => {
  const label = new Intl.DateTimeFormat('es-AR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${periodo.slice(0, 7)}-15T12:00:00Z`))
  return label.charAt(0).toUpperCase() + label.slice(1)
}

export const formatDate = date => new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`))

export const METODOS = { efectivo: 'Efectivo', transferencia: 'Transferencia', otro: 'Otro' }
