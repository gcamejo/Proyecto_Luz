import React from 'react'
import { Link } from 'react-router-dom'

const NavbarUser = () => {
  return (
    <>
    <li className="nav-item">
        <Link className="nav-link active" aria-current="page" to="/actividades">Turnos</Link>
    </li>
    <li className="nav-item">
        <Link className="nav-link active" aria-current="page" to="/actividades">Datos Personales</Link>
    </li>
    </>
    )
}

export default NavbarUser