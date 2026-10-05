import { createSlice } from "@reduxjs/toolkit";
import axios from "axios";

const apiEndPoint = 'api/actividades/'

export const serviciosSlice = createSlice({
    name:"servicios",
    initialState:{
        listaServicios:[]
    },
    reducers:{
        setServicios:(estado,action) => {
            estado.listaServicios = action.payload
        }
    }
})

export const { setServicios } = serviciosSlice.actions

export default serviciosSlice.reducer

export const getServicios = () => (dispatch) => {
    axios.get(apiEndPoint)
    .then (res => {
        dispatch(setServicios(res.data.sort((a,b) => a.servicio > b.servicio ? 1 : -1)))
    })
}

export const createServicio = (values) => {
    axios.post(apiEndPoint, values)
}

export const deleteServicio = (id) => {
    axios.delete(apiEndPoint + id)
}