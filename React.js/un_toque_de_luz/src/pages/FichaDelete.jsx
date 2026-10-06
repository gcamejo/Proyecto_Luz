import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useNavigate, useParams } from 'react-router'
import { Link } from 'react-router-dom'
import { deleteFicha, getFicha } from '../store/Slice/Yoguinis/yoguinis'

const errorMessage = error => {
  const validationErrors = error.response?.data?.errors
  return validationErrors ? Object.values(validationErrors).flat().join(' ') : error.response?.data?.message || error.message || 'No se pudo completar la solicitud.'
}

const FichaDelete = () => {
  const { id } = useParams()
  const dispatch = useDispatch()
  const navigate = useNavigate()
  const [ficha, setFicha] = useState(null)
  const [loading, setLoading] = useState(true)
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    dispatch(getFicha(id))
      .then(record => { if (active) setFicha(record) })
      .catch(requestError => { if (active) setError(errorMessage(requestError)) })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [dispatch, id])

  const handleDelete = async () => {
    setDeleting(true)
    setError('')
    try {
      await dispatch(deleteFicha(id))
      navigate('/verFichas')
    } catch (requestError) {
      setError(errorMessage(requestError))
      setDeleting(false)
    }
  }

  if (loading) return <p className="yoguini-message yoguini-page-status" role="status">Cargando ficha...</p>

  return (
    <section className="yoguini-form-page">
      <div className="yoguini-form yoguini-delete-panel">
        <header className="yoguini-form-heading">
          <p className="yoguinis-eyebrow">Directorio de yoguinis</p>
          <h1>Eliminar ficha</h1>
        </header>
        {error && <p className="yoguini-message yoguini-message-error" role="alert">{error}</p>}
        {ficha && <div className="yoguini-dialog-person">
          <strong>{ficha.nombre} {ficha.apellido}</strong>
          <span>{ficha.direccion} {ficha.numero}</span>
          <span>{ficha.telefono} · {ficha.email}</span>
        </div>}
        {!ficha && !error && <p className="yoguini-message">No se encontró la ficha solicitada.</p>}
        {ficha && <p>Esta acción no se puede deshacer.</p>}
        <div className="yoguini-form-actions">
          {ficha && <button className="yoguini-button yoguini-button-danger" type="button" disabled={deleting} onClick={handleDelete}>
            {deleting ? 'Eliminando...' : 'Eliminar ficha'}
          </button>}
          <Link className="yoguini-button yoguini-button-secondary" to="/verFichas">Volver al directorio</Link>
        </div>
      </div>
    </section>
  )
}

export default FichaDelete