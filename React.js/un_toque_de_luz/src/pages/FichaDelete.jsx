import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useNavigate, useParams } from 'react-router'
import { Link } from 'react-router-dom'
import { deleteFicha, getFicha } from '../store/Slice/Yoguinis/yoguinis'

const FichaDelete = () => {

    const{id} = useParams()

    const dispatch = useDispatch()

    const {ficha} = useSelector(estado => estado.ficha)

    const navigate = useNavigate()
       
  useEffect(() => {
    dispatch(getFicha(id))  
  },[])


  const borrar = (e) =>{
    e.preventDefault()
    deleteFicha(id)
    navigate('/verFichas')
  }
  
  


  return (
    <>
        <div  className="card border-danger p-4 col-4 mx-auto shadow">
            <div className="card text-bg-danger">
                <div className="card-header-danger text-center">
                    <h3>ATENCION!!</h3>
                </div>
            </div>
                <div className="card-body text-danger">
                    <h5 className="card-title">Esta por eliminar la Ficha de:</h5>
                    <div className="card-body text-dark">
                        <h6>{ficha.nombre +" "+ficha.apellido}</h6>
                        <h6>Direccion: {ficha.direccion + " " + ficha.numero} </h6>
                        <h6>Telefono: {ficha.telefono}</h6>
                    </div>
                        <div className="row">
                            <div className="col">
                                <button className="btn btn-danger mt-3 bt-sm" onClick={borrar}>Eliminar</button>
                            </div>
                            <div className="col">
                                <Link className="btn btn-success mt-3 bt-sm" to={'/verFichas'}>Volver</Link>
                            </div>
                    </div>
                </div>
                
        </div>
    </>
    )
}

export default FichaDelete