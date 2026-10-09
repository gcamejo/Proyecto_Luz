import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import Loading from '../components/Loading'
import { useForm } from '../hooks/useForm'
import { setCodigo, setError, validarUsuario } from '../store/Slice/loginUsuario/loginUsuario'
import { safeInternalRedirect } from '../utils/safeInternalRedirect'

const Login = () => {
  const dispatch = useDispatch()
  const navigate = useNavigate()
  const location = useLocation()
  const { codigo } = useSelector((estado) => estado.codigo)
  const { error } = useSelector((estado) => estado.error)
  const [values, handleInputChange, setValues] = useForm({ email: '', password: '' })
  const [mensaje, setMensaje] = useState('')
  const [cargando, setCargando] = useState(false)

  useEffect(() => {
    if (!cargando) return

    if (codigo === 200) {
      const destination = safeInternalRedirect(new URLSearchParams(location.search).get('redirect'))
      navigate(destination, { replace: true })
    } else if (codigo === 404) {
      setMensaje('El email o la contraseña no son válidos.')
      setValues((actual) => ({ ...actual, password: '' }))
      setCargando(false)
    } else if (error) {
      setMensaje('No pudimos iniciar sesión. Revisá tus datos e intentá nuevamente.')
      setCargando(false)
    }
  }, [codigo, error, cargando, location.search, navigate, setValues])

  const handleSubmit = (evento) => {
    evento.preventDefault()
    setMensaje('')
    dispatch(setCodigo(null))
    dispatch(setError(null))
    setCargando(true)
    dispatch(validarUsuario(values))
  }

  return (
    <section className="login-page">
      <form className="form-login" onSubmit={handleSubmit}>
        <div className="tituloLogin">
          <img src="/img/logo_principal.jpg" alt="Un Toque de Luz" width="56" height="56" />
          <div>
            <p className="login-kicker">Un espacio para vos</p>
            <h1>Iniciar sesión</h1>
          </div>
        </div>

        <div className="form-login-int">
          <label htmlFor="email" className="form-label">Email</label>
          <input
            type="email"
            className="form-control"
            id="email"
            name="email"
            value={values.email}
            onChange={handleInputChange}
            placeholder="nombre@correo.com"
            autoComplete="email"
            required
            disabled={cargando}
          />

          <label htmlFor="password" className="form-label">Contraseña</label>
          <input
            type="password"
            className="form-control"
            id="password"
            name="password"
            value={values.password}
            onChange={handleInputChange}
            placeholder="Ingresá tu contraseña"
            autoComplete="current-password"
            required
            disabled={cargando}
          />

          {mensaje && <p className="login-message" role="alert">{mensaje}</p>}

          <button className="login-submit" type="submit" disabled={cargando}>
            {cargando ? (
              <>
                <Loading clase="spinner-border spinner-border-sm" />
                <span>Ingresando...</span>
              </>
            ) : 'Ingresar'}
          </button>

          <p className="login-register">
            ¿Todavía no tenés usuario? <Link to="/cargarFicha">Registrate</Link>
          </p>
        </div>
      </form>
    </section>
  )
}

export default Login