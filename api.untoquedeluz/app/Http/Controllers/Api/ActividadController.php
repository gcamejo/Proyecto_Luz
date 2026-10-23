<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Actividade;
use Illuminate\Support\Facades\Storage;

class ActividadController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $Actividades = Actividade::all();
        
        return $Actividades;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'urlImagen' => 'nullable|string|max:30',
            'titulo' => 'nullable|string|max:30',
            'descripcion' => 'nullable|string|max:500',
            'horarios' => 'nullable|string|max:30',
        ]);

        $Actividad = new Actividade;
        $Actividad->urlImagen = $validated['urlImagen'] ?? 'logo_principal.jpg';
        $Actividad->titulo = $validated['titulo'] ?? null;
        $Actividad->descripcion = $validated['descripcion'] ?? null;
        $Actividad->horarios = $validated['horarios'] ?? null;
        
        $Actividad->save();

    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeFile(Request $request)
    {
        
        if($request->file('file'))
        {
        $archivo = $request->file('file');
        $ext = $archivo->getClientOriginalExtension();
        $nombreImagen = 'actividad_'.now()->format('Ymd_His').'.'.$ext;
        $archivo->storeAs('img', $nombreImagen, 'public');
        return $nombreImagen ;
        
        }
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
        $Actividad = Actividade::find($id);
        return $Actividad;
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
        $Actividad = Actividade::findOrFail($id);
        $validated = $request->validate([
            'urlImagen' => 'nullable|string|max:30',
            'titulo' => 'nullable|string|max:30',
            'descripcion' => 'nullable|string|max:500',
            'horarios' => 'nullable|string|max:30',
        ]);

        $Actividad->fill($validated);
        $Actividad->save();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $Actividad = Actividade::find($id);

        $nombreImagen = $Actividad->urlImagen;        
        $path = 'img/'.$nombreImagen;
        $mensaje = 'No se elimino archivo';

        if($path != 'img/logo_principal.jpg')
        {
            if(Storage::disk('public')->exists($path))
            {                
                Storage::disk('public')->delete($path);
                $mensaje='Se elimino el archivo '.$path;
            }
        }
        
        $Actividad = Actividade::destroy($id);
        
        return [
                'codigo' => 200,
                'mensaje' => $mensaje
                ];
    }
}
