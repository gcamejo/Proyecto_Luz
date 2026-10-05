import React, { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'



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
    setClaseButton('btn btn-primary')    
    setMensajeButton('Iniciar Sesion')
  }else{
    setNavegar("/")
    setMensaje('Hola ' + usuarioLogueado.nombre )
    setClaseButton('btn btn-success')    
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
    
       
    <nav className="navbar navbar-expand-lg navbar-light">
        <div className="container-fluid">
              <a className="navbar-brand" href="#">
                <img style={{borderRadius: 35}} src="/img/logo_principal.jpg"  alt="logo" width="60" height="60" />
              </a>
              <Link className="navbar-brand" to="/">Inicio</Link>
          <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir navegación">
            <span className="navbar-toggler-icon"></span>
          </button>
          <div className="collapse navbar-collapse" id="navbarNav">
            <ul className="navbar-nav me-auto mb-2 mb-lg-0">
                <li className="nav-item">
                  <Link className="nav-link active" aria-current="page" to="/actividades">Actividades</Link>
                </li>
                {user ? <NavbarUser/> : <li></li>}
                {admin ? <NavbarAdmin/> : <li></li>}
            </ul>
            <ul className="navbar-nav">
                  <li className="nav-item">
                    <Link className="nav-link active"  to={navegar}>{mensaje}</Link>
                  </li>
                  <li className="nav-item">
                     <button className={claseButton} onClick={handleSubmit}>{mensajeButton}</button>
                  </li>
            </ul>
          </div>
        </div>
    </nav>

    
    
    
  )
}

export default Navbar