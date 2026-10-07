import React, { useState } from 'react'
import axios from '../api/axios'
import useSWR from 'swr'
import fetcher from '../api/fetcher'
import { BsCalendar2Week, BsClock, BsPeople } from 'react-icons/bs'
import '../Styles/Reservas.css'

const weekdays = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
const requestError = error => {
  const validation = error.response?.data?.errors
  return validation ? Object.values(validation).flat().join(' ') : error.response?.data?.message || error.message || 'No se pudo completar la solicitud.'
}
const formatDate = date => new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`))
const scheduleOptions = cycle => {
  const schedules = new Map()
  cycle.clases?.forEach(clase => {
    if (!clase.horario) return
    if (!schedules.has(clase.horario_id)) schedules.set(clase.horario_id, { horario: clase.horario, classes: [] })
    schedules.get(clase.horario_id).classes.push(clase)
  })
  return [...schedules.values()].sort((a, b) => a.horario.dia_semana - b.horario.dia_semana || a.horario.hora_inicio.localeCompare(b.horario.hora_inicio))
}

const BookingStudent = () => {
  const [selectedSchedules, setSelectedSchedules] = useState({})
  const [savingCycle, setSavingCycle] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const { data: cycles = [], error: loadError, isLoading: loading, mutate: refreshCycles } = useSWR('api/booking/cycles/active', fetcher)

  const toggleSchedule = (cycleId, scheduleId) => {
    setSelectedSchedules(current => {
      const selected = current[cycleId] || []
      const exists = selected.includes(scheduleId)
      return { ...current, [cycleId]: exists ? selected.filter(id => id !== scheduleId) : [...selected, scheduleId] }
    })
  }

  const enroll = async event => {
    event.preventDefault()
    const cycleId = event.currentTarget.dataset.cycleId
    const scheduleIds = selectedSchedules[cycleId] || []
    const cycle = cycles.find(item => String(item.id) === cycleId)
    if (!cycle || scheduleIds.length !== cycle.clases_por_semana) return

    setSavingCycle(cycleId)
    setError('')
    setNotice('')
    try {
      await axios.post(`api/booking/cycles/${cycleId}/enroll`, { horario_ids: scheduleIds })
      setNotice(`Inscripción confirmada para ${cycle.nombre}.`)
      setSelectedSchedules(current => ({ ...current, [cycleId]: [] }))
      await refreshCycles()
    } catch (requestErrorValue) {
      setError(requestError(requestErrorValue))
    } finally {
      setSavingCycle(null)
    }
  }

  return (
    <section className='booking-page' aria-labelledby='booking-title'>
      <header className='booking-heading'>
        <p className='booking-eyebrow'>Práctica semanal</p>
        <h1 id='booking-title'>Elegí tu ciclo</h1>
        <p>Seleccioná tus días fijos. La reserva se crea para cada clase futura del ciclo.</p>
      </header>
      {(error || loadError) && <p className='booking-message booking-message-error' role='alert'>{error || requestError(loadError)}</p>}
      {notice && <p className='booking-message booking-message-success' role='status'>{notice}</p>}
      {loading ? <p className='booking-message' role='status'>Cargando ciclos disponibles...</p> : cycles.length === 0 ? (
        <div className='booking-empty'><h2>No hay ciclos abiertos</h2><p>Cuando haya nuevas fechas disponibles, aparecerán acá.</p></div>
      ) : (
        <div className='booking-cycle-list'>
          {cycles.map(cycle => {
            const schedules = scheduleOptions(cycle)
            const selected = selectedSchedules[cycle.id] || []
            const correctSelection = selected.length === cycle.clases_por_semana
            return (
              <article className='booking-cycle' key={cycle.id}>
                <div className='booking-cycle-header'>
                  <div>
                    <p className='booking-eyebrow'>Ciclo</p>
                    <h2>{cycle.nombre}</h2>
                  </div>
                  <p className='booking-price'>${Number(cycle.precio).toLocaleString('es-AR')}</p>
                </div>
                <div className='booking-cycle-meta'>
                  <span><BsCalendar2Week aria-hidden='true' /> {formatDate(cycle.fecha_inicio)} a {formatDate(cycle.fecha_fin)}</span>
                  <span><BsPeople aria-hidden='true' /> {cycle.clases_por_semana} {cycle.clases_por_semana === 1 ? 'día fijo' : 'días fijos'} por semana</span>
                </div>
                <form className='booking-enroll-form' data-cycle-id={cycle.id} onSubmit={enroll}>
                  <fieldset className='booking-schedule-options'>
                    <legend>Horarios disponibles</legend>
                    {schedules.length === 0 ? <p className='booking-muted'>Todavía no se generaron clases para este ciclo.</p> : schedules.map(({ horario, classes }) => {
                      const fullCount = classes.filter(clase => clase.disponibles <= 0).length
                      const scheduleId = horario.id
                      return (
                        <label className={`booking-schedule-option${selected.includes(scheduleId) ? ' is-selected' : ''}`} key={scheduleId}>
                          <input
                            type='checkbox'
                            checked={selected.includes(scheduleId)}
                            onChange={() => toggleSchedule(cycle.id, scheduleId)}
                            aria-label={`${weekdays[horario.dia_semana]} ${horario.hora_inicio.slice(0, 5)}`}
                          />
                          <span className='booking-schedule-main'>
                            <strong>{weekdays[horario.dia_semana]}</strong>
                            <span><BsClock aria-hidden='true' /> {horario.hora_inicio.slice(0, 5)} · {horario.duracion_min} min</span>
                          </span>
                          <span className='booking-schedule-side'>
                            <span>{horario.nivel || 'Todos los niveles'}</span>
                            <small>{classes.length} clases futuras{fullCount ? ` · ${fullCount} completa${fullCount === 1 ? '' : 's'}` : ''}</small>
                          </span>
                        </label>
                      )
                    })}
                  </fieldset>
                  <div className='booking-enroll-footer'>
                    <p className={correctSelection ? 'booking-selection-valid' : 'booking-muted'}>
                      Elegidos: {selected.length} de {cycle.clases_por_semana}
                    </p>
                    <button className='booking-button booking-button-primary' type='submit' disabled={!correctSelection || savingCycle === String(cycle.id) || schedules.length === 0}>
                      {savingCycle === String(cycle.id) ? 'Reservando...' : 'Inscribirme al ciclo'}
                    </button>
                  </div>
                </form>
              </article>
            )
          })}
        </div>
      )}
    </section>
  )
}

export default BookingStudent
