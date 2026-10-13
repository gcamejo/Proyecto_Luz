import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { Link, useNavigate } from 'react-router-dom'
import axios from '../api/axios'
import { setUsuarioLogueado } from '../store/Slice/loginUsuario/loginUsuario'
import '../Styles/Yoguinis.css'

const Perfil = () => {
  const dispatch = useDispatch()
  const navigate = useNavigate()
  const { usuarioLogueado } = useSelector(state => state.usuarioLogueado)
  const { token } = useSelector(state => state.token)

  const [values, setValues] = useState({
    nombre: '',
    apellido: '',
    direccion: '',
    numero: '',
    telefono: '',
    fechaNacimiento: '',
    email: '',
    password: '',
    password_confirmation: '',
  })

  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [showPasswordFields, setShowPasswordFields] = useState(false)

  useEffect(() => {
    if (!token) {
      navigate('/login?redirect=%2Fperfil')
      return
    }

    let active = true
    setLoading(true)
    setError('')

    if (usuarioLogueado) {
      setValues(current => ({
        ...current,
        nombre: usuarioLogueado.nombre || '',
        apellido: usuarioLogueado.apellido || '',
        direccion: usuarioLogueado.direccion || '',
        numero: usuarioLogueado.numero ?? '',
        telefono: usuarioLogueado.telefono || '',
        fechaNacimiento: usuarioLogueado.fechaNacimiento || '',
        email: usuarioLogueado.email || '',
      }))
    }

    axios.get('api/profile')
      .then(res => {
        if (!active) return
        const data = res.data || {}
        setValues(current => ({
          ...current,
          nombre: data.nombre || '',
          apellido: data.apellido || '',
          direccion: data.direccion || '',
          numero: data.numero ?? '',
          telefono: data.telefono || '',
          fechaNacimiento: data.fechaNacimiento || '',
          email: data.email || '',
        }))
        dispatch(setUsuarioLogueado(data))
      })
      .catch(err => {
        if (!active) return
        if (!usuarioLogueado) {
          setError(err.response?.data?.message || err.message || 'No se pudieron cargar los datos del perfil.')
        }
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => { active = false }
  }, [token, navigate, dispatch])

  const updateField = e => {
    const { name, value } = e.target
    setValues(curr => ({ ...curr, [name]: value }))
    setError('')
    setSuccess('')
  }

  const handleSubmit = async e => {
    e.preventDefault()
    setError('')
    setSuccess('')

    if (showPasswordFields && values.password) {
      if (values.password.length < 8) {
        setError('La contraseña debe tener al menos 8 caracteres.')
        return
      }
      if (values.password !== values.password_confirmation) {
        setError('Las contraseñas no coinciden.')
        return
      }
    }

    setSaving(true)

    const payload = {
      nombre: values.nombre.trim(),
      apellido: values.apellido.trim(),
      direccion: values.direccion.trim(),
      numero: Number(values.numero),
      telefono: values.telefono.trim(),
      fechaNacimiento: values.fechaNacimiento,
      email: values.email.trim(),
    }

    if (showPasswordFields && values.password) {
      payload.password = values.password
    }

    try {
      const { data } = await axios.patch('api/profile', payload)
      dispatch(setUsuarioLogueado(data))
      setSuccess('¡Tus datos personales fueron actualizados con éxito!')
      setValues(curr => ({ ...curr, password: '', password_confirmation: '' }))
      setShowPasswordFields(false)
    } catch (err) {
      const validationErrors = err.response?.data?.errors
      if (validationErrors) {
        setError(Object.values(validationErrors).flat().join(' '))
      } else {
        setError(err.response?.data?.message || err.message || 'No se pudieron guardar los cambios.')
      }
    } finally {
      setSaving(false)
    }
  }

  const isAdmin = usuarioLogueado?.perfil === 'Admin'

  if (loading && !values.nombre) {
    return <p className="yoguini-message yoguini-page-status" role="status">Cargando perfil...</p>
  }

  return (
    <section className="yoguini-form-page">
      <form className="yoguini-form" onSubmit={handleSubmit}>
        <header className="yoguini-form-heading">
          <div className="yoguini-profile-header-bar">
            <div>
              <p className="yoguinis-eyebrow">Mi cuenta</p>
              <h1>Mis datos personales</h1>
            </div>
            {usuarioLogueado && (
              <span className={`yoguini-profile-role-badge ${isAdmin ? 'badge-admin' : 'badge-user'}`}>
                {isAdmin ? 'Administrador' : 'Alumno'}
              </span>
            )}
          </div>
          <p>Consultá y mantené actualizada tu información personal.</p>
        </header>

        {error && <p className="yoguini-message yoguini-message-error" role="alert">{error}</p>}
        {success && <p className="yoguini-message yoguini-message-success" role="status">{success}</p>}

        <div className="yoguini-form-fields">
          <label className="yoguini-field">
            Nombre
            <input
              name="nombre"
              type="text"
              autoComplete="given-name"
              value={values.nombre}
              onChange={updateField}
              required
              maxLength="255"
              disabled={saving}
            />
          </label>

          <label className="yoguini-field">
            Apellido
            <input
              name="apellido"
              type="text"
              autoComplete="family-name"
              value={values.apellido}
              onChange={updateField}
              required
              maxLength="255"
              disabled={saving}
            />
          </label>

          <label className="yoguini-field yoguini-field-wide">
            Correo electrónico
            <input
              name="email"
              type="email"
              autoComplete="email"
              value={values.email}
              onChange={updateField}
              required
              maxLength="255"
              disabled={saving}
            />
          </label>

          <label className="yoguini-field">
            Teléfono
            <input
              name="telefono"
              type="tel"
              autoComplete="tel"
              value={values.telefono}
              onChange={updateField}
              required
              maxLength="255"
              disabled={saving}
            />
          </label>

          <label className="yoguini-field">
            Fecha de nacimiento
            <input
              name="fechaNacimiento"
              type="date"
              value={values.fechaNacimiento}
              onChange={updateField}
              required
              disabled={saving}
            />
          </label>

          <label className="yoguini-field yoguini-field-wide">
            Dirección
            <input
              name="direccion"
              type="text"
              autoComplete="street-address"
              value={values.direccion}
              onChange={updateField}
              required
              maxLength="255"
              disabled={saving}
            />
          </label>

          <label className="yoguini-field">
            Número
            <input
              name="numero"
              type="number"
              min="0"
              step="1"
              value={values.numero}
              onChange={updateField}
              required
              disabled={saving}
            />
          </label>
        </div>

        <div className="yoguini-password-section">
          <button
            type="button"
            className="yoguini-password-toggle"
            onClick={() => {
              setShowPasswordFields(prev => !prev)
              setError('')
            }}
          >
            {showPasswordFields ? '− Cancelar cambio de contraseña' : '+ Cambiar contraseña'}
          </button>

          {showPasswordFields && (
            <div className="yoguini-form-fields yoguini-password-fields">
              <label className="yoguini-field">
                Nueva contraseña
                <input
                  name="password"
                  type="password"
                  autoComplete="new-password"
                  value={values.password}
                  onChange={updateField}
                  minLength="8"
                  placeholder="Mínimo 8 caracteres"
                  disabled={saving}
                />
              </label>

              <label className="yoguini-field">
                Confirmar nueva contraseña
                <input
                  name="password_confirmation"
                  type="password"
                  autoComplete="new-password"
                  value={values.password_confirmation}
                  onChange={updateField}
                  minLength="8"
                  placeholder="Repetí la nueva contraseña"
                  disabled={saving}
                />
              </label>
            </div>
          )}
        </div>

        <div className="yoguini-form-actions">
          <button className="yoguini-button yoguini-button-primary" type="submit" disabled={saving}>
            {saving ? 'Guardando...' : 'Guardar cambios'}
          </button>
          <Link className="yoguini-button yoguini-button-secondary" to="/">
            Volver al inicio
          </Link>
        </div>
      </form>
    </section>
  )
}

export default Perfil
