import React from 'react'
import { useNavigate } from 'react-router'
import { Link } from 'react-router-dom'
import { useForm } from '../hooks/useForm'
import { createFicha } from '../store/Slice/Yoguinis/yoguinis'




const FichaCreate = () => {

  const navigate = useNavigate()

  var repassword = ''

  const [values, handleInputChange] = useForm({
    nombre:'',
    apellido:'',
    direccion:'',
    numero:'',
    telefono:'',
    fechaNacimiento:'',
    email:'',
    password:'',
    perfil:''
    
  })

  const handleSubmit = (e) =>{
    e.preventDefault()
    createFicha(values)
    navigate('/verFichas')
  }

  const {nombre, apellido, direccion, numero, telefono, fechaNacimiento, email, password, perfil} = values
  

  return (
    <>
    
    
        <form className="form-login">
            <div className="tituloLogin">
              <img style={{borderRadius: 35}} src="/img/logo_principal.jpg"  alt="logo" width="60" height="60" />
              <h3>Registro Yoguini</h3>
            </div>
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
                  placeholder="Ingrese Nombre" />
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
                  placeholder="Ingrese Apellido" />
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
                  placeholder="Ingrese Calle" />
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
                  placeholder="Ingrese Numero" />
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
                    placeholder="Ingrese Telefono" />
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
                      placeholder="Ingrese email" />
                    </label>
                  </div>
                </div>
                <div className='row'>
                  <div className="col">
                    <label htmlFor="password" className="form-label">
                      Contraseña
                      <input
                      type="password"
                      className="form-control"
                      id="password"
                      name="password"
                      value={password}
                      onChange={handleInputChange}
                      placeholder="Ingrese Contraseña" />
                    </label>
                </div>
                <div className="col">
                  <label htmlFor="repassword" className="form-label">
                    Repita Contraseña
                  <input
                    type="password"
                    className="form-control"
                    id="repassword"
                    name="repassword"
                    value={repassword}
                    onChange={handleInputChange}
                    placeholder="Repita Contraseña" 
                    />
                  </label>
                </div>


            </div>

      
            
            
            
            <button className="btn btn-primary mt-3" onClick={handleSubmit}>Cargar</button>
            <Link className="btn btn-secondary mt-3" to={'/verFichas'}>Volver al Listado</Link>
        </form>
    
    </>
  )
}


export default FichaCreate