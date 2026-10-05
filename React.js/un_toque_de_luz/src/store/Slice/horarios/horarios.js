import { createSlice } from "@reduxjs/toolkit";
import axios from "axios";

const apiEndPoint = 'api/horarios/'

export const horariosSlice = createSlice({
    name:"horarios",
    initialState:{
        listaHorarios:[]
    },
    reducers:{
        setHorarios:(state,action) => {
            state.listaHorarios = action.payload
        }
    }

})

export const { setHorarios } = horariosSlice.actions

export default horariosSlice.reducer

export const getHorarios = () => (dispatch) => {
    axios.get(apiEndPoint)
    .then(res => {
        dispatch(setHorarios(res.data.sort((a,b) => a.horario > b.horario ? 1 : -1)))
    })

}

export const createHorario = (values) => {
    axios.post(apiEndPoint, values)
}

export const deleteHorario = (id) => {
    axios.delete(apiEndPoint + id)
}
