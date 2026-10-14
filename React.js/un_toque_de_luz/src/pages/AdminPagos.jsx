import React, { useState } from 'react'
import axios from '../api/axios'
import useSWR from 'swr'
import fetcher from '../api/fetcher'
import ActionModal from '../components/payments/ActionModal'
import { METODOS, StatusBadge, currentPeriod, formatDate, formatPeriod, requestError, todayBuenosAires } from '../components/payments/shared'
import { formatMoney, fromCents, splitPayment, toCents } from '../utils/money'
import '../Styles/Reservas.css'
import '../Styles/Pagos.css'

const fetchWithParams = async ([url, params]) => {
  const { data } = await axios.get(url, { params })
  return data
}

const studentName = alumno => `${alumno?.apellido || ''} ${alumno?.nombre || ''}`.trim()
const metodoOptions = Object.entries(METODOS).map(([value, label]) => ({ value, label }))

const PaymentModal = ({ userId, cargo, onSubmit, onClose }) => {
  const { data: account } = useSWR(`api/payments/admin/students/${userId}`, fetcher)
  const otherBalances = (account?.cargos || [])
    .filter(item => item.estado !== 'anulado' && item.id !== cargo?.id && toCents(item.saldo) > 0)
    .sort((a, b) => a.periodo.localeCompare(b.periodo))
    .map(item => toCents(item.saldo))
  const fields = [
    { name: 'monto', label: 'Monto', type: 'money', required: true, defaultValue: cargo ? cargo.saldo : '' },
    { name: 'fecha_pago', label: 'Fecha de pago', type: 'date', required: true, defaultValue: todayBuenosAires(), max: todayBuenosAires() },
    { name: 'metodo', label: 'Método', type: 'select', options: metodoOptions, defaultValue: 'efectivo' },
    { name: 'referencia', label: 'Referencia (opcional)', type: 'text' },
    { name: 'nota', label: 'Nota (opcional)', type: 'textarea', maxLength: 500 },
  ]
  if (cargo) fields.push({ name: 'aplicar_excedente', label: 'Aplicar el excedente a otros cargos pendientes', type: 'checkbox' })

  const renderExtra = values => {
    const amountCents = toCents(values.monto)
    if (amountCents === null || amountCents <= 0) return null
    const split = splitPayment({
      amountCents,
      chargeBalanceCents: cargo ? toCents(cargo.saldo) : 0,
      spill: Boolean(values.aplicar_excedente),
      otherBalancesCents: otherBalances,
    })
    if (!cargo) return <p className='payment-surplus'>Sin cargo elegido: {formatMoney(split.credit)} quedarán como saldo a favor del alumno.</p>
    if (split.credit === 0 && split.toOthers === 0) return <p className='payment-surplus'>Se aplicarán {formatMoney(split.toCharge)} al cargo.</p>
    return (
      <p className='payment-surplus' role='status'>
        Se aplicarán {formatMoney(split.toCharge)} al cargo
        {split.toOthers > 0 && <>, {formatMoney(split.toOthers)} a otros cargos pendientes</>} y {formatMoney(split.credit)} quedarán como saldo a favor.
      </p>
    )
  }

  return (
    <ActionModal
      title={cargo ? `Registrar pago: ${cargo.concepto}` : 'Registrar pago a cuenta'}
      message={cargo ? `Saldo del cargo: ${formatMoney(cargo.saldo)}` : 'El pago quedará como saldo a favor hasta que lo apliques a un cargo.'}
      fields={fields}
      renderExtra={renderExtra}
      submitLabel='Registrar pago'
      onSubmit={values => onSubmit({
        user_id: userId,
        monto: values.monto.trim().replace(',', '.'),
        fecha_pago: values.fecha_pago,
        metodo: values.metodo,
        referencia: values.referencia.trim() || null,
        nota: values.nota.trim() || null,
        ...(cargo ? { cargo_id: cargo.id, aplicar_excedente: Boolean(values.aplicar_excedente) } : {}),
      })}
      onClose={onClose}
    />
  )
}

