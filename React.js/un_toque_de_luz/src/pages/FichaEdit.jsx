import React, { useEffect } from 'react'
import { useDispatch, useSelector } from 'react-redux'

import { useNavigate, useParams } from 'react-router'
import { Link } from 'react-router-dom'
import { useForm } from '../hooks/useForm'
import { getFicha, updateFicha } from '../store/Slice/Yoguinis/yoguinis'

const FichaEdit = () => {

    const {id} = useParams()
    const dispatch = useDispatch()
    const navigate = useNavigate()

    useEffect(() => {
      dispatch(getFicha(id))
    },[])
        
    const {ficha} = useSelector(estado => estado.ficha)

          
      const [values, handleInputChange, setValues] = useForm({
      nombre:'',
      apellido:'',
      direccion:'',
      numero:'',
      telefono:'',
      fechaNacimiento:'',
      email:''
      })
    

    const {nombre, apellido, direccion, numero, telefono, fechaNacimiento, email } = values
    
    
    useEffect(()=>{
      if(id == ficha.id){
      setValues({  
      nombre:ficha.nombre,
      apellido:ficha.apellido,
      direccion:ficha.direccion,
      numero:ficha.numero,
      telefono:ficha.telefono,
      fechaNacimiento:ficha.fechaNacimiento,
      email:ficha.email
    })
    }
    },[ficha])

    
  
    const handleSubmit = (e) =>{
      e.preventDefault()
        dispatch(updateFicha(id, values))
        navigate('/verFichas')
    }

    
    
       
    
  return (
    <>
    <div className="alert p-4 col-5 mx-auto shadow">
    
    <form >
        <h3>Editar Ficha Yoguini</h3>
        <div className="row">
          <div className="col">
            <label htmlFor="nombre" className="form-label">
              Nombre
            <input
              type="text"
              className="form-control"
              id="nombre "
              name="nombre"
              value={nombre}
              onChange={handleInputChange}
              />
            </label>
          </div>
          <div className="col">
            <label htmlFor="apellido" className="form-label">
              Apellido
            <input
              type="text"
              className="form-control"
              id="apellido "
              name="apellido"
              value={apellido}
              onChange={handleInputChange}
              />
            </label>
          </div>
        </div>
        <div className="row">
          <div className="col">
            <label htmlFor="direccion" className="form-label">
              Dirección
            <input
              type="text"
              className="form-control"
              id="direccion "
              name="direccion"
              value={direccion}
              onChange={handleInputChange}
              />
            </label>
          </div>
          <div className="col">
            <label htmlFor="numero" className="form-label">
              Numero
            <input
              type="number"
              className="form-control"
              id="numero "
              name="numero"
              value={numero}
              onChange={handleInputChange}
              />
            </label>
          </div>
        </div>
        <div className='row'>
          <div className="col">
            <label htmlFor="telefono" className="form-label">
              Telefono
            <input
              type="tel"
              className="form-control"
              id="telefono"
              name="telefono"
              value={telefono}
              onChange={handleInputChange}
              />
            </label>
          </div>
          <div className="col">
            <label htmlFor="fechaNacimiento" className="form-label">
              Fecha Nacimiento
            <input
              type="date"
              className="form-control"
              id="fechaNacimiento"
              name="fechaNacimiento"
              value={fechaNacimiento}
              onChange={handleInputChange}
              />
            </label>
          </div>
        </div>
        <div className='row'>
          <div className="col">
            <label htmlFor="email" className="form-label">
              Correo Electronico
            <input
              type="email"
              className="form-control"
              id="email"
              name="email"
              value={email}
              onChange={handleInputChange}
              />
            </label>
          </div>
        </div>

        
        
        
        
        <button className="btn btn-primary mt-3" onClick={handleSubmit}>Editar</button>
        <Link className="btn btn-secondary mt-3" to={'/verFichas'}>Volver al Listado</Link>
        
    </form>
        
    </div>
    </>
  )
}

export default FichaEdit