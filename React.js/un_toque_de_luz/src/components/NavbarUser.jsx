import React from 'react'
import { NavLink } from 'react-router-dom'

const NavbarUser = () => {
  return (
    <>
    <li className="nav-item">
        <NavLink className={({isActive}) => `nav-link${isActive ? ' active' : ''}`} to="/reservarCiclo">Reservar clase</NavLink>
    </li>
    <li className="nav-item">
        <NavLink className={({isActive}) => `nav-link${isActive ? ' active' : ''}`} to="/misReservas">Mis reservas</NavLink>
    </li>
    </>
    )
}

export default NavbarUser