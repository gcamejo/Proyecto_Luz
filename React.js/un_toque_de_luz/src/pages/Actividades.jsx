import React, { useEffect } from 'react'
import CardActividades from '../components/CardActividades'

import '../Styles/Actividades.css'

import { useDispatch, useSelector } from 'react-redux'
import { getActividades } from '../store/Slice/actividades/actividades'
import { Link } from 'react-router-dom'
import {  BsPlusCircleFill } from 'react-icons/bs'



const Actividades = ({admin}) => {
  
  const dispatch = useDispatch()
    
  const {actividades} = useSelector((estado => estado.actividades))
  
  useEffect(() => {
    dispatch(getActividades())
  },[])

  
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

        {actividades.length > 0 ? <div className='actividades-grid'>
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