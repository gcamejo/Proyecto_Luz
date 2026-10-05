import React, { useEffect, useState } from 'react'
import { deleteFicha, getFicha } from '../store/Slice/Yoguinis/yoguinis'
import { useNavigate, useParams } from 'react-router'
import { useDispatch, useSelector } from 'react-redux'

const ModalDelete = () => {
    
    const {ficha} = useSelector(estado => estado.ficha)

    const navigate = useNavigate()
      
    const borrar = (e) =>{
        e.preventDefault()
        deleteFicha(id)
        navigate('/verFichas')
      }




  return (
    <>
        
            <div className="modal-dialog">
                <div className="modal-content">
                <div className="modal-header">
                    <h1 className="modal-title fs-5" id="exampleModalLabel">Borrar</h1>
                    <button type="button" className="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div className="modal-body">
                        
                        <h6>{ficha.nombre +" "+ ficha.apellido}</h6>
                        <h6>Direccion: {ficha.direccion + " " + ficha.numero} </h6>
                        <h6>Telefono: {ficha.telefono}</h6>
                </div>
                <div className="modal-footer">
                    <button type="button" className="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="button" className="btn btn-danger" onClick={borrar}>Borrar</button>
                </div>
                </div>
            </div>
        
    </>
  )
}

export default ModalDelete