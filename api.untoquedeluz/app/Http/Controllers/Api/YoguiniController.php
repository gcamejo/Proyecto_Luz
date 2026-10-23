<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminStoreYoguiniRequest;
use App\Http\Requests\StoreYoguiniRequest;
use Illuminate\Http\Request;
use App\Models\Yoguini;
use App\Services\LegalConsentService;
use App\Services\PasswordChangeNotifier;
use App\Notifications\PrivacyDeletionRequestedNotification;
use App\Models\Cargo;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\PagoAplicacion;
use App\Models\Recuperacion;
use App\Models\Reserva;
use App\Services\RegistrationNotifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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
    public function store(StoreYoguiniRequest $request, LegalConsentService $consents)
    {
        $validated = $request->validated();
        unset($validated['acepta_legal']);

        $Yoguini = DB::transaction(function () use ($validated, $consents) {
            $student = new Yoguini($validated);
            $student->password = Hash::make($validated['password']);
            $student->perfil = 'user';
            $student->save();
            $consents->acceptCurrent($student);

            return $student;
        });

        $registeredAt = now(config('app.timezone'))->toIso8601String();
        app()->terminating(function () use ($Yoguini, $registeredAt) {
            app(RegistrationNotifier::class)->notifyAdmins($Yoguini, $registeredAt);
        });

        return response()->json($Yoguini->makeHidden(['password']), 201);
    }

    public function storeByAdmin(AdminStoreYoguiniRequest $request)
    {
        $validated = $request->validated();
        $student = new Yoguini($validated);
        $student->password = Hash::make($validated['password']);
        $student->perfil = 'user';
        $student->save();

        return response()->json($student->makeHidden(['password']), 201);
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
            'nombre' => 'nullable|string|max:30',
            'apellido' => 'nullable|string|max:30',
            'direccion' => 'nullable|string|max:30',
            'numero' => 'nullable|integer|min:0',
            'telefono' => 'nullable|string|max:30',
            'fechaNacimiento' => 'nullable|date',
            'email' => ['nullable', 'email', 'max:100', Rule::unique('yoguinis', 'email')->ignore($Yoguini->id)],
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

        if ($Yoguini->consentimientos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar esta ficha porque conserva registros de consentimiento legal.',
            ], 409);
        }

        $Yoguini->delete();

        return response()->json(['message' => 'Ficha eliminada correctamente.']);
    }

    public function login(Request $request)

    {
        
        $Yoguini = Yoguini::where('email', $request -> email)->first();
        

        if ($Yoguini && Hash::check($request->password, $Yoguini->password)) {
                        
            $expiresAt = now(config('app.timezone'))
                ->addMinutes(config('sanctum.token_ttl_minutes'));
            $token = $Yoguini->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;
              
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

    public function profile(Request $request)
    {
        return response()->json($request->user()->makeHidden(['password']));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $passwordChanged = $request->filled('password');
        $notificationEmail = $user->email;
        $notificationName = trim($user->nombre.' '.$user->apellido);
        $validated = $request->validate([
            'nombre' => 'nullable|string|max:30',
            'apellido' => 'nullable|string|max:30',
            'direccion' => 'nullable|string|max:30',
            'numero' => 'nullable|integer|min:0',
            'telefono' => 'nullable|string|max:30',
            'fechaNacimiento' => 'nullable|date',
            'email' => ['nullable', 'email', 'max:100', Rule::unique('yoguinis', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8|max:100|confirmed',
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        unset($validated['password']);

        $user->fill($validated);
        $user->save();

        if ($passwordChanged) {
            $changedAt = now(config('app.timezone'))->toIso8601String();
            $idempotencyKey = 'password-change-'.$user->getKey().'-'.hash('sha256', $changedAt);
            app()->terminating(function () use ($notificationEmail, $notificationName, $changedAt, $idempotencyKey) {
                app(PasswordChangeNotifier::class)->send(
                    $notificationEmail,
                    $notificationName,
                    $changedAt,
                    $idempotencyKey
                );
            });
        }

        return response()->json($user->makeHidden(['password']));
    }

    public function exportProfile(Request $request)
    {
        $user = $request->user();
        $charges = Cargo::where('user_id', $user->id)->orderBy('periodo')->get([
            'id', 'inscripcion_id', 'ciclo_id', 'periodo', 'concepto', 'monto_original',
            'descuento', 'motivo_descuento', 'monto_final', 'vence_el', 'estado',
            'anulado_en', 'anulado_por', 'motivo_anulacion', 'created_at', 'updated_at',
        ]);
        $payments = Pago::where('user_id', $user->id)->orderBy('fecha_pago')->get([
            'id', 'monto', 'fecha_pago', 'metodo', 'referencia', 'nota', 'registrado_por',
            'estado', 'anulado_en', 'anulado_por', 'motivo_anulacion', 'created_at', 'updated_at',
        ]);
        $applications = PagoAplicacion::whereIn('pago_id', $payments->pluck('id'))
            ->orderBy('pago_id')->orderBy('id')->get([
                'id', 'pago_id', 'cargo_id', 'monto', 'estado', 'anulada_en',
                'anulada_por', 'motivo_anulacion', 'registrado_por', 'created_at', 'updated_at',
            ]);

        $export = [
            'exported_at' => now(config('app.timezone'))->toIso8601String(),
            'profile' => [
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'direccion' => $user->direccion,
                'numero' => $user->numero,
                'telefono' => $user->telefono,
                'fechaNacimiento' => $user->fechaNacimiento,
                'email' => $user->email,
                'perfil' => $user->perfil,
            ],
            'inscripciones' => Inscripcion::where('user_id', $user->id)->with(['ciclo:id,nombre,fecha_inicio,fecha_fin', 'horarios:id,dia_semana,hora_inicio'])->orderBy('id')->get(),
            'reservas' => Reserva::where('user_id', $user->id)->with(['clase:id,ciclo_id,horario_id,fecha,hora_inicio,estado', 'clase.ciclo:id,nombre'])->orderBy('id')->get(),
            'creditos_recuperacion' => Recuperacion::where('user_id', $user->id)->orderBy('id')->get([
                'id', 'reserva_origen_id', 'inscripcion_id', 'reserva_destino_id', 'vence_en', 'estado', 'created_at', 'updated_at',
            ]),
            'consentimientos' => $user->consentimientos()->orderBy('tipo')->orderBy('aceptado_en')->get([
                'tipo', 'version', 'aceptado_en',
            ]),
            'cargos' => $charges,
            'pagos' => $payments,
            'aplicaciones_de_pago' => $applications,
        ];

        return response()->json($export)
            ->header('Content-Disposition', 'attachment; filename="mis-datos.json"');
    }

    public function requestDeletion(Request $request)
    {
        $student = $request->user();
        $admins = Yoguini::where('perfil', 'Admin')->get();

        Notification::send($admins, new PrivacyDeletionRequestedNotification(
            (int) $student->id,
            trim($student->nombre.' '.$student->apellido),
            (string) $student->email
        ));

        return response()->json(['message' => 'Solicitud enviada a administración. Los datos no se eliminaron automáticamente.'], 202);
    }
}
