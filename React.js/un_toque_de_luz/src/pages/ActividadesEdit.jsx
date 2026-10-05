import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { getActividad, updateActividad } from '../store/Slice/actividades/actividades'
import { useNavigate, useParams } from 'react-router'
import { useForm } from '../hooks/useForm'
import { Link } from 'react-router-dom'



import '../Styles/Actividades.css'

const ActividadesEdit = () => {
  
  const {id} = useParams()
  
  const dispatch = useDispatch()
  
  const navigate = useNavigate()
  
  useEffect(() => {
    dispatch(getActividad(id))
  },[])
  
  const {actividad} = useSelector((estado => estado.actividad))
  
  const [values,handleInputChange, setValues] = useForm({
    urlImagen:'',
    titulo:'',
    descripcion:'',
    horarios:''
  })
  
  const {urlImagen, titulo, descripcion, horarios} = values
  
  const img = 'http://localhost:8000/storage/img/'+urlImagen
  
  useEffect(()=>{
      if(id == actividad.id){
        setValues({
          titulo:actividad.titulo,
          descripcion:actividad.descripcion,
          horarios:actividad.horarios
        })
      }
  },[actividad])

  const handleSubmit = (e) =>{
    e.preventDefault()
      dispatch(updateActividad(id, values))
      navigate('/adminActividades')
  }
  

  return (
    <>
    <div className='container-actividades'>
        <div className='titulo-actividades'>
          <h3>Editar Actividad</h3>
        </div>
        <div className='card'>

          <div className="row g-0">
            <div className="col-md-4">
                 
                <img src={img} className="img-fluid rounded-start" alt="..."/>
              
            </div>
            

              <div className='col-md-8'>

                    <div className='card-body' >
                      <div className='card-title'>
                        
                        <label htmlFor="titulo" className="form-label">
                          
                          <input
                            type="text"
                            className="form-control"
                            id="titulo"
                            name="titulo"
                            value={titulo}
                            onChange={handleInputChange}
                            placeholder="Ingrese Titulo" />
                        </label>
                      </div>
                
                        <div className='card-contenido'>
                          <div className='card-text'>
                            <label htmlFor="descripcion" className="form-label">
                                Descripcion
                              <textarea
                                  type="text"
                                  className="form-control"
                                  id="descripcion"
                                  name="descripcion"
                                  value={descripcion}
                                  onChange={handleInputChange}
                                  placeholder="Ingrese Descripcion" />
                            </label>
                          </div>
                          <div className='card-text'>
                            <label htmlFor="horarios" className="form-label">
                              Horarios
                              <input
                                type="text"
                                className="form-control"
                                id="horarios"
                                name="horarios"
                                value={horarios}
                                onChange={handleInputChange}
                                placeholder="Ingrese Horarios" />
                            </label>
                          </div>  
                        </div>
                    </div>
                  
                  

                    <div className="container-button">
                      <div className='card-button'>
                        <button className="btn btn-outline-primary mt-3" onClick={handleSubmit}>Guardar</button>
                        <Link className="btn btn-outline-secondary mt-3" to={'/adminActividades'}>Volver</Link>
                      </div>
                    </div>

              </div>
          </div>
        
        </div>
    </div>
    </>
  )
}

export default ActividadesEdit