import React from 'react'
import { useNavigate } from 'react-router'
import { useForm } from '../hooks/useForm'

const Turnos = () => {

  const navigate = useNavigate()

  const [values, handleInputChange] = useForm({
    nombre:'',
    dias:'',
    horario:''

  })

  const handleSubmit = (e) =>{
    e.preventDefault()

    navigate('/')
  }



  return (
    <>
    <h1>Turno</h1>
    <form>
        <div className="mb-3">
          <label htmlFor="usuario" className="form-label">Nombre</label>
          <input type="text" className="form-control" id="nombre" name="nombre" placeholder="Ingrese Nombre"/>
        </div>
        <div className="mb-3">
          <label htmlFor="dias" className="form-label">Dias</label>
          <input type="text" className="form-control" id="dias" name="dias" placeholder="Elija Dias"/>
        </div>
        <div className="mb-3">
          <label htmlFor="hora" className="form-label">Horario</label>
          <input type="hour" className="form-control" id="horario" name="horario" placeholder="Elija Horario"/>
        </div>
        <button className="btn btn-success">Pedir</button>
    </form>
    </>
  )
}

export default Turnos