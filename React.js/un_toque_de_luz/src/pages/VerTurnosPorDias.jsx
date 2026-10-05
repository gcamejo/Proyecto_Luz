import React, { useEffect } from 'react'
import { useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { Link } from 'react-router-dom'
import { getListadoTurnosOrdenadosHora } from '../store/Slice/turnos/turnos'
import FormularioDias from './FormularioDias'

const VerTurnosPorDias = () => {

    const dispatch = useDispatch()

    const {listadoTurnos} = useSelector(estado => estado.listadoTurnos)

    useEffect(() => {
        dispatch(getListadoTurnosOrdenadosHora())
    },[])

    // const listadoTurnos = useLocation()

    const oscuro = 'btn btn-dark'
    const claro = 'btn btn-outline-dark'
    const reset = {background: 'initial'}

    

    const [mostrar, setMostrar] = useState('lunes')
    
    const mostrarOcultar=(dia)=>{
        if (dia == 'lunes') {
            setMostrar('lunes')
        }
        if (dia == 'martes') {
            setMostrar('martes')
        }
        if (dia == 'miercoles') {
            setMostrar('miercoles')
        }
        if (dia == 'jueves') {
            setMostrar('jueves')
        }
        if (dia == 'viernes') {
            setMostrar('viernes')
        }
    }
  return (
    <>

    
    <div className='container'>




    <div className='card w-50 border-dark'>
            <div className="card-header">
                <h4 className='text-center'>Listado por Dias</h4>
                <nav className="navbar bg-body-tertiary" >
                    <ul className="nav nav-tabs">
                        <li className="nav-item" style={reset}>
                            <button className={mostrar == 'lunes' ? oscuro : claro} onClick={() => mostrarOcultar('lunes')} >Lunes</button>
                        </li>
                        <li className="nav-item" style={reset}>
                            <button className={mostrar == 'martes' ? oscuro : claro} onClick={() => mostrarOcultar('martes')} >Martes</button>
                        </li>
                        <li className="nav-item" style={reset}>
                            <button className={mostrar == 'miercoles' ? oscuro : claro} onClick={() => mostrarOcultar('miercoles')} >Miercoles</button>
                        </li>
                        <li className="nav-item" style={reset}>
                            <button className={mostrar == 'jueves' ? oscuro : claro} onClick={() => mostrarOcultar('jueves')} >Jueves</button>
                        </li>
                        <li className="nav-item" style={reset}>
                            <button className={mostrar == 'viernes' ? oscuro : claro} onClick={() => mostrarOcultar('viernes')} >Viernes</button>
                        </li>
                    </ul>
                    <ul className="nav justify-content-end">
                        <li className="nav-item" style={reset}>
                            <h4>{ mostrar == 'lunes' ? 'Lunes' : ""}</h4>
                            <h4>{ mostrar == 'martes' ? 'Martes' : ""}</h4>
                            <h4>{ mostrar == 'miercoles' ? 'Miercoles' : ""}</h4>
                            <h4>{ mostrar == 'jueves' ? 'Jueves' : ""}</h4>
                            <h4>{ mostrar == 'viernes' ? 'Viernes' : ""}</h4>
                        </li>
                        

                          
                    </ul>
                </nav>  
            </div>
            <div className='card-body'>
                { mostrar == 'lunes' ? <FormularioDias tituloDia = 'Lunes' dia = 'Lunes' listadoTurnos = {listadoTurnos}/>: "" }
                { mostrar == 'martes' ? <FormularioDias tituloDia = 'Martes' dia = 'Martes' listadoTurnos = {listadoTurnos}/>: "" }
                { mostrar == 'miercoles' ? <FormularioDias tituloDia = 'Miercoles' dia = 'Miercoles' listadoTurnos = {listadoTurnos}/>: "" }
                { mostrar == 'jueves' ? <FormularioDias tituloDia = 'Jueves' dia = 'Jueves' listadoTurnos = {listadoTurnos}/>: "" }
                { mostrar == 'viernes' ? <FormularioDias tituloDia = 'Viernes' dia = 'Viernes' listadoTurnos = {listadoTurnos}/>: "" }
            </div>
    </div>
            <Link className='btn btn-primary' to={'/verTurnos'}>Volver</Link>
    
    </div>
    </>
    
  )
}

export default VerTurnosPorDias