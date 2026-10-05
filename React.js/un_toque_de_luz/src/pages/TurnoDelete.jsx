import React, { useEffect } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useNavigate, useParams } from 'react-router'
import { Link } from 'react-router-dom'
import { deleteTurno, getTurno } from '../store/Slice/turnos/turnos'

const TurnoDelete = () => {

    const{id} = useParams()

    const dispatch = useDispatch()

    const navigate = useNavigate()
    
    const {turno} = useSelector(estado => estado.turno)

    
  useEffect(() => {
      dispatch(getTurno(id))
  },[])

   
  const handleSubmit = (e) =>{
    e.preventDefault()
    deleteTurno(id)
    navigate('/verTurnos')
  }
  
  
  
  return (
    <>
        <div  className="card border-danger p-4 col-4 mx-auto shadow-lg">
            <div className="card text-bg-danger">
                <div className="card-header text-center">
                    <h3>ATENCION!!</h3>
                </div>
            </div>
                <div className="card-body text-danger">
                    <h5 className="card-title">Esta por eliminar el Turno de:</h5>
                    <div className="card-body text-dark">
                        <h6>{turno.nombre +" "+turno.apellido}</h6>
                        <h6>{"Servicio:  " + turno.servicio}</h6>
                        <h6>Dias:</h6>
                        <h6>{turno.dia1 + " " + turno.hora1 + " y " + turno.dia2 + " " + turno.hora2}</h6>
                    </div>
                        <div className="row">
                            <div className="col">
                                <button className="btn btn-danger mt-3 bt-sm" onClick={handleSubmit}>Eliminar</button>
                            </div>
                            <div className="col">
                                <Link className="btn btn-success mt-3 bt-sm" to={'/verTurnos'}>Volver</Link>
                            </div>
                    </div>
                </div>
                
        </div>
</>
  )
}

export default TurnoDelete