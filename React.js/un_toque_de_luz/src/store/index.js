import { configureStore } from "@reduxjs/toolkit";
import fichasyoguinis from "./Slice/Yoguinis/yoguinis";
import ficha from "./Slice/Yoguinis/yoguinis";
import listadoTurnos from "./Slice/turnos/turnos";
import turno from "./Slice/turnos/turnos";
import listaServicios from "./Slice/servicios/servicios";
import listaHorarios from "./Slice/horarios/horarios";
import sessionStart from "./Slice/sessionStart/sessionStart";
import usuarioLogueado from "./Slice/loginUsuario/loginUsuario";
import token from "./Slice/loginUsuario/loginUsuario";
import codigo from "./Slice/loginUsuario/loginUsuario";
import error from "./Slice/loginUsuario/loginUsuario";
import actividades from "./Slice/actividades/actividades";
import actividad from "./Slice/actividades/actividades";


export default configureStore({
    reducer:{
        fichasyoguinis,
        ficha,
        listadoTurnos,
        turno,
        listaServicios,
        listaHorarios,
        sessionStart,
        usuarioLogueado,
        token,
        codigo,
        error,
        actividades,
        actividad,
        
          
    }
})