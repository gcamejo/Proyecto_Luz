import React from 'react'
import { Link } from 'react-router-dom'

const NavbarAdmin = () => {
  return (
    <>
    <li className="nav-item dropdown">
        <button className="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            Turnos
        </button>                     
        <ul className="dropdown-menu">
          <li><Link className="dropdown-item" to="/verTurnos">Ver Listado</Link></li>
          <li><Link className="dropdown-item" to="/cargarTurno">Cargar Turno</Link></li>
        </ul>
    </li>
    <li className="nav-item dropdown">
        <button className="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            Yoguinis
        </button>
        <ul className="dropdown-menu">
          <li><Link className="dropdown-item" to="/verFichas">Ver Listado</Link></li>
          <li><Link className="dropdown-item" to="/cargarFicha">Nueva</Link></li>
        </ul>
    </li>
    <li className="nav-item dropdown">
          <button className="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
           Administrar
          </button>
         <ul className="dropdown-menu">
           <li><Link className="dropdown-item" to="/adminServicios">Servicios</Link></li>
           <li><Link className="dropdown-item" to="/adminHorarios">Horarios</Link></li>
           <li><Link className="dropdown-item" to="/adminActividades">Actividades</Link></li>
           <li><Link className="dropdown-item" to="/adminReservas">Ciclos y reservas</Link></li>
         </ul>
    </li>          
    </>
  )
}

export default NavbarAdmin