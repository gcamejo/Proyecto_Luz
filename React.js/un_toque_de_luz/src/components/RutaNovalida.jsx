import React from 'react'
import { Link } from 'react-router-dom'

const RutaNovalida = () => {

  return (
    <>
    <div className="card-rutaNoValida">
          <div className="card-body">
            <div className="titulo-login">
                <img style={{borderRadius: 35}} src="/img/logo_principal.jpg"  alt="logo" width="60" height="60" />
                <h3>No permitido</h3>
              </div>            
        
              <div className='card-contenido'>
                  <div className="alert alert-info" role="alert">
                    Debes <Link  className="alert-link" to='/login'>Iniciar Sesion</Link>
                  </div>
                  <div className="alert alert-info" role="alert">
                    O <Link  className="alert-link" to='/cargarFicha'>Registrarte</Link>
                  </div>


              </div>
          </div>
        </div>
    
    </>
  )
}

export default RutaNovalida