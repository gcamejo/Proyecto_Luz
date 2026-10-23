import { createSlice } from "@reduxjs/toolkit";
import axios from "../../../api/axios";


const apiEndPoint = 'api/login/';


export const loginUsuarioSlice = createSlice({
    name:'loginUsuario',
    initialState:{
        usuarioLogueado:(null),
        token:(null),
        codigo:(null),
        error:(null),
        
    },
    reducers:{
               
        setUsuarioLogueado:(estado,action)=>{
            estado.usuarioLogueado = action.payload
        },
        setToken:(estado,action)=>{
            estado.token = action.payload
        },
        setCodigo:(estado,action)=>{
            estado.codigo = action.payload
        },
        setError:(estado,action)=>{
            estado.error = action.payload
        },
        
    }
})

export const {setUsuarioLogueado,setToken,setCodigo,setError} = loginUsuarioSlice.actions

export default loginUsuarioSlice.reducer

export const validarUsuario = (values) => async (dispatch) => {
    try {
        const response = await axios.post(apiEndPoint, values)
        dispatch(setUsuarioLogueado(response.data.user_log))
        dispatch(setToken(response.data.access_token))
        dispatch(setCodigo(response.data.status_code))
        dispatch(setError(null))
    } catch (error) {
        dispatch(setError(error.message))
    }
}

export const deleteUsuario = () => (dispatch) => {
    dispatch(setUsuarioLogueado(null))
    dispatch(setToken(null))
    dispatch(setCodigo(null))
    dispatch(setError(null))
    
}


