import React, { useEffect } from 'react'
import { BsPlusCircle, BsXCircleFill } from 'react-icons/bs'
import { useDispatch, useSelector } from 'react-redux'
import { useForm } from '../hooks/useForm'
import { createServicio, deleteServicio, getServicios } from '../store/Slice/servicios/servicios'

const AdminServicio = () => {

    const dispatch = useDispatch()
    
    const {listaServicios} = useSelector(estado => estado.listaServicios)
  
    useEffect(() => {
        dispatch(getServicios())
    },[dispatch])
  
    const [values, handleInputChange] = useForm({
    servicio:''
    })

    const {servicio} = values

    const handleSubmit = (e) =>{
        e.preventDefault()
        createServicio(values)
        location.reload()
      }

    const handleDelete = (id) => {
        deleteServicio(id)
        location.reload()
    }

  return (
    <>
    <div className="alert p-4 col-3 mx-auto shadow">
        <form className='form-control'>
            <table className='table'>
                <thead>
                    <tr>
                        <th>Servicios</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {listaServicios.map(servicio => (
                        <tr key = {servicio.id}>
                            <td>{servicio.servicio}</td>
                            <td>
                                <button className='btn' onClick={() => handleDelete(servicio.id)}>
                                <BsXCircleFill/>
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
                    <div className='row'>
                        <div className='col-auto'>
                            <input 
                                type="text" 
                                className='form-control'
                                id="servicio" 
                                name="servicio" 
                                value={servicio} 
                                onChange={handleInputChange}
                            />
                        </div>
                        <div className='col'>
                            <button className="btn " onClick={handleSubmit}>
                            <BsPlusCircle/>
                            </button>
                        </div>
                    </div>
        </form>
    </div>
    </>
  )
}

export default AdminServicio