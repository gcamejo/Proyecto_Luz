import React from 'react'
import { useEffect  } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from '../hooks/useForm'
import { getHorarios } from '../store/Slice/horarios/horarios'
import { getServicios } from '../store/Slice/servicios/servicios'
import { createTurno } from '../store/Slice/turnos/turnos'
import { getFichasYoguinis } from '../store/Slice/Yoguinis/yoguinis'




const TurnoCreate = () => {

const dispatch = useDispatch()

const {listaServicios} = useSelector(estado => estado.listaServicios)

const {fichasyoguinis} = useSelector(estado => estado.fichasyoguinis)

const {listaHorarios} = useSelector(estado => estado.listaHorarios)


useEffect (()=>{
  dispatch(getFichasYoguinis())
  dispatch(getServicios())
  dispatch(getHorarios())
},[])


  const navigate = useNavigate()

  const [values, handleInputChange] = useForm({
    servicio:'',
    nombre:'',
    apellido:'',
    dia1:'',
    hora1:'',
    dia2:'',
    hora2:''

  })

  const handleSubmit = (e) =>{
    e.preventDefault()
    createTurno(values)
    navigate('/verTurnos')
  }

  const {servicio, nombre, apellido, dia1, dia2, hora1, hora2} = values



  return (
    <>
      
      <div className="fondoCrearTurno">
        
          <form className="formCrearTurno">
            <h2 className="text-white text-center">Cargar Turno</h2>
                <div className="row">
                  <div className="col">
                    <div className="form text-white">
                    <label htmlFor="servicio" className="form-label">
                    Servicio
                      <select className="form-select"
                        type="text"
                        id="servicio"
                        name="servicio"
                        value={servicio}
                        onChange={handleInputChange}>
                          <option >Elija un Servicio</option>
                          {listaServicios && listaServicios.map(servicio=>(
                            <option key = {servicio.id}>
                              {servicio.servicio}
                            </option>

                          ))}
                      </select>
                    </label>
                    </div>
                  </div>
                  <div className="col">
                  <div className="form text-white">
                    <label htmlFor="Nombre" className="form-label">
                     Nombre Completo
                      <select className="form-select"
                        type="array"
                        id="nombre"
                        name="nombre"
                        value={nombre}
                        onChange={handleInputChange}
                        >
                        
                          <option >Elija opcion</option>
                          {fichasyoguinis && fichasyoguinis.map(yoguini=>(
                          <option key={yoguini.id}>
                            {yoguini.nombre + " " + yoguini.apellido}
                          </option>
                          ))}
                        
                      </select>
                    </label>
                  </div>
                  </div>
                </div>
                            
              <div className="row">
                <div className="col">
                    
                          <label htmlFor="dia1" className="form-label text-white">
                            Dia 1
                            <select className="form-select"
                                type="text"
                                id="dia1"
                                name="dia1"
                                value={dia1}
                                onChange={handleInputChange}>
                              <option >Elija un dia</option>   
                              <option >Lunes</option>
                              <option >Martes</option>
                              <option >Miercoles</option>
                              <option >Jueves</option>
                              <option >Viernes</option>
                            </select >
                          </label>
                </div>
                <div className="col">
                    
                      <label htmlFor="hora1" className="form-label text-white">
                        Horario
                        <select className="form-select"
                          type="time"
                          id="hora1"
                          name="hora1"
                          value={hora1}
                          onChange={handleInputChange} >
                                <option >Elija un horario</option>
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
                          <label htmlFor="dia2" className="form-label text-white">
                            Dia 2
                          <select
                            className="form-select"
                            type="text"
                            id="dia2"
                            name="dia2"
                            value={dia2}
                            onChange={handleInputChange}>
                              <option >Elija un dia</option>
                              <option >Lunes</option>
                              <option >Martes</option>
                              <option >Miercoles</option>
                              <option >Jueves</option>
                              <option >Viernes</option>
                            </select>
                          </label>
                          </div>
                    
                    <div className="col">
                      <label htmlFor="hora2" className="form-label text-white">
                        Horario
                        <select
                          className="form-select"
                          type="text"
                          id="hora2"
                          name="hora2"
                          value={hora2}
                          onChange={handleInputChange}>
                                <option >Elija un horario</option>
                                {listaHorarios && listaHorarios.map(horario => (
                                  <option key = {horario.id}>
                                    {horario.horario}
                                  </option>
                                  ))}
                        </select >
                      </label>
                      </div>
              </div>
              <button className="btn btn-success mt-3" onClick={handleSubmit}>Cargar Turno</button>
              <Link className="btn btn-secondary mt-3" to={'/verTurnos'}>Volver al Listado</Link>
          </form>
          
        </div> 
    </>
  )
}

export default TurnoCreate