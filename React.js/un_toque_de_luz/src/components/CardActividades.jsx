import React from 'react'
import { BsPencilSquare, BsTrash, BsWhatsapp } from 'react-icons/bs'
import { Link, Route, useNavigate } from 'react-router-dom'
import { deleteActividad, getActividad } from '../store/Slice/actividades/actividades'

import '../Styles/Actividades.css'

import { useDispatch, useSelector } from 'react-redux'



const CardActividades = ({id, name, tit, des, hor, edit}) => {

const dispatch = useDispatch()

const modalId = `actividad-${id}-modal`
const modalLabelId = `actividad-${id}-modal-label`

const {actividad} = useSelector(estado => estado.actividad)

const urlImagen = 'http://localhost:8000/storage/img/' + name

const navigate = useNavigate()

const borrarActividad = (e) =>{
  e.preventDefault()
  try {
    deleteActividad(actividad.id)
  } catch (error) {
  console.error(error)  
  }
  navigate('/adminActividades')
  
}

const handleShow = (id) => {
  dispatch(getActividad(id))
}

  return (
    <>
      <article className='actividades-card'>
        <img className='actividades-image' src={urlImagen} alt={`Imagen de la actividad ${tit}`} />
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
              <button className='actividades-action actividades-action-delete' type='button' data-bs-toggle='modal' data-bs-target={`#${modalId}`} onClick={() => handleShow(id)} aria-label={`Eliminar actividad ${tit}`}>
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
      <div className='modal fade' id={modalId} tabIndex='-1' aria-labelledby={modalLabelId} aria-hidden='true'>
              <div className="modal-dialog">
                  <div className="modal-content">
                  <div className="modal-header">
                      <h2 className="modal-title fs-5" id={modalLabelId}>Atención</h2>
                      <button type="button" className="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div className="modal-body">
                          <h6>Esta por borrar la actividad:</h6>
                          <h6>{actividad.titulo}</h6>
                          <h6>{actividad.descripcion} </h6>
                          <h6>{actividad.horarios}</h6>
                          
                  </div>
                  <div className="modal-footer">
                      <button type="button" className="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                      <button type="button" className="btn btn-danger" data-bs-dismiss="modal" onClick={borrarActividad}>Borrar</button>
                  </div>
                  </div>
              </div>    
            </div>
      
    </>
  )
}

export default CardActividades