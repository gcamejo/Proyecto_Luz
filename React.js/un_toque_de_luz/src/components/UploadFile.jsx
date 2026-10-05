import axios from 'axios';
import React, { useState } from 'react'
import CardMensaje from './CardMensaje';
import Loading from './Loading';


const UploadFile = () => {
    const [selectedFile, setSelectedFile] = useState(null)
    const [mensaje, setMensaje] = useState(null)
    const [loading, setLoading] = useState(false)

  const handleFileUpload = (event) => {
    setSelectedFile(event.target.files[0])
    }
    
  const handleUpload = () => {
    setLoading(true)
    const formData = new FormData()
    formData.append('file', selectedFile)
    axios.post('api/actividadesImg/' , formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
      .then((response) => {
        setLoading(false)
        setMensaje(response.data)
      })
      .catch((error) => {
        console.log(error);
      })
    }
    /*
    <input  type = "file" name = "file" onChange={handleFileUpload}/>
      <button className="btn btn-success" onClick={handleUpload}>Subir</button>
    */

  return (
    <>

    <div>

      <div className="input-group mb-3 p-5" >
        <input type="file" className="form-control" onChange={handleFileUpload}/>
        <button className="input-group-text" onClick={handleUpload}>Subir</button>
      </div>
        {loading ? <Loading clase = {"spinner-border text-primary"}/> : ''}
        {mensaje ? <CardMensaje mensaje = {mensaje}/> : ""}
      
    </div>
    
    
    
    </>
  )
}

export default UploadFile