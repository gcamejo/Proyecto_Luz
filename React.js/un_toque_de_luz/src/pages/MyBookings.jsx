import React, { useState } from 'react'
import axios from '../api/axios'
import useSWR from 'swr'
import fetcher from '../api/fetcher'
import { BsArrowRepeat, BsCalendar2Week, BsClock } from 'react-icons/bs'
import { Link } from 'react-router-dom'
import '../Styles/Reservas.css'

const requestError = error => {
  const validation = error.response?.data?.errors
  return validation ? Object.values(validation).flat().join(' ') : error.response?.data?.message || error.message || 'No se pudo completar la solicitud.'
}
const formatDate = date => new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`))
const formatClassDate = date => new Intl.DateTimeFormat('es-AR', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`))
const formatMonthName = date => new Intl.DateTimeFormat('es-AR', { month: 'long', timeZone: 'UTC' }).format(new Date(`${date.slice(0, 7)}-01T12:00:00Z`))
const formatNumericDate = date => new Intl.DateTimeFormat('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`))
const creditExpiryForClass = classDate => {
  const [year, month] = classDate.split('-').map(Number)
  const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate()
  return `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`
}

const MyBookings = () => {
  const [selectedClasses, setSelectedClasses] = useState({})
  const [busyId, setBusyId] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const { data: bookingsData, error: bookingsError, isLoading: bookingsLoading, mutate: refreshBookings } = useSWR('api/booking/me', fetcher)
  const { data: credits = [], error: creditsError, isLoading: creditsLoading, mutate: refreshCredits } = useSWR('api/booking/credits', fetcher)
  const { data: recoveryClasses = [], error: classesError, isLoading: classesLoading, mutate: refreshRecoveryClasses } = useSWR('api/booking/recoveries/available-classes', fetcher)
  const data = { ...(bookingsData || { inscripciones: [] }), recuperaciones: credits }
  const loading = bookingsLoading || creditsLoading || classesLoading
  const loadError = bookingsError || creditsError || classesError
  const refresh = () => Promise.all([refreshBookings(), refreshCredits(), refreshRecoveryClasses()])

  const cancelReservation = async reservation => {
    const confirmation = reservation.genera_credito
      ? `¿Cancelar esta reserva? Se generará un crédito válido hasta el ${formatNumericDate(creditExpiryForClass(reservation.clase.fecha))}, para clases de ${formatMonthName(reservation.clase.fecha)}.`
      : '¿Cancelar esta reserva? No se generará un crédito porque faltan menos de 24 horas.'
    if (!window.confirm(confirmation)) return

    setBusyId(`cancel-${reservation.id}`)
    setError('')
    setNotice('')
    try {
      const { data: result } = await axios.post(`api/booking/reservations/${reservation.id}/cancel`)
      setNotice(result.credito_devuelto
        ? 'Reserva cancelada con aviso. Tu crédito de recuperación volvió a estar disponible.'
        : result.recuperacion_generada
          ? 'Reserva cancelada con aviso. Se generó un crédito de recuperación.'
          : 'Reserva cancelada. No se generó un crédito porque el aviso fue menor a 24 horas.')
      await refresh()
    } catch (requestErrorValue) {
      setError(requestError(requestErrorValue))
    } finally {
      setBusyId(null)
    }
  }

  const cancelEnrollment = async enrollment => {
    const futureReservations = enrollment.reservas.filter(reservation =>
      reservation.es_proxima && reservation.clase?.ciclo_id === enrollment.ciclo_id
    ).length
    const cycleName = enrollment.ciclo?.nombre || 'este ciclo'
    const confirmation = `¿Darte de baja de ${cycleName}? Se cancelarán ${futureReservations} clases futuras de este ciclo. Cada cancelación se revisará según el aviso mínimo de 24 horas. El historial y las clases pasadas se conservarán.`
    if (!window.confirm(confirmation)) return

    setBusyId(`enrollment-${enrollment.id}`)
    setError('')
    setNotice('')
    try {
      const { data: result } = await axios.post(`api/booking/enrollments/${enrollment.id}/cancel`)
      const creditsByMonth = (result.creditos_por_mes || []).map(monthCredits => {
        const monthName = formatMonthName(monthCredits.vence_en)
        const counts = [
          monthCredits.generados ? `${monthCredits.generados} generados` : '',
          monthCredits.devueltos ? `${monthCredits.devueltos} devueltos` : '',
        ].filter(Boolean).join(', ')
        return `${monthName}: ${counts}, vence el ${formatNumericDate(monthCredits.vence_en)}`
      })
      const resultLines = [`Baja confirmada de ${cycleName}.`]
      if (result.reservas_canceladas) resultLines.push(`${result.reservas_canceladas} clases futuras canceladas.`)
      else resultLines.push('No había clases futuras para cancelar.')
      if (creditsByMonth.length) resultLines.push(`Créditos por mes: ${creditsByMonth.join('; ')}.`)
      if (result.reservas_sin_credito) resultLines.push(`${result.reservas_sin_credito} reservas sin crédito por aviso menor a 24 horas.`)
      setNotice(resultLines.join(' '))
      await refresh()
    } catch (requestErrorValue) {
      setError(requestError(requestErrorValue))
    } finally {
      setBusyId(null)
    }
  }

  const bookRecovery = async credit => {
    const classId = selectedClasses[credit.id]
    if (!classId) return
    setBusyId(`credit-${credit.id}`)
    setError('')
    setNotice('')
    try {
      await axios.post(`api/booking/recoveries/${credit.id}/book`, { clase_id: classId })
      setNotice('Clase de recuperación reservada.')
      setSelectedClasses(current => ({ ...current, [credit.id]: '' }))
      await refresh()
    } catch (requestErrorValue) {
      setError(requestError(requestErrorValue))
    } finally {
      setBusyId(null)
    }
  }

  const reservations = data.inscripciones.flatMap(enrollment => enrollment.reservas.map(reservation => ({
    ...reservation,
    cycleName: enrollment.ciclo?.nombre,
  })))
  const upcomingReservations = reservations.filter(reservation => reservation.es_proxima)
  const activeEnrollments = data.inscripciones.filter(enrollment => enrollment.estado === 'activa')
  const reservationHistory = reservations.filter(reservation => !reservation.es_proxima)
  const reservedClassIds = new Set(data.inscripciones.flatMap(enrollment => enrollment.reservas.map(reservation => reservation.clase_id)))
  const eligibleClasses = recoveryClasses
  const displayError = error || (loadError ? requestError(loadError) : '')
  const renderReservation = reservation => (
    <li className='booking-reservation' key={reservation.id}>
      <div>
        <strong><BsCalendar2Week aria-hidden='true' /> {formatClassDate(reservation.clase.fecha)}</strong>
        <span><BsClock aria-hidden='true' /> {reservation.clase.hora_inicio.slice(0, 5)} · {reservation.cycleName} · {reservation.tipo === 'recuperacion' ? 'Recuperación' : 'Regular'}</span>
      </div>
      <span className={`booking-status booking-status-${reservation.estado}`}>{reservation.estado.replaceAll('_', ' ')}</span>
      {reservation.puede_cancelar && (
        <div className='booking-cancel-action'>
          <small>{reservation.genera_credito
            ? `Crédito válido para ${formatMonthName(reservation.clase.fecha)}; vence ${formatNumericDate(creditExpiryForClass(reservation.clase.fecha))}`
            : 'No genera crédito: aviso menor a 24 h'}</small>
          <button className='booking-text-button booking-text-button-danger' type='button' onClick={() => cancelReservation(reservation)} disabled={busyId === `cancel-${reservation.id}`}>
            {busyId === `cancel-${reservation.id}` ? 'Cancelando...' : 'No voy a poder ir'}
          </button>
        </div>
      )}
    </li>
  )

  return (
    <section className='booking-page' aria-labelledby='my-bookings-title'>
      <header className='booking-heading'>
        <p className='booking-eyebrow'>Tu práctica</p>
        <h1 id='my-bookings-title'>Mis reservas</h1>
        <p>Consultá tus clases y usá tus créditos de recuperación dentro del mes.</p>
      </header>
      {displayError && <p className='booking-message booking-message-error' role='alert'>{displayError}</p>}
      {notice && <p className='booking-message booking-message-success' role='status'>{notice}</p>}
      {loading ? <p className='booking-message' role='status'>Cargando tus reservas...</p> : (
        <>
          <section className='booking-section' aria-labelledby='upcoming-title'>
            <div className='booking-section-heading'>
              <div><p className='booking-eyebrow'>Próximas</p><h2 id='upcoming-title'>Mis clases</h2></div>
              <Link className='booking-inline-link' to='/reservarCiclo'>Explorar ciclos</Link>
            </div>
            {activeEnrollments.length > 0 && (
              <div className='booking-reservation-list'>
                {activeEnrollments.map(enrollment => {
                  const cycleReservations = enrollment.reservas.filter(reservation =>
                    reservation.es_proxima && reservation.clase?.ciclo_id === enrollment.ciclo_id
                  )
                  return (
                    <article className='booking-enrollment' key={enrollment.id}>
                      <header>
                        <div>
                          <h3>{enrollment.ciclo?.nombre}</h3>
                          <p>{formatDate(enrollment.ciclo?.fecha_inicio)} a {formatDate(enrollment.ciclo?.fecha_fin)} · {cycleReservations.length} clases próximas</p>
                        </div>
                        <button
                          className='booking-text-button booking-text-button-danger'
                          type='button'
                          disabled={busyId === `enrollment-${enrollment.id}`}
                          onClick={() => cancelEnrollment(enrollment)}
                        >
                          {busyId === `enrollment-${enrollment.id}` ? 'Procesando baja...' : 'Darme de baja del ciclo'}
                        </button>
                      </header>
                    </article>
                  )
                })}
              </div>
            )}
            {upcomingReservations.length ? <ul className='booking-reservation-list'>{upcomingReservations.map(renderReservation)}</ul> : <p className='booking-empty'>No tenés clases próximas reservadas.</p>}
          </section>

          <section className='booking-section' aria-labelledby='history-title'>
            <div className='booking-section-heading'><div><p className='booking-eyebrow'>Registro</p><h2 id='history-title'>Historial de clases</h2></div></div>
            {reservationHistory.length ? <ul className='booking-reservation-list'>{reservationHistory.map(renderReservation)}</ul> : <p className='booking-empty'>Todavía no hay clases en tu historial.</p>}
          </section>

          <section className='booking-section' aria-labelledby='credits-title'>
            <div className='booking-section-heading'>
              <div><p className='booking-eyebrow'>Recuperaciones</p><h2 id='credits-title'>Mis créditos</h2></div>
            </div>
            {data.recuperaciones.length === 0 ? <p className='booking-empty'>No tenés créditos de recuperación.</p> : (
              <div className='booking-credit-list'>
                {data.recuperaciones.map(credit => {
                  const options = eligibleClasses.filter(classItem =>
                    credit.estado === 'disponible' &&
                    classItem.fecha.slice(0, 7) === credit.vence_en.slice(0, 7) &&
                    classItem.disponibles > 0 &&
                    !reservedClassIds.has(classItem.id)
                  )
                  return (
                    <article className='booking-credit' key={credit.id}>
                      <div className='booking-credit-info'>
                        <span className={`booking-status booking-status-${credit.estado}`}>{credit.estado}</span>
                        <p>Válido para clases de <strong>{formatMonthName(credit.vence_en)}</strong></p>
                        <p>Vence el <strong>{formatDate(credit.vence_en)}</strong></p>
                        {credit.reserva_origen?.clase && <small>Origen: {formatDate(credit.reserva_origen.clase.fecha)}</small>}
                      </div>
                      {credit.estado === 'disponible' && (
                        <div className='booking-credit-action'>
                          <label className='booking-visually-hidden' htmlFor={`credit-class-${credit.id}`}>Clase destino para el crédito {credit.id}</label>
                          <select id={`credit-class-${credit.id}`} value={selectedClasses[credit.id] || ''} onChange={event => setSelectedClasses(current => ({ ...current, [credit.id]: event.target.value }))}>
                            <option value=''>Elegí una clase con lugar en {formatMonthName(credit.vence_en)}</option>
                            {options.map(classItem => <option value={classItem.id} key={classItem.id}>{classItem.ciclo?.nombre} · {formatDate(classItem.fecha)} · {classItem.hora_inicio.slice(0, 5)} · {classItem.disponibles} lugares</option>)}
                          </select>
                          {options.length === 0 && <small className='booking-muted'>No hay clases futuras con lugar dentro de la vigencia de este crédito.</small>}
                          <button className='booking-button booking-button-primary' type='button' disabled={!selectedClasses[credit.id] || busyId === `credit-${credit.id}`} onClick={() => bookRecovery(credit)}>
                            <BsArrowRepeat aria-hidden='true' /> {busyId === `credit-${credit.id}` ? 'Reservando...' : 'Usar crédito'}
                          </button>
                        </div>
                      )}
                    </article>
                  )
                })}
              </div>
            )}
          </section>
        </>
      )}
    </section>
  )
}

export default MyBookings
