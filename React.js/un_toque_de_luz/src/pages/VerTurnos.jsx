import React, { useEffect} from 'react'
import { Link } from 'react-router-dom'

import { BsTrash, BsPencilSquare} from "react-icons/bs"
import { useDispatch, useSelector } from 'react-redux'
import { getListadoTurnos } from '../store/Slice/turnos/turnos'


const VerTurnos = () => {


  const dispatch = useDispatch()
  
  const {listadoTurnos} = useSelector(estado => estado.listadoTurnos)

  useEffect(() => {
      dispatch(getListadoTurnos())
  },[])


  return (
    
    <>
    <div className='container'>

        <div className="bg-success-subtle text-emphasis-success shadow-lg p-3 mt-5 mb-5 bg-body-tertiary rounded">
          <h1 className="text-center mb-4">Turnos</h1>
        </div>
        
        <hr/>
        <Link className="btn btn-primary" to={'/verTurnosPorDias'} state={listadoTurnos}>Ver por dias</Link>

        <div className='table-responsive-sm'>
            <table className="table">
                    <thead>
                      <tr>
                        <th scope="col">Servicio</th>
                        <th scope="col">Nombre y Apellido</th>
                        <th scope="col">Dia 1</th>
                        <th scope="col">Hora 1</th>
                        <th scope="col">Dia 2</th>
                        <th scope="col">Hora 2</th>
                        <th scope="col">Acciones</th>
                        
                      </tr>
                    </thead>
                    <tbody>
                      {listadoTurnos.map(turno=>(
                        <tr key={turno.id}>
                        <td>{turno.servicio}</td>
                        <td>{turno.nombre + ' ' + turno.apellido}</td>
                        <td>{turno.dia1}</td>
                        <td>{turno.hora1}</td>
                        <td>{turno.dia2}</td>
                        <td>{turno.hora2}</td>
                        <td>
                          
                          <Link className="btn btn-outline-secondary btn-sm" to = {`/editarTurno/${turno.id}`} >
                            <BsPencilSquare/>
                            Ver/Editar
                          </Link>
                          
                          <Link className="btn btn-outline-danger btn-sm" to={`/borrarTurno/${turno.id}`}>
                            <BsTrash/>
                            Borrar
                          </Link>
                        </td>
                        
                      </tr>
                      ))}
                      
                    </tbody>
            </table>

        </div>
    
    </div>
    </>
  )
}

export default VerTurnos