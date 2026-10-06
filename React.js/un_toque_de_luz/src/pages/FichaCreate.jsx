import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useDispatch, useSelector } from 'react-redux'
import { createFicha } from '../store/Slice/Yoguinis/yoguinis'
import '../Styles/Yoguinis.css'

const errorMessage = error => {
  const validationErrors = error.response?.data?.errors
  return validationErrors ? Object.values(validationErrors).flat().join(' ') : error.response?.data?.message || error.message || 'No se pudo crear la ficha.'
}

const FichaCreate = () => {
  const dispatch = useDispatch()
  const navigate = useNavigate()
  const { token } = useSelector(state => state.token)
  const isAdminCreate = Boolean(token)
  const [values, setValues] = useState({
    nombre: '', apellido: '', direccion: '', numero: '', telefono: '',
    fechaNacimiento: '', email: '', password: '', perfil: 'user'
  })
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  const updateField = event => setValues(current => ({ ...current, [event.target.name]: event.target.value }))

  const handleSubmit = async event => {
    event.preventDefault()
    setError('')
    if (values.password !== passwordConfirmation) {
      setError('Las contraseñas no coinciden.')
      return
    }

    setSaving(true)
    try {
      const { repassword, ...payload } = { ...values, perfil: 'user' }
      await dispatch(createFicha(payload))
      navigate(isAdminCreate ? '/verFichas' : '/login')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  return (
    <section className="yoguini-form-page">
      <form className="yoguini-form" onSubmit={handleSubmit}>
        <header className="yoguini-form-heading">
          <p className="yoguinis-eyebrow">Un toque de luz</p>
          <h1>{isAdminCreate ? 'Nueva ficha' : 'Registro de yoguini'}</h1>
          <p>Completá los datos para crear la ficha.</p>
        </header>

        {error && <p className="yoguini-message yoguini-message-error" role="alert">{error}</p>}

        <div className="yoguini-form-fields">
          <label className="yoguini-field">Nombre
            <input name="nombre" autoComplete="given-name" value={values.nombre} onChange={updateField} required maxLength="255" />
          </label>
          <label className="yoguini-field">Apellido
            <input name="apellido" autoComplete="family-name" value={values.apellido} onChange={updateField} required maxLength="255" />
          </label>
          <label className="yoguini-field yoguini-field-wide">Dirección
            <input name="direccion" autoComplete="street-address" value={values.direccion} onChange={updateField} required maxLength="255" />
          </label>
          <label className="yoguini-field">Número
            <input name="numero" type="number" min="0" step="1" value={values.numero} onChange={updateField} required />
          </label>
          <label className="yoguini-field">Teléfono
            <input name="telefono" type="tel" autoComplete="tel" value={values.telefono} onChange={updateField} required maxLength="255" />
          </label>
          <label className="yoguini-field">Fecha de nacimiento
            <input name="fechaNacimiento" type="date" value={values.fechaNacimiento} onChange={updateField} required />
          </label>
          <label className="yoguini-field yoguini-field-wide">Correo electrónico
            <input name="email" type="email" autoComplete="email" value={values.email} onChange={updateField} required maxLength="255" />
          </label>
          <label className="yoguini-field">Contraseña
            <input name="password" type="password" autoComplete="new-password" value={values.password} onChange={updateField} required minLength="8" />
          </label>
          <label className="yoguini-field">Confirmar contraseña
            <input type="password" autoComplete="new-password" value={passwordConfirmation} onChange={event => setPasswordConfirmation(event.target.value)} required minLength="8" />
          </label>
        </div>

        <div className="yoguini-form-actions">
          <button className="yoguini-button yoguini-button-primary" type="submit" disabled={saving}>
            {saving ? 'Guardando...' : 'Crear ficha'}
          </button>
          <Link className="yoguini-button yoguini-button-secondary" to={isAdminCreate ? '/verFichas' : '/login'}>Cancelar</Link>
        </div>
      </form>
    </section>
  )
}

export default FichaCreate