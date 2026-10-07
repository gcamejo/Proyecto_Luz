import React from 'react'
import ReactDOM from 'react-dom/client'
import { Provider } from 'react-redux'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.min.js'
import store from './store'
import { setTokenProvider } from './api/axios'


import './index.css'

import App from './pages/App'


setTokenProvider(() => store.getState().token.token)

ReactDOM.createRoot(document.getElementById('root')).render(
  <Provider store={store}>
    <React.StrictMode>      
       <App/>
    </React.StrictMode>
  </Provider>
)
