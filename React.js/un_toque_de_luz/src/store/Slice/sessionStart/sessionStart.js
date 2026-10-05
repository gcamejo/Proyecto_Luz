import { createSlice } from "@reduxjs/toolkit";

export const sessionStartSlice = createSlice({
    name:"sessionStart",
    initialState:{
        sessionStart:[]
    },
    reducers:{
        setSessionStart:(estado,action) => {
            estado.sessionStart = action.payload
        }
    }
})

export const {setSessionStart} = sessionStartSlice.actions

export default sessionStartSlice.reducer

export const sessionStartOn = (usuario) => {

    sessionStorage.setItem(usuarioLogueado, usuario)
    
    return usuarioLogueado
}