import React, { useEffect, useState } from 'react'
import { useDispatch } from 'react-redux'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { getFicha, updateFicha } from '../store/Slice/Yoguinis/yoguinis'
import '../Styles/Yoguinis.css'

const FichaEdit = () => {
    const { id } = useParams()
    const dispatch = useDispatch()
    const navigate = useNavigate()
    const [values, setValues] = useState({ nombre: '', apellido: '', direccion: '', numero: '', telefono: '', fechaNacimiento: '', email: '' })
    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)
    const [error, setError] = useState('')

    useEffect(() => {
      let active = true
      setLoading(true)
      setError('')
      dispatch(getFicha(id))
        .then(ficha => {
          if (active) setValues({
            nombre: ficha.nombre || '',
            apellido: ficha.apellido || '',
            direccion: ficha.direccion || '',
            numero: ficha.numero ?? '',
            telefono: ficha.telefono || '',
            fechaNacimiento: ficha.fechaNacimiento || '',
            email: ficha.email || ''
          })
        })
        .catch(requestError => { if (active) setError(requestError.response?.data?.message || requestError.message || 'No se pudo cargar la ficha.') })
        .finally(() => { if (active) setLoading(false) })
      return () => { active = false }
    }, [dispatch, id])

    const updateField = event => setValues(current => ({ ...current, [event.target.name]: event.target.value }))
    const handleSubmit = async event => {
      event.preventDefault()
      setSaving(true)
      setError('')
      try {
        await dispatch(updateFicha(id, values))
        navigate('/verFichas')
      } catch (requestError) {
        const validationErrors = requestError.response?.data?.errors
        setError(validationErrors ? Object.values(validationErrors).flat().join(' ') : requestError.response?.data?.message || requestError.message || 'No se pudo guardar la ficha.')
      } finally {
        setSaving(false)
      }
    }

    if (loading) return <p className="yoguini-message yoguini-page-status" role="status">Cargando ficha...</p>

    return (
      <section className="yoguini-form-page">
        <form className="yoguini-form" onSubmit={handleSubmit}>
          <header className="yoguini-form-heading">
            <p className="yoguinis-eyebrow">Directorio de yoguinis</p>
            <h1>Editar ficha</h1>
            <p>Actualizá los datos de contacto y perfil.</p>
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
          </div>
          <div className="yoguini-form-actions">
            <button className="yoguini-button yoguini-button-primary" type="submit" disabled={saving}>
              {saving ? 'Guardando...' : 'Guardar cambios'}
            </button>
            <Link className="yoguini-button yoguini-button-secondary" to="/verFichas">Cancelar</Link>
          </div>
        </form>
      </section>
    )
}

export default FichaEdit