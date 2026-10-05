import React, { useEffect } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useForm } from '../hooks/useForm'
import { createHorario, deleteHorario, getHorarios } from '../store/Slice/horarios/horarios'

const AdminHorarios = () => {

    const dispatch = useDispatch()
    
    const {listaHorarios} = useSelector(estado => estado.listaHorarios)
  
    useEffect(() => {
        dispatch(getHorarios())
    },[dispatch])
  
    const [values, handleInputChange] = useForm({
    horario:''
    })

    const {horario} = values

    const handleSubmit = (e) =>{
        e.preventDefault()
        createHorario(values)
        location.reload()
      }

    const handleDelete = (id) => {
        deleteHorario(id)
        location.reload()
    }

  return (
    <>
    <div className="alert p-4 col-4 mx-auto shadow">
        <form className='form-control'>
            <h3 className='text-center'>Horarios</h3>
            
                {listaHorarios.map(horario => (
                    <div className='row' key={horario.id}>
                        <div className='col-6'>
                            <input 
                                type="time" 
                                className='form-control'
                                id="horario" 
                                name="horario" 
                                value={horario.horario} 
                                readOnly
                            />
                        </div>
                        <div className='col-6'>
                            <button className='btn btn-outline-danger btn-sm' onClick={() => handleDelete(horario.id)}>x</button>
                        </div>
                    </div>
                    
                    
                    ))}
                    <div className='row'>
                        <div className='col-6'>
                            <input 
                                type="time" 
                                className='form-control'
                                id="horario" 
                                name="horario" 
                                value={horario} 
                                onChange={handleInputChange}
                            />
                        </div>
                        <div className='col'>
                            <button className="btn btn-outline-primary btn-sm" onClick={handleSubmit}>+</button>

                        </div>
                    </div>
        </form>
    </div>
    </>
  )
}

export default AdminHorarios