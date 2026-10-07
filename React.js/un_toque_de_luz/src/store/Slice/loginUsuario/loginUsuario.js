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

export const validarUsuario = (values) => (dispatch) => {
    axios.get('sanctum/csrf-cookie')
        .then (res =>{
                axios.post (apiEndPoint, values)
                    
                    .then(res=>{
                            dispatch(setUsuarioLogueado(res.data.user_log))
                            dispatch(setToken(res.data.access_token))
                            dispatch(setCodigo(res.data.status_code))
                            dispatch(setError(null))
                    })
            })
        .catch ((err) => dispatch(setError(err.message)))
               
}

export const deleteUsuario = () => (dispatch) => {
    dispatch(setUsuarioLogueado(null))
    dispatch(setToken(null))
    dispatch(setCodigo(null))
    
}


