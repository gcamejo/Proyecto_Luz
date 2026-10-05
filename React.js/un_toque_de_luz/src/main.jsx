import React from 'react'
import ReactDOM from 'react-dom/client'
import { Provider } from 'react-redux'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.min.js'
import store from './store'


import './index.css'

import App from './pages/App'



ReactDOM.createRoot(document.getElementById('root')).render(
  <Provider store={store}>
    <React.StrictMode>      
       <App/>
    </React.StrictMode>
  </Provider>
)
