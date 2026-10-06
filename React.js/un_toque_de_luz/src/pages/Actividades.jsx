import React, { useEffect } from 'react'
import CardActividades from '../components/CardActividades'

import '../Styles/Actividades.css'

import { useDispatch, useSelector } from 'react-redux'
import { getActividades } from '../store/Slice/actividades/actividades'
import { Link } from 'react-router-dom'
import {  BsPlusCircleFill } from 'react-icons/bs'



const Actividades = ({admin}) => {
  
  const dispatch = useDispatch()
    
  const {actividades, isLoading, error} = useSelector((estado => estado.actividades))
  
  useEffect(() => {
    dispatch(getActividades())
  },[dispatch])

  
  return (
    <>
    <section className='actividades-page'>
        <header className='actividades-header'>
          <div>
            <p className='actividades-eyebrow'>Un toque de luz</p>
            <h1>Actividades</h1>
            <p className='actividades-intro'>Encontrá una práctica para regalarte un momento de bienestar.</p>
          </div>
          {admin && <Link className='actividades-create' to='/nuevaActividad'>
            <BsPlusCircleFill aria-hidden='true' />
            <span>Nueva actividad</span>
          </Link>}
        </header>

        {isLoading ? (
          <div className='actividades-loading' role='status' aria-live='polite'>
            <div className='actividades-loading-label'>
              <span className='actividades-loading-indicator' aria-hidden='true' />
              <span>Cargando actividades...</span>
            </div>
            <div className='actividades-grid actividades-loading-grid' aria-hidden='true'>
              {Array.from({length: 3}, (_, index) => (
                <article className='actividades-loading-card' key={index}>
                  <div className='actividades-skeleton actividades-skeleton-image' />
                  <div className='actividades-loading-content'>
                    <div className='actividades-skeleton actividades-skeleton-title' />
                    <div className='actividades-skeleton actividades-skeleton-line' />
                    <div className='actividades-skeleton actividades-skeleton-line actividades-skeleton-line-short' />
                    <div className='actividades-skeleton actividades-skeleton-schedule' />
                  </div>
                </article>
              ))}
            </div>
          </div>
        ) : error ? <p className='actividades-empty' role='alert'>{error}</p> : actividades.length > 0 ? <div className='actividades-grid'>
          {actividades.map(actividad => (
            <CardActividades
              key={actividad.id}
              id={actividad.id}
              name={actividad.urlImagen}
              tit={actividad.titulo}
              des={actividad.descripcion}
              hor={actividad.horarios}
              edit={admin}
            />
          ))}
        </div> : <p className='actividades-empty'>Próximamente vas a encontrar nuevas actividades.</p>}
    </section>
    
   </>
  )
}

export default Actividades