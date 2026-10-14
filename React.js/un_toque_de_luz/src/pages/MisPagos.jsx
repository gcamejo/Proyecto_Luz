import React from 'react'
import useSWR from 'swr'
import fetcher from '../api/fetcher'
import { METODOS, StatusBadge, formatDate, formatPeriod, requestError } from '../components/payments/shared'
import { formatMoney, toCents } from '../utils/money'
import '../Styles/Reservas.css'
import '../Styles/Pagos.css'

const MisPagos = () => {
  const { data, error, isLoading } = useSWR('api/payments/me', fetcher)

  return (
    <main className='booking-page' aria-labelledby='mis-pagos-title'>
      <header className='booking-heading'>
        <p className='booking-eyebrow'>Mi cuenta</p>
        <h1 id='mis-pagos-title'>Mis pagos</h1>
        <p className='booking-muted'>Consulta de tus cargos y pagos registrados por la administración.</p>
      </header>

      {isLoading && <p className='booking-message' role='status'>Cargando tus pagos...</p>}
      {error && <p className='booking-message booking-message-error' role='alert'>{requestError(error)}</p>}

      {data && (
        <>
          {toCents(data.saldo_a_favor) > 0 && (
            <p className='booking-message booking-message-success' role='status'>Tenés {formatMoney(data.saldo_a_favor)} de saldo a favor.</p>
          )}

          <section className='booking-section'>
            <div className='booking-section-heading'><h2>Saldo por mes</h2></div>
            {data.saldo_por_mes.length === 0 ? <p className='booking-empty'>Todavía no tenés cargos.</p> : (
              <div className='payment-table-wrap'>
                <table className='payment-table'>
                  <thead><tr><th>Mes</th><th className='payment-amount'>Facturado</th><th className='payment-amount'>Pagado</th><th className='payment-amount'>Saldo</th></tr></thead>
                  <tbody>
                    {data.saldo_por_mes.map(row => (
                      <tr key={row.periodo}><td>{formatPeriod(row.periodo)}</td><td className='payment-amount'>{formatMoney(row.facturado)}</td><td className='payment-amount'>{formatMoney(row.aplicado)}</td><td className='payment-amount'>{formatMoney(row.saldo)}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          <section className='booking-section'>
            <div className='booking-section-heading'><h2>Cargos</h2></div>
            {data.cargos.length === 0 ? <p className='booking-empty'>No hay cargos registrados.</p> : (
              <div className='payment-table-wrap'>
                <table className='payment-table'>
                  <thead><tr><th>Mes</th><th>Concepto</th><th className='payment-amount'>Monto</th><th className='payment-amount'>Saldo</th><th>Estado</th></tr></thead>
                  <tbody>
                    {data.cargos.map(cargo => (
                      <tr key={cargo.id}>
                        <td>{formatPeriod(cargo.periodo)}</td>
                        <td>{cargo.concepto}</td>
                        <td className='payment-amount'>{formatMoney(cargo.monto_final)}</td>
                        <td className='payment-amount'>{formatMoney(cargo.saldo)}</td>
                        <td><StatusBadge estado={cargo.estado} /></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          <section className='booking-section'>
            <div className='booking-section-heading'><h2>Pagos registrados</h2></div>
            {data.pagos.length === 0 ? <p className='booking-empty'>No hay pagos registrados.</p> : (
              <div className='payment-table-wrap'>
                <table className='payment-table'>
                  <thead><tr><th>Fecha</th><th className='payment-amount'>Monto</th><th>Método</th><th>Estado</th></tr></thead>
                  <tbody>
                    {data.pagos.map(pago => (
                      <tr key={pago.id}>
                        <td>{formatDate(pago.fecha_pago)}</td>
                        <td className='payment-amount'>{formatMoney(pago.monto)}</td>
                        <td>{METODOS[pago.metodo] || pago.metodo}</td>
                        <td><StatusBadge estado={pago.estado} /></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </main>
  )
}

export default MisPagos
