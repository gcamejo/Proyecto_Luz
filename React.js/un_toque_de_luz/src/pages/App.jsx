import { BrowserRouter, Route, Routes} from "react-router-dom"
import axios from "axios"
import { useSelector } from 'react-redux'


import '../Styles/App.css'
import '../Styles/Navbar.css'


import Navbar from "../components/Navbar"


import Inicio from "./Inicio"
import Actividades from "./Actividades"
import VerTurnos from "./VerTurnos"
import TurnoCreate from "./TurnoCreate"
import Login from "./Login"
import VerFichas from "./VerFichas"
import FichaCreate from "./FichaCreate"
import RutaNovalida from "../components/RutaNovalida"
import TurnoEdit from "./TurnoEdit"
import TurnoDelete from "./TurnoDelete"
import FichaDelete from "./FichaDelete"
import FichaEdit from "./FichaEdit"
import VerTurnosPorDias from "./VerTurnosPorDias"
import AdminServicio from "./AdminServicio"
import AdminHorarios from "./AdminHorarios"
import Footer from "../components/Footer"
import ActividadesEdit from "./ActividadesEdit"
import ActividadesCreate from "./ActividadesCreate"
import BookingStudent from "./BookingStudent"
import BookingAdmin from "./BookingAdmin"
import MyBookings from "./MyBookings"





function App() {


const {token} = useSelector((estado=>estado.token))

axios.defaults.baseURL = 'http://localhost:8000/';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

return (
  <>

  
  <BrowserRouter>
    
          <header>

              <Navbar/>

          </header>
          <main>

                <Routes >
                  <Route path="*" element={<RutaNovalida/>}/>
                  <Route path="/" element={<Inicio/>}/>
                  <Route path="/actividades" element={<Actividades admin={false}/>}/>
                  <Route path="/login" element={<Login/>}/>
                  <Route path="/cargarFicha/" element={<FichaCreate/>}/>
                  {token ?
                          <>
                          <Route path="/verTurnos" element={<VerTurnos/>}/>
                          <Route path="/verTurnosPorDias" element={<VerTurnosPorDias/>}/>
                          <Route path="/cargarTurno" element={<TurnoCreate/>}/>
                          <Route path="/editarTurno/:id" element={<TurnoEdit/>}/>
                          <Route path="/borrarTurno/:id" element={<TurnoDelete/>}/>
                          <Route path="/verFichas" element={<VerFichas/>}/>
                          <Route path="/editarFicha/:id" element={<FichaEdit/>}/>
                          <Route path="/borrarFicha/:id" element={<FichaDelete/>}/>
                          <Route path="/adminServicios" element={<AdminServicio/>}/>
                          <Route path="/adminHorarios" element={<AdminHorarios/>}/>
                          <Route path="/adminActividades" element={<Actividades admin={true}/>}/>
                          <Route path="/nuevaActividad" element={<ActividadesCreate/>}/>
                          <Route path="/editarActividad/:id" element={<ActividadesEdit/>}/> 
                          <Route path="/reservarCiclo" element={<BookingStudent/>}/>
                          <Route path="/misReservas" element={<MyBookings/>}/>
                          {token && <Route path="/adminReservas" element={<BookingAdmin/>}/>}
                          </> 
                          :
                          ''}
                </Routes>
        </main>
          
  </BrowserRouter>
          
        <footer>
                <Footer/>    

        </footer>
          
  

  
  
    
    </>
  )
}

export default App
