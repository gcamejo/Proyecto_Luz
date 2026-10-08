import React, { useEffect, useState } from 'react'
import { useDispatch } from 'react-redux'
import { getActividad, updateActividad } from '../store/Slice/actividades/actividades'
import { useNavigate, useParams } from 'react-router'
import { useForm } from '../hooks/useForm'
import { Link } from 'react-router-dom'
import UploadFile from '../components/UploadFile'
import { BsArrowLeft } from 'react-icons/bs'
import { apiAssetUrl } from '../api/axios'

import '../Styles/Actividades.css'

const ActividadesEdit = () => {
  
  const {id} = useParams()
  
  const dispatch = useDispatch()
  
  const navigate = useNavigate()
  
  const [values,handleInputChange, setValues] = useForm({
    urlImagen:'',
    titulo:'',
    descripcion:'',
    horarios:''
  })
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState(null)

  useEffect(() => {
    let active = true
    setLoading(true)
    setLoadError(null)
    dispatch(getActividad(id))
      .then(actividad => {
        if (!active) return
        setValues({
          urlImagen: actividad.urlImagen || '',
          titulo: actividad.titulo || '',
          descripcion: actividad.descripcion || '',
          horarios: actividad.horarios || ''
        })
      })
      .catch(error => {
        if (active) setLoadError(error.response?.data?.message || error.message || 'No se pudo cargar la actividad.')
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => { active = false }
  }, [dispatch, id, setValues])

  const {urlImagen, titulo, descripcion, horarios} = values
  const img = urlImagen ? apiAssetUrl(`storage/img/${encodeURIComponent(urlImagen)}`) : null

  const handleSubmit = async (e) =>{
    e.preventDefault()
    if (saving) return
    setSaving(true)
    setSaveError(null)
    try {
      await dispatch(updateActividad(id, values))
      navigate('/adminActividades')
    } catch (error) {
      setSaveError(error.response?.data?.message || error.message || 'No se pudo guardar la actividad.')
    } finally {
      setSaving(false)
    }
  }
  

  return (
    <>
    <section className='actividad-form-page'>
      <header className='actividad-form-header'>
        <p className='actividad-form-eyebrow'>Administración</p>
        <h1>Editar actividad</h1>
        <p>Actualizá la información de la actividad.</p>
      </header>

      {loading ? <p className='actividad-form-status' role='status'>Cargando actividad...</p> : loadError ? <div className='actividad-form-load-error' role='alert'>
        <p>{loadError}</p>
        <Link className='actividad-form-cancel' to='/adminActividades'><BsArrowLeft aria-hidden='true' /><span>Volver</span></Link>
      </div> : <form className='actividad-form' onSubmit={handleSubmit}>
        <div className='actividad-form-layout'>
          <section className='actividad-form-photo' aria-labelledby='actividad-photo-heading'>
            <div className='actividad-form-section-title'>
              <span aria-hidden='true'>01</span>
              <div>
                <h2 id='actividad-photo-heading'>Foto de portada</h2>
                <p>Conservá la actual o subí una nueva.</p>
              </div>
            </div>
            {img ? <img className='actividad-edit-image' src={img} alt={`Imagen actual de ${titulo}`} /> : <p className='actividad-form-status'>Esta actividad no tiene una foto.</p>}
            {urlImagen && <p className='actividad-edit-filename' role='status'>Archivo actual: {urlImagen}</p>}
            <UploadFile onUpload={filename => setValues(current => ({...current, urlImagen: filename}))}/>
          </section>

          <div className='actividad-form-fields'>
            <div className='actividad-form-section-title'>
              <span aria-hidden='true'>02</span>
              <div>
                <h2>Datos de la actividad</h2>
                <p>Los campos marcados con * son obligatorios.</p>
              </div>
            </div>
            <div className='actividad-form-field'>
              <label htmlFor='titulo'>Título *</label>
              <input id='titulo' name='titulo' type='text' value={titulo} onChange={handleInputChange} maxLength={120} required />
            </div>
            <div className='actividad-form-field'>
              <label htmlFor='descripcion'>Descripción *</label>
              <textarea id='descripcion' name='descripcion' value={descripcion} onChange={handleInputChange} rows={5} required />
            </div>
            <div className='actividad-form-field'>
              <label htmlFor='horarios'>Horarios *</label>
              <input id='horarios' name='horarios' type='text' value={horarios} onChange={handleInputChange} required />
            </div>
          </div>
        </div>
        <div className='actividad-form-footer'>
          {saveError && <p className='actividad-form-error' role='alert'>{saveError}</p>}
          <div className='actividad-form-actions'>
            <Link className='actividad-form-cancel' to='/adminActividades'><BsArrowLeft aria-hidden='true' /><span>Volver</span></Link>
            <button className='actividad-form-submit' type='submit' disabled={saving}>{saving ? 'Guardando...' : 'Guardar cambios'}</button>
          </div>
        </div>
      </form>}
    </section>
    </>
  )
}

export default ActividadesEdit