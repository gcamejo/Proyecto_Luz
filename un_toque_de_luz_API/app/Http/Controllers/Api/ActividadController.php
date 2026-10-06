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
        $nombreImagen = cache('ImagenSubida', 'logo_principal.jpg');
        $Actividad = new Actividade;
        $Actividad->urlImagen = $nombreImagen;
        $Actividad->titulo = $request->titulo;
        $Actividad->descripcion = $request->descripcion;
        $Actividad->horarios = $request->horarios;
        
        $Actividad->save();

        cache()->forget('ImagenSubida');
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
        $path = $archivo->storeAs('public/img', $nombreImagen);
        cache(['ImagenSubida' => $nombreImagen]);
        
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
        $Actividad = Actividade::find($id);
        $Actividad->urlImagen = $request->urlImagen;
        $Actividad->titulo = $request->titulo;
        $Actividad->descripcion = $request->descripcion;
        $Actividad->horarios = $request->horarios;

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
        $path = 'public/img/'.$nombreImagen;
        $mensaje = 'No se elimino archivo';

        if($path != 'public/img/logo_principal.jpg')
        {
            if(Storage::exists($path))
            {                
                Storage::delete($path);
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
