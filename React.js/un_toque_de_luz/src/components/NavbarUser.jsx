import React from 'react'
import { NavLink } from 'react-router-dom'

const NavbarUser = () => {
  return (
    <>
    <li className="nav-item">
        <NavLink className={({isActive}) => `nav-link${isActive ? ' active' : ''}`} to="/actividades">Turnos</NavLink>
    </li>
    <li className="nav-item">
        <NavLink className={({isActive}) => `nav-link${isActive ? ' active' : ''}`} to="/actividades">Datos Personales</NavLink>
    </li>
    </>
    )
}

export default NavbarUser