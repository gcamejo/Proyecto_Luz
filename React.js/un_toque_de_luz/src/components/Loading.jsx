import React from 'react'

const Loading = ({clase}) => {
  //<div className="d-flex justify-content-center">
  //</div>
  
  return (
    <div className="text-center">

        <div className={clase} role="status">
            <span className="visually-hidden"></span>
        </div>
    </div>
  )
}

export default Loading