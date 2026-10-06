<?php

use App\Http\Controllers\Api\ActividadController;
use App\Http\Controllers\Api\YoguiniController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [YoguiniController::class , 'login']);
Route::post('/yoguinis',[YoguiniController::class , 'store'] );

Route::get('/actividades',[ActividadController::class,'index']);



//Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    
    //Rutas a tabla yoguinis
    
    Route::controller(YoguiniController::class)->group(function (){
        Route::get('/yoguinis', 'index');
        Route::get('/yoguinis/{id}', 'edit');
        Route::patch('/yoguinis/{id}',  'update');
        Route::delete('/yoguinis/{id}', 'destroy');
    });
    
    //Rutas a tabla actividades
    
    Route::controller(ActividadController::class)->group(function (){
        Route::post('/actividadesImg', 'storeFile');
        Route::post('/actividades', 'create');
        Route::get('/actividades/{id}', 'edit' );
        Route::patch('/actividades/{id}',  'update' );
        Route::delete('/actividades/{id}', 'destroy' );
    });



});

/*
Route::controller(YoguiniController::class)->group(function () {
    Route::get('/yoguinis', 'index');
    Route::post('/yoguinis', 'store');
    Route::get('/yoguinis/{id}', 'edit');
    Route::patch('/yoguinis/{id}', 'update');
    Route::delete('/yoguinis/{id}', 'destroy');
});
*/