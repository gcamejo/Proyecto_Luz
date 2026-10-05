import React from 'react'

const CardMensaje = ({mensaje}) => {

  return (
    <div className="alert alert-success" role="alert">
        El archivo {mensaje} subio correctamente
    </div>
  )
}

export default CardMensaje