const NewChargeModal = ({ periodo, fixedStudent, onSubmit, onClose }) => {
  const { data: students } = useSWR(fixedStudent ? null : 'api/yoguinis', fetcher)
  const studentList = Array.isArray(students) ? students : students?.data || []
  const fields = [
    fixedStudent
      ? null
      : {
        name: 'user_id',
        label: 'Alumno',
        type: 'select',
        required: true,
        options: [{ value: '', label: students ? 'Elegí un alumno' : 'Cargando alumnos...' }, ...studentList.map(student => ({ value: String(student.id), label: studentName(student) || student.email }))],
      },
    { name: 'concepto', label: 'Concepto', type: 'text', required: true },
    { name: 'periodo', label: 'Mes', type: 'month', required: true, defaultValue: periodo },
    { name: 'monto_original', label: 'Monto', type: 'money', required: true },
    { name: 'vence_el', label: 'Vencimiento (opcional)', type: 'date' },
  ].filter(Boolean)

  return (
    <ActionModal
      title='Nuevo cargo manual'
      message={fixedStudent ? `Alumno: ${studentName(fixedStudent)}` : null}
      fields={fields}
      submitLabel='Crear cargo'
      onSubmit={values => onSubmit({
        user_id: fixedStudent ? fixedStudent.id : Number(values.user_id),
        concepto: values.concepto.trim(),
        periodo: values.periodo,
        monto_original: values.monto_original.trim().replace(',', '.'),
        ...(values.vence_el ? { vence_el: values.vence_el } : {}),
      })}
      onClose={onClose}
    />
  )
}

