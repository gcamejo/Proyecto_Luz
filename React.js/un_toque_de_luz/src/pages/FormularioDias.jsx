import React from 'react'
import { BsPencilSquare, BsTrash } from 'react-icons/bs'
import { Link } from 'react-router-dom'

const FormularioDias = (props) => {

    const {tituloDia, dia, listadoTurnos} = props

  return (
    <>
        
            <div className='table-responsive-sm'>
                <table className="table">
                    <thead>
                        <tr>
                        <th scope="col">Hora</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Servicio</th>
                        <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {listadoTurnos.filter(turno => turno.dia1 == (dia) || turno.dia2  == (dia)).map(turno=>(
                        <tr key={turno.id}>
                        <td>{turno.dia1 == (dia) ? turno.hora1 : turno.hora2}</td>
                        <td>{turno.nombre}</td>
                        <td>{turno.servicio}</td>
                        <td>
                            <Link className="btn btn-outline-secondary btn-sm" to = {`/editarTurno/${turno.id}`} >
                                <BsPencilSquare/>
                                    Editar
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
      
    </>
  )
}

export default FormularioDias