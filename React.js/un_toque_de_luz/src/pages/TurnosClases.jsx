import React, { useState } from 'react'
import axios from '../api/axios'
import useSWR from 'swr'
import fetcher from '../api/fetcher'
import '../Styles/Reservas.css'

const reservationStates = new Set(['reservada', 'asistio', 'falto'])
const fetchClasses = async ([url, params]) => {
  const { data } = await axios.get(url, { params })
  return data
}

const requestError = error => {
  const validation = error.response?.data?.errors
  return validation ? Object.values(validation).flat().join(' ') : error.response?.data?.message || error.message || 'No se pudo cargar el listado.'
}

const formatDate = date => new Intl.DateTimeFormat('es-AR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'UTC',
}).format(new Date(`${date}T12:00:00Z`))

const reservationLabel = state => ({
  reservada: 'Reservó',
  asistio: 'Asistió',
  falto: 'Faltó',
}[state] || state)

const TurnosClases = () => {
  const [cycleId, setCycleId] = useState('')
  const { data: cycles = [], error: cyclesError, isLoading: cyclesLoading } = useSWR('api/booking/admin/cycles', fetcher)
  const classParams = cycleId ? { ciclo_id: cycleId } : {}
  const { data: classes = [], error: classesError, isLoading: classesLoading } = useSWR(
    ['api/booking/admin/classes', classParams],
    fetchClasses
  )
  const error = cyclesError || classesError
  const isLoading = cyclesLoading || classesLoading
  const classesWithStudents = classes
    .map(classItem => ({
      ...classItem,
      students: (classItem.reservas || []).filter(reservation => reservationStates.has(reservation.estado) && reservation.yoguini),
    }))
    .filter(classItem => classItem.students.length > 0)

  return (
    <section className='booking-page booking-roster-page' aria-labelledby='turnos-classes-title'>
      <header className='booking-heading'>
        <p className='booking-eyebrow'>Turnos</p>
        <h1 id='turnos-classes-title'>Clases y alumnos</h1>
        <p>Clases con reservas y alumnos anotados.</p>
      </header>

      {error && <p className='booking-message booking-message-error' role='alert'>{requestError(error)}</p>}
      <div className='booking-class-filters'>
        <label htmlFor='turnos-cycle-filter'>Ciclo
          <select id='turnos-cycle-filter' value={cycleId} onChange={event => setCycleId(event.target.value)}>
            <option value=''>Todos los ciclos</option>
            {cycles.map(cycle => <option value={cycle.id} key={cycle.id}>{cycle.nombre}</option>)}
          </select>
        </label>
      </div>
      {isLoading ? <p className='booking-message' role='status'>Cargando clases...</p> : classesWithStudents.length === 0 ? (
        <p className='booking-empty'>{cycleId ? 'Este ciclo no tiene clases con alumnos reservados.' : 'No hay clases con alumnos reservados.'}</p>
      ) : (
        <div className='booking-readonly-class-list'>
          {classesWithStudents.map(classItem => (
            <article className='booking-readonly-class' key={classItem.id}>
              <header className='booking-readonly-class-header'>
                <div>
                  <h2>{formatDate(classItem.fecha)} · {classItem.hora_inicio.slice(0, 5)}</h2>
                  <p>{classItem.ciclo?.nombre} · {classItem.duracion_min} min</p>
                  <small>{classItem.profesor || 'Sin profesor'} · {classItem.nivel || 'Todos los niveles'}</small>
                </div>
                <span className='booking-count'>{classItem.students.length} {classItem.students.length === 1 ? 'alumno' : 'alumnos'} · cupo {classItem.cupo}</span>
              </header>
              <ul className='booking-readonly-students'>
                {classItem.students.map(reservation => (
                  <li key={reservation.id}>
                    <span>{reservation.yoguini.nombre} {reservation.yoguini.apellido}</span>
                    <small>{reservationLabel(reservation.estado)} · {reservation.tipo === 'recuperacion' ? 'Recuperación' : 'Regular'}</small>
                  </li>
                ))}
              </ul>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}

export default TurnosClases