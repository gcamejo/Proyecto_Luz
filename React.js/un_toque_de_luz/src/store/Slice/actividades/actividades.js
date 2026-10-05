import { createSlice } from "@reduxjs/toolkit";
import axios from "axios";

const apiEndPoint = 'api/actividades/';

export const actividadesSlice = createSlice({
    name:'actividades',
    initialState:{
        actividades:[],
        actividad:[]
        
        
    },
    reducers:{
        setActividades:(estado,action)=>{
            estado.actividades = action.payload
        },
        setActividad:(estado,action)=>{
            estado.actividad = action.payload
        },
        patchActividad:(estado,action)=>{
            estado.actividad = action.payload
        }
        
    }
})

export const { setActividades, setActividad, patchActividad} = actividadesSlice.actions

export default actividadesSlice.reducer

export const getActividades = () => (dispatch) => {
    axios.get(apiEndPoint)
        .then(res => {
            dispatch(setActividades(res.data))
        })
        
}

export const getActividad = (id) => (dispatch) => {
    axios.get(apiEndPoint + id)
        .then(res => {
            dispatch(setActividad(res.data))
        })
        
}

export const updateActividad = (id, values) => (dispatch) => {
    axios.patch(apiEndPoint + id, values)
        .then(res=>{
            dispatch(patchActividad(res.data))
        })
}

export const createActividad = (values)  => {
    axios.post(apiEndPoint, values)
       
}

export const deleteActividad = (id)  => {
    axios.delete(apiEndPoint + id)
    .then(res=>console.log(res.data))
       
}