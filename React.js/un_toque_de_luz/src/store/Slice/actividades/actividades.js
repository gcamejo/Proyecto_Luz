import { createSlice } from "@reduxjs/toolkit";
import axios from "axios";

const apiEndPoint = 'api/actividades/';

export const actividadesSlice = createSlice({
    name:'actividades',
    initialState:{
        actividades:[],
        actividad:[],
        isLoading:true,
        error:null
        
        
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
        },
        replaceActividad:(estado,action)=>{
            estado.actividades = estado.actividades.map(actividad =>
                String(actividad.id) === String(action.payload.id) ? {...actividad, ...action.payload} : actividad
            )
        },
        removeActividad:(estado,action)=>{
            estado.actividades = estado.actividades.filter(actividad => String(actividad.id) !== String(action.payload))
        },
        setActividadesLoading:(estado,action)=>{
            estado.isLoading = action.payload
        },
        setActividadesError:(estado,action)=>{
            estado.error = action.payload
        }
        
    }
})

export const { setActividades, setActividad, patchActividad, replaceActividad, removeActividad, setActividadesLoading, setActividadesError} = actividadesSlice.actions

export default actividadesSlice.reducer

export const getActividades = () => (dispatch) => {
    dispatch(setActividadesLoading(true))
    dispatch(setActividadesError(null))
    return axios.get(apiEndPoint)
        .then(res => {
            dispatch(setActividades(res.data))
        })
        .catch(error => {
            dispatch(setActividadesError(error.response?.data?.message || error.message || 'No se pudieron cargar las actividades.'))
        })
        .finally(() => {
            dispatch(setActividadesLoading(false))
        })
}

export const getActividad = (id) => (dispatch) => {
    return axios.get(apiEndPoint + id)
        .then(res => {
            dispatch(setActividad(res.data))
            return res.data
        })
}

export const updateActividad = (id, values) => (dispatch) => {
    return axios.patch(apiEndPoint + id, values)
        .then(res=>{
            const responseValues = res.data && typeof res.data === 'object' ? res.data : {}
            const actividadActualizada = {...values, ...responseValues, id}
            dispatch(patchActividad(actividadActualizada))
            dispatch(replaceActividad(actividadActualizada))
            return res
        })
}

export const createActividad = (values)  => {
    return axios.post(apiEndPoint, values)
       
}

export const deleteActividad = (id) => (dispatch) => {
    return axios.delete(apiEndPoint + id)
        .then(res => {
            dispatch(removeActividad(id))
            return res
        })
}