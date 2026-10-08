import React, { useState } from 'react'
import { BsPencilSquare, BsTrash, BsWhatsapp } from 'react-icons/bs'
import { Link } from 'react-router-dom'
import { deleteActividad } from '../store/Slice/actividades/actividades'
import { apiAssetUrl } from '../api/axios'

import '../Styles/Actividades.css'

import { useDispatch } from 'react-redux'



const CardActividades = ({id, name, tit, des, hor, edit}) => {

const dispatch = useDispatch()
const [confirmingDelete, setConfirmingDelete] = useState(false)
const [deleting, setDeleting] = useState(false)
const [deleteError, setDeleteError] = useState(null)

const urlImagen = apiAssetUrl(`storage/img/${encodeURIComponent(name)}`)

const borrarActividad = async (e) =>{
  e.preventDefault()
  if (deleting) return
  setDeleting(true)
  setDeleteError(null)
  try {
    await dispatch(deleteActividad(id))
  } catch (error) {
    setDeleteError(error.response?.data?.message || error.message || 'No se pudo eliminar la actividad.')
    setDeleting(false)
  }
}

  return (
    <>
      <article className='actividades-card'>
        <img className='actividades-image' src={urlImagen} alt={`Imagen de la actividad ${tit}`} loading='lazy' decoding='async' />
        <div className='actividades-card-content'>
          <h2 className='actividades-card-title'>{tit}</h2>
          <p className='actividades-description'>{des}</p>
          <div className='actividades-schedule'>
            <span>Horarios</span>
            <p>{hor}</p>
          </div>
          <div className='actividades-actions'>
            {edit ? <>
              <Link className='actividades-action actividades-action-edit' to={`/editarActividad/${id}`}>
                <BsPencilSquare aria-hidden='true' />
                <span>Editar</span>
              </Link>
              <button className='actividades-action actividades-action-delete' type='button' onClick={() => {setDeleteError(null); setConfirmingDelete(true)}} aria-label={`Eliminar actividad ${tit}`}>
                <BsTrash aria-hidden='true' />
                <span>Eliminar</span>
              </button>
            </> : <Link className='actividades-action actividades-action-book' to='/'>
              <BsWhatsapp aria-hidden='true' />
              <span>Solicitar turno</span>
            </Link>}
          </div>
        </div>
      </article>
      {confirmingDelete && <div className='actividad-delete-overlay'>
        <section className='actividad-delete-dialog' role='dialog' aria-modal='true' aria-labelledby={`actividad-${id}-delete-title`} aria-describedby={`actividad-${id}-delete-description`}>
          <h2 id={`actividad-${id}-delete-title`}>Eliminar actividad</h2>
          <div className='actividad-delete-details'>
            {name && <img src={urlImagen} alt={`Imagen de la actividad ${tit}`} />}
            <div>
              <h3>{tit}</h3>
              <p id={`actividad-${id}-delete-description`}>{des}</p>
              <p><strong>Horarios:</strong> {hor}</p>
            </div>
          </div>
          {deleteError && <p className='actividad-delete-error' role='alert'>{deleteError}</p>}
          {deleting && <p className='actividad-delete-status' role='status'>Eliminando actividad...</p>}
          <div className='actividad-delete-actions'>
            <button type='button' className='actividad-form-cancel' onClick={() => setConfirmingDelete(false)} disabled={deleting} autoFocus>Cancelar</button>
            <button type='button' className='actividad-delete-confirm' onClick={borrarActividad} disabled={deleting}>
              {deleting ? 'Eliminando...' : 'Confirmar eliminación'}
            </button>
          </div>
        </section>
      </div>}
      
    </>
  )
}

export default CardActividades