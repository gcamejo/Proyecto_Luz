import { createSlice } from "@reduxjs/toolkit";
import axios from "../../../api/axios";

const apiEndPoint = 'api/turnos/';

export const turnosSlice = createSlice({
    name:'turnos',
    initialState:{
        listadoTurnos:[],
        turno:[]
    },
    reducers:{
        setListadoTurnos:(estado,action)=>{
            estado.listadoTurnos = action.payload
        },
        setTurno:(estado,action)=>{
            estado.turno = action.payload
        },
        patchTurno:(estado,action)=>{
            estado.turno = action.payload
        }
    }

})

export const {setListadoTurnos, setTurno, patchTurno} = turnosSlice.actions

export default turnosSlice.reducer

export const getListadoTurnos= () => (dispatch) => {
    axios.get(apiEndPoint)
    .then(res => {
        dispatch(setListadoTurnos(res.data.sort((a,b) => a.nombre > b.nombre ? 1 : -1)))
    })
}

export const getListadoTurnosOrdenadosHora = () => (dispatch) => {
    axios.get(apiEndPoint)
    .then(res => {
        dispatch(setListadoTurnos(res.data.sort((a,b) => a.hora1 > b.hora1 || a.hora2 > a.hora2 ? 1 : -1)))
    })
}

export const getTurno = (id) => (dispatch) => {
    axios.get(apiEndPoint + id)
    .then(res => {
        dispatch(setTurno(res.data))
    })
}

export const updateTurno = (id, values) => (dispatch) => {
    axios.patch(apiEndPoint + id, values)
    .then(res => {
        dispatch(patchTurno(res.data))
    })
}

export const createTurno = (values) => {
    axios.post(apiEndPoint, values)
}

export const deleteTurno = (id) => {
    axios.delete(apiEndPoint + id)
}