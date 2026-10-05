import React, { useEffect } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useNavigate, useParams } from 'react-router'
import { Link } from 'react-router-dom'
import { useForm } from '../hooks/useForm'
import { getHorarios } from '../store/Slice/horarios/horarios'
import { getServicios } from '../store/Slice/servicios/servicios'
import { getTurno, updateTurno } from '../store/Slice/turnos/turnos'


const TurnoEdit = () => {

    const {id} = useParams()
    
    const navigate = useNavigate()

    const dispatch = useDispatch()

    const {turno} = useSelector(estado => estado.turno)

    const {listaServicios} = useSelector(estado => estado.listaServicios)

    const {listaHorarios} = useSelector(estado => estado.listaHorarios)

    const [values, handleInputChange, setValues] = useForm()
  
    const {servicio, nombre, apellido, dia1, dia2, hora1, hora2} = values
  
  
  useEffect(() => {
      dispatch(getTurno(id))
      dispatch(getServicios())
      dispatch(getHorarios())
  },[])

  useEffect(()=>{
    setValues(turno)
  },[turno])
      
 const handleSubmit = (e) =>{
    e.preventDefault()
    dispatch(updateTurno(id, values))
    navigate('/verTurnos')
  }

  return (
    <>
    <div className="alert p-4 col-4 mx-auto bg-body-secondary shadow">
    
      <form >

          <h3>Editar Turno</h3>
            <div className="form">
              <label htmlFor="sevicio" className="form-label">
                Servicio
                <select className="form-select"
                  type="text"
                  id="servicio"
                  name="servicio"
                  value = {servicio}
                  onChange={handleInputChange}>
                       {listaServicios && listaServicios.map(servicio => (
                        <option key = {servicio.id}>
                        {servicio.servicio}
                        </option>
                    ))}
                </select>
              </label>
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
                  readOnly
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
                  readOnly
                  />
              </label>
            </div>
          </div>
          <div className="row">
                <div className="col">
                      <label htmlFor="dia1" className="form-label">
                        Selecione dia 1:
                        <select className="form-select"
                              type="text"
                              id="dia1"
                              name="dia1"
                              value={dia1}
                              onChange={handleInputChange}>
                            <option >Lunes</option>
                            <option >Martes</option>
                            <option >Miercoles</option>
                            <option >Jueves</option>
                            <option >Viernes</option>
                        </select >
                      </label>
                </div>
                <div className="col">
                  <label htmlFor="hora1" className="form-label">
                    Elija hora dia 1
                    <select className="form-select"
                      type="text"
                      id="hora1"
                      name="hora1"
                      value={hora1}
                      onChange={handleInputChange} >
                                {listaHorarios && listaHorarios.map(horario => (
                                  <option key = {horario.id}>
                                    {horario.horario}
                                  </option>
                                ))}
                    </select >
                  </label>
                </div>
          </div>
          <div className="row">
                <div className="col">
                      <label htmlFor="dia2" className="form-label">
                        Dia 2
                        <select
                          className="form-select"
                          type="text"
                          id="dia2"
                          name="dia2"
                          value={dia2}
                          onChange={handleInputChange}>
                            <option >Lunes</option>
                            <option >Martes</option>
                            <option >Miercoles</option>
                            <option >Jueves</option>
                            <option >Viernes</option>
                          </select>
                        </label>
                </div>
                <div className="col">
                  <label htmlFor="hora2" className="form-label">
                    Eleja hora dia 2
                    <select
                      className="form-select"
                      type="text"
                      id="hora2"
                      name="hora2"
                      value={hora2}
                      onChange={handleInputChange}>
                               {listaHorarios && listaHorarios.map(horario => (
                                  <option key = {horario.id}>
                                    {horario.horario}
                                  </option>
                                ))}
                    </select >
                    </label>
                </div>
          </div>
          
          <button className="btn btn-dark" onClick={handleSubmit}>Editar</button>
          <Link className="btn btn-secondary" to={'/verTurnos'}>Volver al Listado</Link>
          
      </form>
    </div>
    </>
  )
}

export default TurnoEdit