import React, { useEffect, useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'



import { useDispatch, useSelector } from 'react-redux'
import { deleteUsuario } from '../store/Slice/loginUsuario/loginUsuario'
import NavbarAdmin from './NavbarAdmin'
import NavbarUser from './NavbarUser'


const Navbar = () => {

const navigate = useNavigate()  
const dispatch = useDispatch()
const {usuarioLogueado} = useSelector((estado=>estado.usuarioLogueado))


const [user, setUser]=useState(false)
const [admin, setAdmin]=useState(false)
const [navegar,setNavegar]=useState([])
const [mensaje,setMensaje]=useState([])
const [claseButton,setClaseButton]=useState([])
const [mensajeButton,setMensajeButton]=useState([])


useEffect(()=>{

  if (usuarioLogueado == null) {
    setNavegar("/cargarFicha")
    setMensaje('Registrarse')
    setClaseButton('site-nav-session site-nav-session-login')    
    setMensajeButton('Iniciar Sesion')
  }else{
    setNavegar("/")
    setMensaje('Hola ' + usuarioLogueado.nombre )
    setClaseButton('site-nav-session site-nav-session-logout')    
    setMensajeButton('Cerrar Sesion')
    switch (usuarioLogueado.perfil) {
      case 'user':
        setUser(true)
        break;    
      case 'Admin':
        setAdmin(true)
        break;
    }
  }
},[usuarioLogueado])


const handleSubmit = (e) => {
  e.preventDefault()
  if (user || admin) {
    dispatch(deleteUsuario())
    setAdmin(false)
    setUser(false)
    navigate("/")
  }else{
    navigate("/login")
  }
}

return (
    
       
    <nav className="navbar navbar-expand-lg site-navbar">
        <div className="container-fluid">
              <Link className="navbar-brand site-nav-identity" to="/" aria-label="Un toque de luz, inicio">
                <img src="/img/logo_principal.jpg" alt="" width="48" height="48" />
                <span>Un toque de luz</span>
              </Link>
          <button className="navbar-toggler site-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir navegación">
            <span className="navbar-toggler-icon"></span>
          </button>
          <div className="collapse navbar-collapse" id="navbarNav">
            <ul className="navbar-nav site-nav-links">
                <li className="nav-item">
                  <NavLink className={({isActive}) => `nav-link${isActive ? ' active' : ''}`} to="/">Inicio</NavLink>
                </li>
                <li className="nav-item">
                  <NavLink className={({isActive}) => `nav-link${isActive ? ' active' : ''}`} to="/actividades">Actividades</NavLink>
                </li>
                {user ? <NavbarUser/> : <li></li>}
                {admin ? <NavbarAdmin/> : <li></li>}
            </ul>
            <ul className="navbar-nav site-nav-account">
                  <li className="nav-item">
                    <Link className="nav-link site-nav-greeting" to={navegar}>{mensaje}</Link>
                  </li>
                  <li className="nav-item">
                     <button type="button" className={claseButton} onClick={handleSubmit}>{mensajeButton}</button>
                  </li>
            </ul>
          </div>
        </div>
    </nav>

    
    
    
  )
}

export default Navbar