const StudentDetail = ({ userId, onAction, onClose }) => {
  const { data, error, isLoading } = useSWR(`api/payments/admin/students/${userId}`, fetcher)
  if (isLoading) return <section className='payment-detail'><p className='booking-message' role='status'>Cargando cuenta del alumno...</p></section>
  if (error || !data) return <section className='payment-detail'><p className='booking-message booking-message-error' role='alert'>{error ? requestError(error) : 'No se pudo cargar la cuenta.'}</p></section>

  const credit = toCents(data.saldo_a_favor) || 0
  const alumno = data.alumno

  return (
    <section className='payment-detail' aria-labelledby='payment-detail-title'>
      <div className='booking-section-heading'>
        <h3 id='payment-detail-title'>Cuenta de {studentName(alumno)}</h3>
        <div className='payment-table-actions'>
          <button type='button' className='booking-button' onClick={() => onAction({ type: 'payment', userId })}>Registrar pago a cuenta</button>
          <button type='button' className='booking-button' onClick={() => onAction({ type: 'charge', student: alumno })}>Nuevo cargo</button>
          <button type='button' className='booking-text-button' onClick={onClose}>Cerrar</button>
        </div>
      </div>
      <p><strong>Saldo a favor:</strong> {formatMoney(data.saldo_a_favor)}</p>
      {credit > 0 && <p className='booking-message' role='status'>El saldo a favor no se aplica solo: usá "Aplicar saldo a favor" en el cargo que corresponda.</p>}

      <h4>Saldo por mes</h4>
      <div className='payment-table-wrap'>
        <table className='payment-table'>
          <thead><tr><th>Mes</th><th className='payment-amount'>Facturado</th><th className='payment-amount'>Aplicado</th><th className='payment-amount'>Saldo</th></tr></thead>
          <tbody>
            {data.saldo_por_mes.length === 0 && <tr><td colSpan='4'>Sin movimientos.</td></tr>}
            {data.saldo_por_mes.map(row => (
              <tr key={row.periodo}><td>{formatPeriod(row.periodo)}</td><td className='payment-amount'>{formatMoney(row.facturado)}</td><td className='payment-amount'>{formatMoney(row.aplicado)}</td><td className='payment-amount'>{formatMoney(row.saldo)}</td></tr>
            ))}
          </tbody>
        </table>
      </div>

      <h4>Cargos</h4>
      <div className='payment-table-wrap'>
        <table className='payment-table'>
          <thead><tr><th>Mes</th><th>Concepto</th><th className='payment-amount'>Final</th><th className='payment-amount'>Saldo</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody>
            {data.cargos.length === 0 && <tr><td colSpan='6'>Sin cargos.</td></tr>}
            {data.cargos.map(cargo => {
              const open = cargo.estado === 'pendiente' || cargo.estado === 'parcial'
              return (
                <tr key={cargo.id}>
                  <td>{formatPeriod(cargo.periodo)}</td>
                  <td>{cargo.concepto}{cargo.motivo_descuento && <small className='booking-muted'> (descuento: {cargo.motivo_descuento})</small>}{cargo.inscripcion_baja && <span className='payment-drop-warning'>Inscripción dada de baja, revisar cargo.</span>}</td>
                  <td className='payment-amount'>{formatMoney(cargo.monto_final)}</td>
                  <td className='payment-amount'>{formatMoney(cargo.saldo)}</td>
                  <td><StatusBadge estado={cargo.estado} /></td>
                  <td>
                    <div className='payment-table-actions'>
                      {open && <button type='button' className='booking-text-button' onClick={() => onAction({ type: 'payment', userId, cargo })}>Registrar pago</button>}
                      {open && credit > 0 && <button type='button' className='booking-text-button' onClick={() => onAction({ type: 'credit', cargo, credit: data.saldo_a_favor })}>Aplicar saldo a favor</button>}
                      {cargo.estado !== 'anulado' && <button type='button' className='booking-text-button' onClick={() => onAction({ type: 'discount', cargo })}>Descuento</button>}
                      {cargo.estado !== 'anulado' && <button type='button' className='booking-text-button booking-text-button-danger' onClick={() => onAction({ type: 'voidCharge', cargo })}>Anular</button>}
                    </div>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <h4>Pagos</h4>
      <div className='payment-table-wrap'>
        <table className='payment-table'>
          <thead><tr><th>Fecha</th><th className='payment-amount'>Monto</th><th>Método</th><th>Aplicaciones</th><th className='payment-amount'>Sin aplicar</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody>
            {data.pagos.length === 0 && <tr><td colSpan='7'>Sin pagos.</td></tr>}
            {data.pagos.map(pago => (
              <tr key={pago.id}>
                <td>{formatDate(pago.fecha_pago)}</td>
                <td className='payment-amount'>{formatMoney(pago.monto)}</td>
                <td>{METODOS[pago.metodo] || pago.metodo}{pago.referencia && <small className='booking-muted'> ({pago.referencia})</small>}</td>
                <td>
                  {(pago.aplicaciones || []).filter(item => item.estado === 'vigente').map(item => (
                    <div key={item.id}>
                      {formatMoney(item.monto)} a {item.cargo?.concepto || `cargo #${item.cargo_id}`}{' '}
                      <button type='button' className='booking-text-button booking-text-button-danger' onClick={() => onAction({ type: 'unapply', application: item })}>Desaplicar</button>
                    </div>
                  ))}
                </td>
                <td className='payment-amount'>{pago.estado === 'vigente' ? formatMoney(pago.remanente) : '-'}</td>
                <td><StatusBadge estado={pago.estado} /></td>
                <td>{pago.estado === 'vigente' && <button type='button' className='booking-text-button booking-text-button-danger' onClick={() => onAction({ type: 'voidPayment', pago })}>Anular pago</button>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}

const AdminPagos = () => {
  const [periodo, setPeriodo] = useState(currentPeriod())
  const [estado, setEstado] = useState('')
  const [search, setSearch] = useState('')
  const [detailUserId, setDetailUserId] = useState(null)
  const [dialog, setDialog] = useState(null)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [generating, setGenerating] = useState(false)
  const [cycleId, setCycleId] = useState('')

  const params = Object.fromEntries(Object.entries({ periodo, estado, q: search.trim() }).filter(([, value]) => value))
  const summaryKey = ['api/payments/admin/summary', { periodo }]
  const { data: summary, error: summaryError, isLoading: summaryLoading, mutate: refreshSummary } = useSWR(summaryKey, fetchWithParams)
  const { data: charges = [], error: chargesError, isLoading: chargesLoading, mutate: refreshCharges } = useSWR(['api/payments/admin/charges', params], fetchWithParams)
  const { data: cycles = [] } = useSWR('api/booking/admin/cycles', fetcher)
  const { mutate: refreshDetail } = useSWR(detailUserId ? `api/payments/admin/students/${detailUserId}` : null, fetcher)

  const refreshAll = () => Promise.all([refreshSummary(), refreshCharges(), detailUserId ? refreshDetail() : null])
  const displayError = error || (chargesError ? requestError(chargesError) : summaryError ? requestError(summaryError) : '')
  const creditStudents = charges.filter(charge => toCents(charge.saldo_a_favor_alumno) > 0)

  // Ejecuta una acción de escritura; el modal muestra los errores y se cierra solo si salió bien.
  const perform = (request, success) => async () => {
    const response = await request()
    setNotice(typeof success === 'function' ? success(response.data) : success)
    setError('')
    await refreshAll()
  }

  const generate = async () => {
    if (!cycleId) return
    if (!window.confirm('Se crearán los cargos faltantes del ciclo para los alumnos inscriptos (los ya existentes se omiten). ¿Continuar?')) return
    setGenerating(true)
    setError('')
    setNotice('')
    try {
      const { data } = await axios.post(`api/payments/admin/cycles/${cycleId}/charges/generate`)
      setNotice(`Cargos creados: ${data.creados}. Omitidos (ya existían): ${data.omitidos}. Sin precio en el ciclo: ${data.sin_precio}.`)
      await refreshAll()
    } catch (requestErrorValue) {
      setError(requestError(requestErrorValue))
    } finally {
      setGenerating(false)
    }
  }

  const renderDialog = () => {
    if (!dialog) return null
    const close = () => setDialog(null)
    const base = 'api/payments/admin'

    switch (dialog.type) {
      case 'payment':
        return (
          <PaymentModal
            userId={dialog.userId}
            cargo={dialog.cargo}
            onClose={close}
            onSubmit={payload => perform(
              () => axios.post(`${base}/payments`, payload),
              data => data.saldo_a_favor_generado && toCents(data.saldo_a_favor_generado) > 0
                ? `Pago registrado. Quedaron ${formatMoney(data.saldo_a_favor_generado)} como saldo a favor del alumno.`
                : 'Pago registrado.'
            )()}
          />
        )
      case 'charge':
        return (
          <NewChargeModal
            periodo={periodo}
            fixedStudent={dialog.student}
            onClose={close}
            onSubmit={payload => perform(() => axios.post(`${base}/charges`, payload), 'Cargo creado.')()}
          />
        )
      case 'credit':
        return (
          <ActionModal
            title={`Aplicar saldo a favor: ${dialog.cargo.concepto}`}
            message={`Saldo a favor disponible: ${formatMoney(dialog.credit)}. Saldo del cargo: ${formatMoney(dialog.cargo.saldo)}.`}
            fields={[{ name: 'monto', label: 'Monto a aplicar', type: 'money', required: true, defaultValue: fromCents(Math.min(toCents(dialog.credit) || 0, toCents(dialog.cargo.saldo) || 0)) }]}
            submitLabel='Aplicar'
            onClose={close}
            onSubmit={values => perform(() => axios.post(`${base}/charges/${dialog.cargo.id}/apply-credit`, { monto: values.monto.trim().replace(',', '.') }), 'Saldo a favor aplicado.')()}
          />
        )
      case 'discount':
        return (
          <ActionModal
            title={`Descuento: ${dialog.cargo.concepto}`}
            message={`Monto original: ${formatMoney(dialog.cargo.monto_original)}. El descuento no puede dejar el cargo por debajo de lo ya aplicado.`}
            fields={[
              { name: 'descuento', label: 'Descuento total', type: 'money', required: true, defaultValue: dialog.cargo.descuento },
              { name: 'motivo', label: 'Motivo', type: 'textarea', required: true, maxLength: 500 },
            ]}
            submitLabel='Guardar descuento'
            onClose={close}
            onSubmit={values => perform(() => axios.patch(`${base}/charges/${dialog.cargo.id}/discount`, { descuento: values.descuento.trim().replace(',', '.'), motivo_descuento: values.motivo.trim() }), 'Descuento guardado.')()}
          />
        )
      case 'voidCharge':
        return (
          <ActionModal
            title={`Anular cargo: ${dialog.cargo.concepto}`}
            message='Solo se puede anular un cargo sin aplicaciones vigentes. Si tiene pagos aplicados, desaplicalos primero.'
            fields={[{ name: 'motivo', label: 'Motivo', type: 'textarea', required: true, maxLength: 500 }]}
            submitLabel='Anular cargo'
            danger
            onClose={close}
            onSubmit={values => perform(() => axios.patch(`${base}/charges/${dialog.cargo.id}/void`, { motivo: values.motivo.trim() }), 'Cargo anulado.')()}
          />
        )
      case 'voidPayment':
        return (
          <ActionModal
            title='Anular pago'
            message={(
              <>
                <p>Pago de {formatMoney(dialog.pago.monto)} del {formatDate(dialog.pago.fecha_pago)}. Se revertirán todas sus aplicaciones.</p>
                {(dialog.pago.cargos_afectados || []).length > 0 ? (
                  <>
                    <p>Cargos que volverán a tener saldo:</p>
                    <ul>{dialog.pago.cargos_afectados.map(item => <li key={item.cargo_id}>{item.concepto || `Cargo #${item.cargo_id}`}: {formatMoney(item.monto)}</li>)}</ul>
                  </>
                ) : <p>No tiene aplicaciones: no afecta ningún cargo.</p>}
              </>
            )}
            fields={[{ name: 'motivo', label: 'Motivo', type: 'textarea', required: true, maxLength: 500 }]}
            submitLabel='Anular pago'
            danger
            onClose={close}
            onSubmit={values => perform(() => axios.patch(`${base}/payments/${dialog.pago.id}/void`, { motivo: values.motivo.trim() }), 'Pago anulado.')()}
          />
        )
      case 'unapply':
        return (
          <ActionModal
            title='Desaplicar pago'
            message={`Se quitarán ${formatMoney(dialog.application.monto)} del cargo y volverán a quedar como saldo a favor del alumno.`}
            fields={[{ name: 'motivo', label: 'Motivo', type: 'textarea', required: true, maxLength: 500 }]}
            submitLabel='Desaplicar'
            danger
            onClose={close}
            onSubmit={values => perform(() => axios.patch(`${base}/applications/${dialog.application.id}/void`, { motivo: values.motivo.trim() }), 'Aplicación anulada; el monto volvió a saldo a favor.')()}
          />
        )
      default:
        return null
    }
  }

  return (
    <main className='booking-page' aria-labelledby='admin-pagos-title'>
      <header className='booking-heading'>
        <p className='booking-eyebrow'>Administración</p>
        <h1 id='admin-pagos-title'>Pagos de alumnos</h1>
        <p className='booking-muted'>Registro manual de cargos y pagos. No emite comprobantes ni cobra online.</p>
      </header>

      {displayError && <p className='booking-message booking-message-error' role='alert'>{displayError}</p>}
      {notice && <p className='booking-message booking-message-success' role='status'>{notice}</p>}
      {creditStudents.length > 0 && (
        <p className='booking-message' role='status'>
          Hay alumnos con saldo a favor en este mes ({creditStudents.map(charge => studentName(charge.alumno)).filter((name, index, all) => all.indexOf(name) === index).join(', ')}). Podés aplicarlo desde el detalle del alumno.
        </p>
      )}

      <div className='payment-toolbar'>
        <label>Mes<input type='month' value={periodo} onChange={event => event.target.value && setPeriodo(event.target.value)} /></label>
        <label>Estado
          <select value={estado} onChange={event => setEstado(event.target.value)}>
            <option value=''>Todos</option>
            <option value='pendiente'>Pendiente</option>
            <option value='parcial'>Parcial</option>
            <option value='pagado'>Pagado</option>
            <option value='anulado'>Anulado</option>
          </select>
        </label>
        <label>Buscar alumno<input type='search' value={search} onChange={event => setSearch(event.target.value)} placeholder='Nombre o email' /></label>
        <span className='payment-toolbar-spacer' />
        <button type='button' className='booking-button booking-button-primary' onClick={() => setDialog({ type: 'charge' })}>Nuevo cargo</button>
      </div>

      <div className='payment-toolbar'>
        <label>Ciclo
          <select value={cycleId} onChange={event => setCycleId(event.target.value)}>
            <option value=''>Elegí un ciclo</option>
            {cycles.map(cycle => <option key={cycle.id} value={cycle.id}>{cycle.nombre}</option>)}
          </select>
        </label>
        <button type='button' className='booking-button' onClick={generate} disabled={!cycleId || generating}>{generating ? 'Generando...' : 'Generar cargos faltantes'}</button>
      </div>

      <section className='booking-section' aria-label={`Resumen de ${formatPeriod(periodo)}`}>
        {summaryLoading ? <p className='booking-message' role='status'>Cargando resumen...</p> : summary && (
          <div className='payment-cards'>
            <div className='payment-card'><span>Facturado</span><strong>{formatMoney(summary.facturado)}</strong></div>
            <div className='payment-card'><span>Aplicado al período</span><strong>{formatMoney(summary.aplicado_al_periodo)}</strong></div>
            <div className='payment-card'><span>Pendiente</span><strong>{formatMoney(summary.pendiente)}</strong></div>
            <div className='payment-card'><span>Cobrado en el mes</span><strong>{formatMoney(summary.cobrado_en_mes)}</strong></div>
            <div className='payment-card'><span>Saldo a favor total</span><strong>{formatMoney(summary.saldo_a_favor_total)}</strong></div>
          </div>
        )}
      </section>

      <section className='booking-section'>
        <div className='booking-section-heading'><h2>Cargos de {formatPeriod(periodo)}</h2></div>
        {chargesLoading ? <p className='booking-message' role='status'>Cargando cargos...</p> : charges.length === 0 ? <p className='booking-empty'>No hay cargos para este filtro.</p> : (
          <div className='payment-table-wrap'>
            <table className='payment-table'>
              <thead><tr><th>Alumno</th><th>Concepto</th><th className='payment-amount'>Monto</th><th className='payment-amount'>Aplicado</th><th className='payment-amount'>Saldo</th><th>Estado</th><th>Acciones</th></tr></thead>
              <tbody>
                {charges.map(charge => (
                  <tr key={charge.id}>
                    <td>
                      <button type='button' className='payment-link' onClick={() => setDetailUserId(charge.user_id)}>{studentName(charge.alumno) || `Alumno #${charge.user_id}`}</button>
                      {toCents(charge.saldo_a_favor_alumno) > 0 && <span className='payment-credit-badge'>Saldo a favor {formatMoney(charge.saldo_a_favor_alumno)}</span>}
                    </td>
                    <td>{charge.concepto}{charge.inscripcion_baja && <span className='payment-drop-warning'>Inscripción dada de baja, revisar cargo.</span>}</td>
                    <td className='payment-amount'>{formatMoney(charge.monto_final)}</td>
                    <td className='payment-amount'>{formatMoney(charge.aplicado)}</td>
                    <td className='payment-amount'>{formatMoney(charge.saldo)}</td>
                    <td><StatusBadge estado={charge.estado} /></td>
                    <td>
                      <div className='payment-table-actions'>
                        {(charge.estado === 'pendiente' || charge.estado === 'parcial') && (
                          <button type='button' className='booking-text-button' onClick={() => setDialog({ type: 'payment', userId: charge.user_id, cargo: charge })}>Registrar pago</button>
                        )}
                        <button type='button' className='booking-text-button' onClick={() => setDetailUserId(charge.user_id)}>Ver cuenta</button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {detailUserId && <StudentDetail userId={detailUserId} onAction={setDialog} onClose={() => setDetailUserId(null)} />}
      {renderDialog()}
    </main>
  )
}

export default AdminPagos
