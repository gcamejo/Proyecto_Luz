import React, { useState } from 'react'
import { useNavigate } from 'react-router'
import { useForm } from '../hooks/useForm'

import '../Styles/Actividades.css'
import UploadFile from '../components/UploadFile'
import { Link } from 'react-router-dom'
import { createActividad } from '../store/Slice/actividades/actividades'
import { BsArrowLeft } from 'react-icons/bs'

const ActividadesCreate = () => {

    
    const navigate = useNavigate()

    const [values,handleInputChange,setValues] = useForm({
        urlImagen:'',
        titulo:'',
        descripcion:'',
        horarios:''
      })
    const [saving, setSaving] = useState(false)
    const [saveError, setSaveError] = useState(null)
    
    const {titulo, descripcion, horarios} = values

    const handleSubmit = async (e) =>{
        e.preventDefault()
        if (saving) return
        setSaving(true)
        setSaveError(null)
        try {
          await createActividad(values)
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
        <h1>Nueva actividad</h1>
        <p>Completá la información para publicarla en actividades.</p>
      </header>

      <form className='actividad-form' onSubmit={handleSubmit}>
        <div className='actividad-form-layout'>
          <section className='actividad-form-photo' aria-labelledby='actividad-photo-heading'>
            <div className='actividad-form-section-title'>
              <span aria-hidden='true'>01</span>
              <div>
                <h2 id='actividad-photo-heading'>Foto de portada</h2>
                <p>Opcional</p>
              </div>
            </div>
            <UploadFile onUpload={(filename) => setValues(current => ({...current, urlImagen: filename}))}/>
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
              <input
                type='text'
                id='titulo'
                name='titulo'
                value={titulo}
                onChange={handleInputChange}
                placeholder='Ej.: Yoga suave'
                maxLength={120}
                required
              />
            </div>

            <div className='actividad-form-field'>
              <label htmlFor='descripcion'>Descripción *</label>
              <textarea
                id='descripcion'
                name='descripcion'
                value={descripcion}
                onChange={handleInputChange}
                placeholder='Contá brevemente en qué consiste la actividad'
                rows={5}
                required
              />
            </div>

            <div className='actividad-form-field'>
              <label htmlFor='horarios'>Horarios *</label>
              <input
                type='text'
                id='horarios'
                name='horarios'
                value={horarios}
                onChange={handleInputChange}
                placeholder='Ej.: Martes y jueves, 18:00'
                required
              />
            </div>
          </div>
        </div>

        <div className='actividad-form-footer'>
          {saveError && <p className='actividad-form-error' role='alert'>{saveError}</p>}
          <div className='actividad-form-actions'>
            <Link className='actividad-form-cancel' to='/adminActividades'>
              <BsArrowLeft aria-hidden='true' />
              <span>Volver</span>
            </Link>
            <button className='actividad-form-submit' type='submit' disabled={saving}>
              {saving ? 'Guardando...' : 'Guardar actividad'}
            </button>
          </div>
        </div>
      </form>
    </section>
    </>
  )
}

export default ActividadesCreate