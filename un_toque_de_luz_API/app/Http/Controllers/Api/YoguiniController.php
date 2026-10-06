<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Yoguini;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class YoguiniController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Yoguini::all()->makeHidden(['password']);
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
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'numero' => 'required|integer|min:0',
            'telefono' => 'required|string|max:255',
            'fechaNacimiento' => 'required|date',
            'email' => 'required|email|max:255|unique:yoguinis,email',
            'password' => 'required|string|min:8',
        ]);

        $Yoguini = new Yoguini($validated);
        $Yoguini->password = Hash::make($validated['password']);
        $Yoguini->perfil = 'user';
        $Yoguini->save();

        return response()->json($Yoguini->makeHidden(['password']), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return Yoguini::findOrFail($id)->makeHidden(['password']);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return Yoguini::findOrFail($id)->makeHidden(['password']);
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
        $Yoguini = Yoguini::findOrFail($id);
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'numero' => 'required|integer|min:0',
            'telefono' => 'required|string|max:255',
            'fechaNacimiento' => 'required|date',
            'email' => ['required', 'email', 'max:255', Rule::unique('yoguinis', 'email')->ignore($Yoguini->id)],
        ]);

        $Yoguini->fill($validated);
        $Yoguini->save();

        return response()->json($Yoguini->makeHidden(['password']));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $Yoguini = Yoguini::findOrFail($id);

        if ($Yoguini->inscripciones()->exists() || $Yoguini->reservas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar esta ficha porque tiene historial de reservas.',
            ], 409);
        }

        $Yoguini->delete();

        return response()->json(['message' => 'Ficha eliminada correctamente.']);
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
