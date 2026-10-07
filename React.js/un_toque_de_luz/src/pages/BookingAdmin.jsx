import React, { useState } from 'react'
import axios from '../api/axios'
import useSWR from 'swr'
import fetcher from '../api/fetcher'
import { BsCalendar2Week, BsCheck2Circle, BsPlusLg, BsSlashCircle } from 'react-icons/bs'
import '../Styles/Reservas.css'

const weekdays = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
const blankSchedule = { dia_semana: '1', hora_inicio: '18:00', duracion_min: '60', nivel: '', profesor: '', cupo: '10', activo: true }
const blankCycle = { nombre: '', fecha_inicio: '', fecha_fin: '', clases_por_semana: '2', precio: '' }
const blankHoliday = { fecha: '', descripcion: '' }
const requestError = error => {
  const validation = error.response?.data?.errors
  return validation ? Object.values(validation).flat().join(' ') : error.response?.data?.message || error.message || 'No se pudo completar la solicitud.'
}
const formatDate = date => new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`))
const formatClassDate = date => new Intl.DateTimeFormat('es-AR', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`))
const fetchClasses = async ([url, params]) => {
  const { data } = await axios.get(url, { params })
  return data
}

const BookingAdmin = () => {
  const [scheduleForm, setScheduleForm] = useState(blankSchedule)
  const [cycleForm, setCycleForm] = useState(blankCycle)
  const [holidayForm, setHolidayForm] = useState(blankHoliday)
  const [editingScheduleId, setEditingScheduleId] = useState(null)
  const [editingCycleId, setEditingCycleId] = useState(null)
  const [editingHolidayId, setEditingHolidayId] = useState(null)
  const [classFilters, setClassFilters] = useState({ fecha: '', ciclo_id: '' })
  const [saving, setSaving] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const classParams = Object.fromEntries(Object.entries(classFilters).filter(([, value]) => value))
  const { data: schedules = [], error: schedulesError, isLoading: schedulesLoading, mutate: refreshSchedules } = useSWR('api/booking/admin/schedules', fetcher)
  const { data: cycles = [], error: cyclesError, isLoading: cyclesLoading, mutate: refreshCycles } = useSWR('api/booking/admin/cycles', fetcher)
  const { data: classes = [], error: classesError, isLoading: classesLoading, mutate: refreshClasses } = useSWR(['api/booking/admin/classes', classParams], fetchClasses)
  const { data: holidays = [], error: holidaysError, isLoading: holidaysLoading, mutate: refreshHolidays } = useSWR('api/booking/admin/holidays', fetcher)
  const loading = schedulesLoading || cyclesLoading || classesLoading || holidaysLoading
  const loadError = schedulesError || cyclesError || classesError || holidaysError
  const displayError = error || (loadError ? requestError(loadError) : '')
  const refresh = () => Promise.all([refreshSchedules(), refreshCycles(), refreshClasses(), refreshHolidays()])

  const runAction = async (key, action, successMessage) => {
    setSaving(key)
    setError('')
    setNotice('')
    try {
      const result = await action()
      setNotice(typeof successMessage === 'function' ? successMessage(result?.data) : successMessage)
      await refresh()
      return true
    } catch (requestErrorValue) {
      setError(requestError(requestErrorValue))
      return false
    } finally {
      setSaving('')
    }
  }

  const createSchedule = event => {
    event.preventDefault()
    const payload = { ...scheduleForm, dia_semana: Number(scheduleForm.dia_semana), duracion_min: Number(scheduleForm.duracion_min), cupo: Number(scheduleForm.cupo) }
    const updating = Boolean(editingScheduleId)
    const actionKey = updating ? `schedule-update-${editingScheduleId}` : 'schedule-create'
    const action = updating
      ? () => axios.patch(`api/booking/admin/schedules/${editingScheduleId}`, payload)
      : () => axios.post('api/booking/admin/schedules', payload)
    runAction(actionKey, action, updating ? 'Horario actualizado.' : 'Horario recurrente creado.')
      .then(success => {
        if (success) {
          setScheduleForm(blankSchedule)
          setEditingScheduleId(null)
        }
      })
  }

  const createCycle = event => {
    event.preventDefault()
    const payload = { ...cycleForm, clases_por_semana: Number(cycleForm.clases_por_semana), precio: Number(cycleForm.precio) }
    const updating = Boolean(editingCycleId)
    const actionKey = updating ? `cycle-update-${editingCycleId}` : 'cycle-create'
    const action = updating
      ? () => axios.patch(`api/booking/admin/cycles/${editingCycleId}`, payload)
      : () => axios.post('api/booking/admin/cycles', payload)
    runAction(actionKey, action, updating ? 'Ciclo actualizado.' : 'Ciclo creado; generá sus clases para habilitar inscripciones.')
      .then(success => {
        if (success) {
          setCycleForm(blankCycle)
          setEditingCycleId(null)
        }
      })
  }

  const editSchedule = schedule => {
    setEditingScheduleId(schedule.id)
    setScheduleForm({
      dia_semana: String(schedule.dia_semana),
      hora_inicio: schedule.hora_inicio.slice(0, 5),
      duracion_min: String(schedule.duracion_min),
      nivel: schedule.nivel || '',
      profesor: schedule.profesor || '',
      cupo: String(schedule.cupo),
      activo: schedule.activo,
    })
  }

  const editCycle = cycle => {
    setEditingCycleId(cycle.id)
    setCycleForm({
      nombre: cycle.nombre,
      fecha_inicio: cycle.fecha_inicio.slice(0, 10),
      fecha_fin: cycle.fecha_fin.slice(0, 10),
      clases_por_semana: String(cycle.clases_por_semana),
      precio: String(cycle.precio),
    })
  }

  const createHoliday = event => {
    event.preventDefault()
    const updating = Boolean(editingHolidayId)
    const actionKey = updating ? `holiday-update-${editingHolidayId}` : 'holiday-create'
    const action = updating
      ? () => axios.patch(`api/booking/admin/holidays/${editingHolidayId}`, holidayForm)
      : () => axios.post('api/booking/admin/holidays', holidayForm)
    runAction(actionKey, action, updating ? 'Feriado actualizado.' : 'Feriado agregado al calendario.')
      .then(success => {
        if (success) {
          setHolidayForm(blankHoliday)
          setEditingHolidayId(null)
        }
      })
  }

  const editHoliday = holiday => {
    setEditingHolidayId(holiday.id)
    setHolidayForm({ fecha: holiday.fecha.slice(0, 10), descripcion: holiday.descripcion || '' })
  }

  const generateCycle = cycle => runAction(`generate-${cycle.id}`,
    () => axios.post(`api/booking/admin/cycles/${cycle.id}/classes/generate`),
    result => `Clases generadas: ${result?.created || 0}; existentes: ${result?.skipped || 0}.`
  )

  const toggleCycle = cycle => runAction(`toggle-cycle-${cycle.id}`,
    () => axios.patch(`api/booking/admin/cycles/${cycle.id}`, { activo: !cycle.activo }),
    cycle.activo ? 'Ciclo pausado.' : 'Ciclo activado.'
  )

  const deleteCycle = cycle => {
    if (!window.confirm(`¿Eliminar el ciclo ${cycle.nombre}?`)) return
    return runAction(`delete-cycle-${cycle.id}`,
      () => axios.delete(`api/booking/admin/cycles/${cycle.id}`),
      'Ciclo eliminado.'
    )
  }

  const deactivateSchedule = schedule => runAction(`schedule-${schedule.id}`,
    () => axios.delete(`api/booking/admin/schedules/${schedule.id}`),
    'Horario desactivado. Las clases ya generadas conservan su configuración.'
  )

  const removeHoliday = holiday => runAction(`holiday-${holiday.id}`,
    () => axios.delete(`api/booking/admin/holidays/${holiday.id}`),
    'Feriado quitado del calendario.'
  )

  const cancelClass = classItem => {
    if (!window.confirm('¿Cancelar esta clase? Se cancelarán sus reservas activas y se emitirán o devolverán los créditos correspondientes.')) return
    return runAction(`cancel-class-${classItem.id}`,
      () => axios.patch(`api/booking/admin/classes/${classItem.id}/cancel`),
      'Clase cancelada; las reservas activas recibieron créditos.'
    )
  }

  const completeClass = classItem => runAction(`complete-class-${classItem.id}`,
    () => axios.post(`api/booking/admin/classes/${classItem.id}/complete`),
    'Clase marcada como realizada.'
  )

  const setAttendance = (reservation, state) => runAction(`attendance-${reservation.id}`,
    () => axios.patch(`api/booking/admin/reservations/${reservation.id}/attendance`, { estado: state }),
    'Asistencia actualizada.'
  )

  return (
    <section className='booking-page booking-admin-page' aria-labelledby='booking-admin-title'>
      <header className='booking-heading'>
        <p className='booking-eyebrow'>Administración</p>
        <h1 id='booking-admin-title'>Ciclos y reservas</h1>
        <p>Configurá horarios semanales, generá clases y gestioná feriados y asistencia.</p>
      </header>
      {displayError && <p className='booking-message booking-message-error' role='alert'>{displayError}</p>}
      {notice && <p className='booking-message booking-message-success' role='status'>{notice}</p>}

      <div className='booking-admin-grid'>
        <section className='booking-section' aria-labelledby='schedules-title'>
          <div className='booking-section-heading'><div><p className='booking-eyebrow'>Base recurrente</p><h2 id='schedules-title'>Horarios semanales</h2></div></div>
          <form className='booking-admin-form' onSubmit={createSchedule}>
            <label>Día de la semana
              <select value={scheduleForm.dia_semana} onChange={event => setScheduleForm(current => ({ ...current, dia_semana: event.target.value }))}>
                {weekdays.map((day, index) => <option value={index} key={day}>{day}</option>)}
              </select>
            </label>
            <label>Hora de inicio<input type='time' value={scheduleForm.hora_inicio} onChange={event => setScheduleForm(current => ({ ...current, hora_inicio: event.target.value }))} required /></label>
            <label>Duración (min)<input type='number' min='1' max='1440' value={scheduleForm.duracion_min} onChange={event => setScheduleForm(current => ({ ...current, duracion_min: event.target.value }))} required /></label>
            <label>Cupo<input type='number' min='1' value={scheduleForm.cupo} onChange={event => setScheduleForm(current => ({ ...current, cupo: event.target.value }))} required /></label>
            <label>Nivel<input value={scheduleForm.nivel} onChange={event => setScheduleForm(current => ({ ...current, nivel: event.target.value }))} placeholder='Todos los niveles' /></label>
            <label>Profesor/a<input value={scheduleForm.profesor} onChange={event => setScheduleForm(current => ({ ...current, profesor: event.target.value }))} /></label>
            <button className='booking-button booking-button-primary booking-form-wide' type='submit' disabled={Boolean(saving)}>
              <BsPlusLg aria-hidden='true' /> {saving === (editingScheduleId ? `schedule-update-${editingScheduleId}` : 'schedule-create') ? 'Guardando...' : editingScheduleId ? 'Guardar horario' : 'Agregar horario'}
            </button>
            {editingScheduleId && <button className='booking-text-button booking-form-wide' type='button' onClick={() => { setEditingScheduleId(null); setScheduleForm(blankSchedule) }}>Cancelar edición</button>}
          </form>
          <div className='booking-admin-list'>
            {schedules.map(schedule => (
              <div className='booking-admin-list-row' key={schedule.id}>
                <div><strong>{weekdays[schedule.dia_semana]} · {schedule.hora_inicio.slice(0, 5)}</strong><span>{schedule.duracion_min} min · {schedule.cupo} lugares · {schedule.nivel || 'Todos los niveles'} · {schedule.profesor || 'Sin profesor asignado'}</span></div>
                <div className='booking-row-actions'>
                  <button className='booking-text-button' type='button' disabled={Boolean(saving)} onClick={() => editSchedule(schedule)}>Editar</button>
                  {schedule.activo
                    ? <button className='booking-text-button booking-text-button-danger' type='button' disabled={Boolean(saving)} onClick={() => deactivateSchedule(schedule)}>Desactivar</button>
                    : <button className='booking-text-button' type='button' disabled={Boolean(saving)} onClick={() => runAction(`schedule-${schedule.id}`, () => axios.patch(`api/booking/admin/schedules/${schedule.id}`, { activo: true }), 'Horario activado.')}>Activar</button>}
                </div>
                {!schedule.activo && <span className='booking-status booking-status-inactivo'>Inactivo</span>}
              </div>
            ))}
          </div>
        </section>

        <section className='booking-section' aria-labelledby='cycles-title'>
          <div className='booking-section-heading'><div><p className='booking-eyebrow'>Período de reserva</p><h2 id='cycles-title'>Ciclos</h2></div></div>
          <form className='booking-admin-form' onSubmit={createCycle}>
            <label className='booking-form-wide'>Nombre del ciclo<input value={cycleForm.nombre} onChange={event => setCycleForm(current => ({ ...current, nombre: event.target.value }))} required maxLength='255' /></label>
            <label>Fecha de inicio<input type='date' value={cycleForm.fecha_inicio} onChange={event => setCycleForm(current => ({ ...current, fecha_inicio: event.target.value }))} required /></label>
            <label>Fecha de fin<input type='date' min={cycleForm.fecha_inicio} value={cycleForm.fecha_fin} onChange={event => setCycleForm(current => ({ ...current, fecha_fin: event.target.value }))} required /></label>
            <label>Clases por semana<input type='number' min='1' max='7' value={cycleForm.clases_por_semana} onChange={event => setCycleForm(current => ({ ...current, clases_por_semana: event.target.value }))} required /></label>
            <label>Precio del ciclo<input type='number' min='0' step='0.01' value={cycleForm.precio} onChange={event => setCycleForm(current => ({ ...current, precio: event.target.value }))} required /></label>
            <button className='booking-button booking-button-primary booking-form-wide' type='submit' disabled={Boolean(saving)}>
              <BsPlusLg aria-hidden='true' /> {saving === (editingCycleId ? `cycle-update-${editingCycleId}` : 'cycle-create') ? 'Guardando...' : editingCycleId ? 'Guardar ciclo' : 'Crear ciclo'}
            </button>
            {editingCycleId && <button className='booking-text-button booking-form-wide' type='button' onClick={() => { setEditingCycleId(null); setCycleForm(blankCycle) }}>Cancelar edición</button>}
          </form>
          <div className='booking-admin-list'>
            {cycles.map(cycle => (
              <div className='booking-admin-list-row' key={cycle.id}>
                <div><strong>{cycle.nombre}</strong><span>{formatDate(cycle.fecha_inicio)} a {formatDate(cycle.fecha_fin)} · {cycle.clases_por_semana} días/semana · ${Number(cycle.precio).toLocaleString('es-AR')}</span><small>{cycle.clases_count} clases · {cycle.inscripciones_count} inscripciones</small></div>
                <div className='booking-row-actions'>
                  <button className='booking-text-button' type='button' disabled={Boolean(saving)} onClick={() => editCycle(cycle)}>Editar</button>
                  <button className='booking-text-button' type='button' disabled={Boolean(saving)} onClick={() => generateCycle(cycle)}>{saving === `generate-${cycle.id}` ? 'Generando...' : 'Generar clases'}</button>
                  <button className='booking-text-button' type='button' disabled={Boolean(saving)} onClick={() => toggleCycle(cycle)}>{cycle.activo ? 'Pausar' : 'Activar'}</button>
                  <button className='booking-text-button booking-text-button-danger' type='button' disabled={Boolean(saving) || cycle.clases_count > 0 || cycle.inscripciones_count > 0} onClick={() => deleteCycle(cycle)}>Eliminar</button>
                </div>
              </div>
            ))}
          </div>
        </section>

        <section className='booking-section' aria-labelledby='holidays-title'>
          <div className='booking-section-heading'><div><p className='booking-eyebrow'>Calendario</p><h2 id='holidays-title'>Feriados</h2><p className='booking-muted'>Cargalos antes de generar las clases; las fechas ya generadas no se modifican.</p></div></div>
          <form className='booking-admin-form booking-holiday-form' onSubmit={createHoliday}>
            <label>Fecha<input type='date' value={holidayForm.fecha} onChange={event => setHolidayForm(current => ({ ...current, fecha: event.target.value }))} required /></label>
            <label>Descripción<input value={holidayForm.descripcion} onChange={event => setHolidayForm(current => ({ ...current, descripcion: event.target.value }))} maxLength='255' /></label>
            <button className='booking-button booking-button-primary' type='submit' disabled={saving === (editingHolidayId ? `holiday-update-${editingHolidayId}` : 'holiday-create')}>
              {editingHolidayId ? 'Guardar cambios' : 'Agregar feriado'}
            </button>
            {editingHolidayId && <button className='booking-text-button' type='button' onClick={() => { setEditingHolidayId(null); setHolidayForm(blankHoliday) }}>Cancelar edición</button>}
          </form>
          <div className='booking-admin-list'>
            {holidays.map(holiday => (
              <div className='booking-admin-list-row' key={holiday.id}>
                <div><strong>{formatDate(holiday.fecha)}</strong><span>{holiday.descripcion || 'Sin descripción'}</span></div>
                <div className='booking-row-actions'>
                  <button className='booking-text-button' type='button' disabled={Boolean(saving)} onClick={() => editHoliday(holiday)}>Editar</button>
                  <button className='booking-text-button booking-text-button-danger' type='button' disabled={Boolean(saving)} onClick={() => removeHoliday(holiday)}>Quitar</button>
                </div>
              </div>
            ))}
            {!holidays.length && <p className='booking-muted'>No hay feriados cargados.</p>}
          </div>
        </section>
      </div>

      <section className='booking-section booking-classes-section' aria-labelledby='classes-title'>
        <div className='booking-section-heading'><div><p className='booking-eyebrow'>Operación diaria</p><h2 id='classes-title'>Clases generadas</h2></div><span className='booking-count'>{classes.length} clases</span></div>
        <div className='booking-class-filters'>
          <label>Fecha<input type='date' value={classFilters.fecha} onChange={event => setClassFilters(current => ({ ...current, fecha: event.target.value }))} /></label>
          <label>Ciclo<select value={classFilters.ciclo_id} onChange={event => setClassFilters(current => ({ ...current, ciclo_id: event.target.value }))}>
            <option value=''>Todos los ciclos</option>
            {cycles.map(cycle => <option value={cycle.id} key={cycle.id}>{cycle.nombre}</option>)}
          </select></label>
          {(classFilters.fecha || classFilters.ciclo_id) && <button className='booking-text-button' type='button' onClick={() => setClassFilters({ fecha: '', ciclo_id: '' })}>Limpiar filtros</button>}
        </div>
        {loading ? <p className='booking-muted'>Cargando agenda...</p> : classes.length === 0 ? <p className='booking-empty'>Generá las clases desde un ciclo para gestionarlas acá.</p> : (
          <div className='booking-class-list'>
            {classes.map(classItem => {
              const available = Math.max(0, classItem.cupo - classItem.ocupados)
              const classReservations = (classItem.reservas || []).filter(reservation => ['reservada', 'asistio', 'falto'].includes(reservation.estado))
              return (
                <article className='booking-admin-class' key={classItem.id}>
                  <div className='booking-admin-class-main'>
                    <strong>{formatClassDate(classItem.fecha)} · {classItem.hora_inicio.slice(0, 5)}</strong>
                    <span>{classItem.ciclo?.nombre} · {classItem.duracion_min} min</span>
                    <small>{classItem.profesor || 'Sin profesor'} · {classItem.nivel || 'Todos los niveles'} · {available}/{classItem.cupo} lugares libres</small>
                  </div>
                  <span className={`booking-status booking-status-${classItem.estado}`}>{classItem.estado}</span>
                  <div className='booking-row-actions'>
                    {classItem.estado === 'programada' && <>
                      <button className='booking-text-button booking-text-button-danger' type='button' disabled={Boolean(saving)} onClick={() => cancelClass(classItem)}><BsSlashCircle aria-hidden='true' /> Cancelar</button>
                      {classItem.puede_completarse && <button className='booking-text-button' type='button' disabled={Boolean(saving)} onClick={() => completeClass(classItem)}><BsCheck2Circle aria-hidden='true' /> Realizada</button>}
                    </>}
                  </div>
                  <details className='booking-class-roster'>
                    <summary>Alumnos y reservas ({classReservations.length})</summary>
                    <div className='booking-attendance-list'>
                      {classReservations.length === 0 && <p className='booking-muted'>No hay alumnos con reservas activas.</p>}
                      {classReservations.map(reservation => (
                        <div className='booking-attendance-row' key={reservation.id}>
                          <span>{reservation.yoguini?.nombre} {reservation.yoguini?.apellido} · {reservation.estado}</span>
                          {classItem.estado === 'realizada' && reservation.estado === 'reservada' && <span className='booking-row-actions'>
                            <button className='booking-text-button' type='button' disabled={Boolean(saving)} onClick={() => setAttendance(reservation, 'asistio')}>Asistió</button>
                            <button className='booking-text-button booking-text-button-danger' type='button' disabled={Boolean(saving)} onClick={() => setAttendance(reservation, 'falto')}>Faltó</button>
                          </span>}
                        </div>
                      ))}
                    </div>
                  </details>
                </article>
              )
            })}
          </div>
        )}
      </section>
    </section>
  )
}

export default BookingAdmin
