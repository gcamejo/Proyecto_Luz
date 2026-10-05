import { useState } from "react"


export const useForm =(estadoInicial={}) =>{

    const[values, setValues] =useState(estadoInicial)

    const handleInputChange = ({target})=>{

        setValues({
            ...values,
            [target.name]:target.value
        })
    }
    

    return [values, handleInputChange, setValues]
}