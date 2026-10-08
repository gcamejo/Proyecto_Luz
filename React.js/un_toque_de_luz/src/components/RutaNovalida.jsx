import React from 'react'
import { Link } from 'react-router-dom'
import { useSelector } from 'react-redux'
import { BsArrowRight, BsHouseDoor, BsCalendar2Week } from 'react-icons/bs'
import '../Styles/RutaNovalida.css'

const RutaNovalida = () => {
  const token = useSelector(state => state.token.token)

  return (
    <section className='route-not-found' aria-labelledby='route-not-found-title'>
      <div className='route-not-found-identity'>
        <span className='route-not-found-code'>404</span>
        <div className='route-not-found-brand'>
          <img src='/img/logo_principal.jpg' alt='' width='56' height='56' />
          <span>Un toque de Luz</span>
        </div>
      </div>
      <div className='route-not-found-content'>
        <p className='route-not-found-eyebrow'>{token ? 'Página no encontrada' : 'Acceso al sitio'}</p>
        <h1 id='route-not-found-title'>{token ? 'No encontramos esta página.' : 'Este espacio todavía no está disponible.'}</h1>
        <p className='route-not-found-description'>
          {token
            ? 'El enlace puede estar desactualizado o la dirección no existe. Volvé al inicio y seguí desde allí.'
            : 'Iniciá sesión para continuar o crea tu cuenta para empezar.'}
        </p>
        <div className='route-not-found-actions'>
          <Link className='route-not-found-primary' to={token ? '/' : '/login'}>
            {token ? <BsHouseDoor aria-hidden='true' /> : <BsArrowRight aria-hidden='true' />}
            {token ? 'Volver al inicio' : 'Iniciar sesión'}
          </Link>
          {token ? (
            <Link className='route-not-found-secondary' to='/actividades'>
              <BsCalendar2Week aria-hidden='true' /> Ver actividades
            </Link>
          ) : (
            <Link className='route-not-found-secondary' to='/cargarFicha'>Crear una cuenta</Link>
          )}
        </div>
      </div>
    </section>
  )
}

export default RutaNovalida