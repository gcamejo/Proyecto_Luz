<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Yoguini;
use Illuminate\Support\Facades\Hash;

class YoguiniController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $Yoguinis = Yoguini::all();
               
        return $Yoguinis;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $Yoguini = new Yoguini;
        $Yoguini->nombre = $request->nombre;
        $Yoguini->apellido = $request->apellido;
        $Yoguini->direccion = $request->direccion;
        $Yoguini->numero = $request->numero;
        $Yoguini->telefono = $request->telefono;
        $Yoguini->fechaNacimiento = $request->fechaNacimiento;
        $Yoguini->email = $request->email;
        $Yoguini->password = Hash::make($request->password) ;
        $Yoguini->perfil = $request->perfil;

        $Yoguini->save();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $Yoguini = Yoguini::find($id);
        return $Yoguini;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $Yoguini = Yoguini::find($id);
        $Yoguini->nombre = $request->nombre;
        $Yoguini->apellido = $request->apellido;
        $Yoguini->direccion = $request->direccion;
        $Yoguini->numero = $request->numero;
        $Yoguini->telefono = $request->telefono;
        $Yoguini->fechaNacimiento = $request->fechaNacimiento;
        $Yoguini->email = $request->email;
        $Yoguini->password = $request->password;
        

        $Yoguini->save();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $Yoguini = Yoguini::destroy($id);
        return 200;
    }

    public function login(Request $request)

    {
        
        $Yoguini = Yoguini::where('email', $request -> email)->first();
        

        if ($Yoguini && Hash::check($request->password, $Yoguini->password)) {
                        
            $token = $Yoguini->createToken('auth_token')->plainTextToken;
              
            $respon = [
                    'status_code' => 200,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user_log' => $Yoguini,
                    
                      ];
        
                      
        }else{
            
            
            $respon = [
                'status_code' => 404,
                'access_token' => null,
                'token_type' => 'Bearer',
                'user_log' => null,
                
            ];
            
        } 
                      
    return response($respon);
        

    }
}
