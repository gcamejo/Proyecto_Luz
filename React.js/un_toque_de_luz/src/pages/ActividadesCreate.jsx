import React from 'react'
import { useNavigate } from 'react-router'
import { useForm } from '../hooks/useForm'

import '../Styles/Actividades.css'
import UploadFile from '../components/UploadFile'
import { Link } from 'react-router-dom'
import { createActividad } from '../store/Slice/actividades/actividades'

const ActividadesCreate = () => {

    
    const navigate = useNavigate()

    const [values,handleInputChange] = useForm({
        urlImagen:'',
        titulo:'',
        descripcion:'',
        horarios:''
      })
    
    const {urlImagen, titulo, descripcion, horarios} = values

    const handleSubmit = (e) =>{
        e.preventDefault()
        createActividad(values)
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
              
                <UploadFile/>
              
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

export default ActividadesCreate