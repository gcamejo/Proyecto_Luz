import axios from '../api/axios';
import React, { useState } from 'react'
import Loading from './Loading';


const UploadFile = ({onUpload}) => {
    const [selectedFile, setSelectedFile] = useState(null)
    const [mensaje, setMensaje] = useState(null)
    const [error, setError] = useState(null)
    const [loading, setLoading] = useState(false)

  const handleFileUpload = (event) => {
    const file = event.target.files[0]
    setMensaje(null)
    setError(null)
    if (file && !file.type.startsWith('image/')) {
      setSelectedFile(null)
      setError('Seleccioná un archivo de imagen válido.')
      event.target.value = ''
      return
    }
    setSelectedFile(file || null)
    }
    
  const handleUpload = async () => {
    if (!selectedFile || loading) return
    setLoading(true)
    setError(null)
    setMensaje(null)
    try {
      const formData = new FormData()
      formData.append('file', selectedFile)
      const response = await axios.post('api/actividadesImg/' , formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
      setMensaje(response.data)
      onUpload?.(response.data)
    } catch (uploadError) {
      setError(uploadError.response?.data?.message || uploadError.message || 'No se pudo subir la imagen.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className='actividad-upload'>
      <label className='actividad-upload-label' htmlFor='actividad-image-file'>Seleccioná un archivo</label>
      <input
        id='actividad-image-file'
        className='actividad-upload-input'
        type='file'
        accept='image/*'
        onChange={handleFileUpload}
      />
      <button className='actividad-upload-button' type='button' onClick={handleUpload} disabled={!selectedFile || loading}>
        {loading ? 'Subiendo...' : 'Subir foto'}
      </button>
      {loading && <Loading clase='spinner-border text-primary'/>}
      {error && <p className='actividad-upload-error' role='alert'>{error}</p>}
      {mensaje && <p className='actividad-upload-success' role='status'>Foto subida: {mensaje}</p>}
    </div>
  )
}

export default UploadFile