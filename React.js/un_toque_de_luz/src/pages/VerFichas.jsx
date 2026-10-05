import React, { useEffect, useState} from 'react'
import { Link, useNavigate } from 'react-router-dom'

import { BsTrash, BsPencilSquare} from "react-icons/bs"
import { useDispatch, useSelector } from 'react-redux'
import { deleteFicha, getFicha, getFichasYoguinis } from '../store/Slice/Yoguinis/yoguinis'

import '../Styles/App.css'

const VerFichas = () => {

  const dispatch = useDispatch()
  
  const {fichasyoguinis} = useSelector(estado => estado.fichasyoguinis)
  const {ficha} = useSelector(estado => estado.ficha)
  

  useEffect(() => {
    dispatch(getFichasYoguinis())
  },[])

  const handleShow = (id) => {
    dispatch(getFicha(id))
  }

  const borrar = (e) =>{
    e.preventDefault()
    try {
      deleteFicha(ficha.id)
    } catch (error) {
    console.error(error)  
    }
    dispatch(getFichasYoguinis())
    
  }
  var a = 0


return (
    <>
      <div className='form-general'>
        
                <div className='titulo-general'>
                  <h1 className="mb-4">Yoguinis</h1>
                </div>
             
                  <hr/>
                <div className="container-fluid ">
                  <form className="d-flex justify-content-end">
                      <input className="form-control me-2" type="search" placeholder="Busqueda" aria-label="Busqueda"/>
                      <button className="btn btn-outline-success" type="submit">Buscar</button>
                  </form>
                </div>
                
        <div className='table-responsive-sm'>
            <table className="table">
              <thead>
                <tr>
                  <th scope="col">Ubicacion</th>
                  <th scope="col">Nombre y Apellido</th>
                  <th scope="col">Direccion</th>
                  <th scope="col">Numero</th>
                  <th scope="col">Telefono</th>
                  <th scope="col">Fecha Nacimiento</th>
                  <th scope="col">Correo Electronico</th>
                
                </tr>
              </thead>
              <tbody>
                {fichasyoguinis.map(yoguini=>(
                  <tr key={yoguini.id}>
                  <td>{a = a + 1 }</td>
                  <td>{yoguini.nombre + " " + yoguini.apellido}</td>
                  <td>{yoguini.direccion}</td>
                  <td>{yoguini.numero}</td>
                  <td>{yoguini.telefono}</td>
                  <td>{yoguini.fechaNacimiento}</td>
                  <td>{yoguini.email}</td>
                  <td>
                    <Link className="btn btn-outline-dark btn-sm" to = {`/editarFicha/${yoguini.id}`} >
                      <BsPencilSquare/>
                      Ver/Editar
                    </Link>
                    
                    <Link className="btn btn-outline-danger btn-sm" to={`/borrarFicha/${yoguini.id}`}>
                      <BsTrash/>
                      Borrar
                    </Link>

                    <button className="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#exampleModal" onClick={()=>handleShow(yoguini.id)}>
                      <BsTrash/>
                      Delete
                    </button>
                  </td>
                </tr>
                ))}
              </tbody>
            </table>
            
        </div>     
      </div>
      
            <div className="modal fade" id="exampleModal" tabIndex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
              <div className="modal-dialog">
                  <div className="modal-content">
                  <div className="modal-header">
                      <h1 className="modal-title fs-5" id="exampleModalLabel" >Atencion!</h1>
                      <button type="button" className="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div className="modal-body">
                          <h6>Esta por borrar la ficha de:</h6>
                          <h6>{ficha.nombre +" "+ ficha.apellido}</h6>
                          <h6>Direccion: {ficha.direccion + " " + ficha.numero} </h6>
                          <h6>Telefono: {ficha.telefono}</h6>
                          
                  </div>
                  <div className="modal-footer">
                      <button type="button" className="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                      <button type="button" className="btn btn-danger" data-bs-dismiss="modal" onClick={borrar}>Borrar</button>
                  </div>
                  </div>
              </div>    
            </div>
                
      
                

    </>
  )
}


export default VerFichas