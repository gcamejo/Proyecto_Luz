import { createSlice } from "@reduxjs/toolkit";
import axios from "axios";

const apiEndPoint = 'api/yoguinis/';

export const yoguinisSlice = createSlice({
    name:'yoguinis',
    initialState:{
        fichasyoguinis:[],
        ficha:[]
        
        
        
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
        }
        
        
    }
})

export const { setFichasYoguinis, setFicha, patchFicha } = yoguinisSlice.actions

export default yoguinisSlice.reducer

export const getFichasYoguinis = () => (dispatch) => {
    axios.get(apiEndPoint)
        .then(res => {
            dispatch(setFichasYoguinis(res.data.sort((a,b) => a.nombre > b.nombre ? 1 : -1)))
        })
        
}

export const getFicha = (id) => (dispatch) => {
    axios.get(apiEndPoint + id)
        .then(res => {
            dispatch(setFicha(res.data))
        })
}

export const updateFicha = (id, values) => (dispatch) => {
    axios.patch(apiEndPoint + id, values)
        .then(res=>{
            dispatch(patchFicha(res.data))
        })
}

export const createFicha = (values)  => {
    axios.post(apiEndPoint, values)
       
}

export const deleteFicha = (id) => {
    axios.delete(apiEndPoint + id)          
}
