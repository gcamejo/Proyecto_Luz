import { createSlice } from "@reduxjs/toolkit";
import axios from "../../../api/axios";

const apiEndPoint = 'api/yoguinis/';

export const yoguinisSlice = createSlice({
    name:'yoguinis',
    initialState:{
        fichasyoguinis:[],
        ficha:[],
        isLoading:false,
        error:null
    },
    reducers:{
        setFichasYoguinis:(estado,action)=>{
            estado.fichasyoguinis = action.payload
        },
        setFicha:(estado,action)=>{
            estado.ficha = action.payload
        },
        patchFicha:(estado,action)=>{
            estado.ficha = action.payload
            estado.fichasyoguinis = estado.fichasyoguinis.map(ficha =>
                ficha.id === action.payload.id ? action.payload : ficha
            ).sort((a, b) => a.nombre.localeCompare(b.nombre))
        },
        removeFicha:(estado,action)=>{
            estado.fichasyoguinis = estado.fichasyoguinis.filter(ficha => ficha.id !== action.payload)
            if (estado.ficha?.id === action.payload) estado.ficha = []
        },
        addFicha:(estado,action)=>{
            estado.fichasyoguinis = [...estado.fichasyoguinis, action.payload]
                .sort((a, b) => a.nombre.localeCompare(b.nombre))
        },
        setLoading:(estado,action)=>{
            estado.isLoading = action.payload
        },
        setError:(estado,action)=>{
            estado.error = action.payload
        }
        
        
    }
})

export const { setFichasYoguinis, setFicha, patchFicha, removeFicha, addFicha, setLoading, setError } = yoguinisSlice.actions

export default yoguinisSlice.reducer

export const getFichasYoguinis = () => async (dispatch) => {
    dispatch(setLoading(true))
    dispatch(setError(null))
    try {
        const { data } = await axios.get(apiEndPoint)
        dispatch(setFichasYoguinis([...data].sort((a, b) => a.nombre.localeCompare(b.nombre))))
        return data
    } catch (error) {
        dispatch(setError(error.response?.data?.message || error.message))
        throw error
    } finally {
        dispatch(setLoading(false))
    }
}

export const getFicha = (id) => async (dispatch) => {
    try {
        const { data } = await axios.get(apiEndPoint + id)
        dispatch(setFicha(data))
        return data
    } catch (error) {
        throw error
    }
}

export const updateFicha = (id, values) => async (dispatch) => {
    const { data } = await axios.patch(apiEndPoint + id, values)
    dispatch(patchFicha(data))
    return data
}

export const createFicha = (values) => async (dispatch) => {
    const { data } = await axios.post(apiEndPoint, values)
    if (data?.id) dispatch(addFicha(data))
    return data
}

export const deleteFicha = (id) => async (dispatch) => {
    await axios.delete(apiEndPoint + id)
    dispatch(removeFicha(Number(id)))
    return id
}
