<?php

namespace Tests\Feature;

use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Horario;
use App\Models\Inscripcion;
use App\Models\Yoguini;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingCapacityConcurrencyTest extends TestCase
{
    private $processes = [];
    private $temporaryFiles = [];
    private $cycleId;
    private $scheduleId;
    private $classId;
    private $userIds = [];
    private $enrollmentIds = [];
    private $reservationIds = [];
    private $creditIds = [];
    private $inscripcionHorarioIds = [];
    private $countsBefore = [];
    private $marker;
    private $targetDatabaseActive = false;
    private $processCount = 0;
    private $confirmedReservations = 0;
    private $rejectedEnrollments = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RUN_BOOKING_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_BOOKING_CONCURRENCY_TESTS=1 to run the MySQL concurrency test.');
        }
        if (!function_exists('proc_open') || !extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('This test requires proc_open and pdo_mysql.');
        }

        $dotenvPath = base_path('.env');
        if (!is_file($dotenvPath)) {
            $this->markTestSkipped('The project .env file is required for the concurrency test guard.');
        }
        $dotenv = \Dotenv\Dotenv::parse(file_get_contents($dotenvPath));
        if (($dotenv['APP_ENV'] ?? null) !== 'local') {
            $this->markTestSkipped('The concurrency test only runs when APP_ENV in .env is exactly local.');
        }
        if (($dotenv['DB_CONNECTION'] ?? null) !== 'mysql') {
            $this->markTestSkipped('The concurrency test requires DB_CONNECTION=mysql in .env.');
        }

        $database = getenv('BOOKING_CONCURRENCY_DB_DATABASE');
        $configuredDatabase = $dotenv['DB_DATABASE'] ?? null;
        if (!$database || !$configuredDatabase || $database !== $configuredDatabase) {
            $this->markTestSkipped('BOOKING_CONCURRENCY_DB_DATABASE must exactly match DB_DATABASE in .env.');
        }

        $connection = config('database.connections.mysql');
        $connection['database'] = $database;
        $connection['url'] = null;

        config([
            'database.connections.booking_concurrency' => $connection,
            'database.default' => 'booking_concurrency',
            'app.env' => 'local',
            'app.timezone' => $dotenv['APP_TIMEZONE'] ?? 'America/Argentina/Buenos_Aires',
        ]);
        DB::purge('booking_concurrency');
        $this->assertSame($database, DB::connection('booking_concurrency')->getDatabaseName());

        foreach ($this->countedTables() as $table) {
            if (!Schema::connection('booking_concurrency')->hasTable($table)) {
                $this->markTestSkipped("The already-migrated concurrency database is missing {$table}.");
            }
        }
        $this->targetDatabaseActive = true;
        $this->countsBefore = $this->tableCounts();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->targetDatabaseActive) {
                $this->closeWorkers();
                $this->rollbackOpenTransaction();
                $this->captureFixtureIds();
                $this->cleanFixtures();
                $after = $this->tableCounts();
                $markerRows = $this->markedFixtureRowCount();
                fwrite(STDOUT, "\nCONC_TEST cleanup marker_rows={$markerRows} before=".json_encode($this->countsBefore)." after=".json_encode($after)."\n");
                $this->assertSame(0, $markerRows, 'Marked concurrency fixtures remain in the database.');
                $this->assertSame($this->countsBefore, $after, 'Affected table row counts changed after concurrency fixture cleanup.');
                DB::purge('booking_concurrency');
            }
            foreach ($this->temporaryFiles as $path) {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_only_one_student_can_claim_the_last_class_seat_concurrently()
    {
        $this->marker = 'CONC_TEST_'.strtoupper(bin2hex(random_bytes(6)));
        $date = Carbon::now(config('app.timezone'))->addDays(7)->startOfDay();
        while (DB::table('feriados')->whereDate('fecha', $date->toDateString())->exists()) {
            $date->addDay();
        }

        $schedule = Horario::create([
            'dia_semana' => $date->dayOfWeek,
            'hora_inicio' => '12:00:00',
            'duracion_min' => 60,
            'nivel' => $this->marker,
            'profesor' => $this->marker,
            'cupo' => 1,
            'activo' => true,
        ]);
        $this->scheduleId = $schedule->id;
        $cycle = Ciclo::create([
            'nombre' => $this->marker,
            'fecha_inicio' => $date->toDateString(),
            'fecha_fin' => $date->toDateString(),
            'clases_por_semana' => 1,
            'precio' => '0.00',
            'activo' => true,
        ]);
        $this->cycleId = $cycle->id;
        $cycle->horarios()->sync([$schedule->id]);
        $class = Clase::create([
            'ciclo_id' => $cycle->id,
            'horario_id' => $schedule->id,
            'fecha' => $date->toDateString(),
            'hora_inicio' => '12:00:00',
            'duracion_min' => 60,
            'nivel' => $this->marker,
            'profesor' => $this->marker,
            'cupo' => 1,
            'estado' => 'programada',
        ]);
        $this->classId = $class->id;
        $firstStudent = Yoguini::factory()->create([
            'nombre' => $this->marker.'_ONE',
            'apellido' => $this->marker,
            'email' => strtolower($this->marker).'_one@concurrency-test.invalid',
        ]);
        $this->userIds[] = $firstStudent->id;
        $secondStudent = Yoguini::factory()->create([
            'nombre' => $this->marker.'_TWO',
            'apellido' => $this->marker,
            'email' => strtolower($this->marker).'_two@concurrency-test.invalid',
        ]);
        $this->userIds[] = $secondStudent->id;
        $students = [$firstStudent, $secondStudent];

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'booking-capacity-'.uniqid('', true);
        if (!mkdir($directory, 0700, true)) {
            $this->fail('Could not create temporary synchronization directory.');
        }
        $gatePath = $directory.DIRECTORY_SEPARATOR.'start';
        $this->temporaryFiles[] = $gatePath;

        DB::connection('booking_concurrency')->beginTransaction();
        DB::connection('booking_concurrency')->table('ciclos')->where('id', $cycle->id)->lockForUpdate()->first();

        foreach ($students as $student) {
            $readyPath = $directory.DIRECTORY_SEPARATOR.'ready-'.$student->id;
            $attemptPath = $directory.DIRECTORY_SEPARATOR.'attempt-'.$student->id;
            $this->temporaryFiles[] = $readyPath;
            $this->temporaryFiles[] = $attemptPath;
            $this->startWorker($student->id, $cycle->id, $schedule->id, $readyPath, $gatePath, $attemptPath);
        }

        $this->waitForFiles(array_map(function ($student) use ($directory) {
            return $directory.DIRECTORY_SEPARATOR.'ready-'.$student->id;
        }, $students));
        file_put_contents($gatePath, 'start');
        $this->waitForFiles(array_map(function ($student) use ($directory) {
            return $directory.DIRECTORY_SEPARATOR.'attempt-'.$student->id;
        }, $students));
        DB::connection('booking_concurrency')->commit();

        $outcomes = [];
        foreach ($this->processes as $worker) {
            $stdout = trim(stream_get_contents($worker['pipes'][1]));
            $stderr = trim(stream_get_contents($worker['pipes'][2]));
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exitCode = proc_close($worker['process']);
            $this->assertSame(0, $exitCode, $stderr);
            $outcomes[] = $stdout;
        }
        $this->processes = [];

        sort($outcomes);
        $this->processCount = count($outcomes);
        $this->confirmedReservations = count(array_filter($outcomes, function ($outcome) {
            return $outcome === 'reserved';
        }));
        $this->rejectedEnrollments = count(array_filter($outcomes, function ($outcome) {
            return $outcome === 'full';
        }));
        $this->assertSame(['full', 'reserved'], $outcomes);
        $this->assertSame(1, DB::table('reservas')->where('clase_id', $class->id)->count());
        $this->captureFixtureIds();
        DB::table('inscripciones')->whereIn('id', $this->enrollmentIds)->update(['estado' => $this->marker.'_ACTIVE']);
        DB::table('reservas')->whereIn('id', $this->reservationIds)->update(['tipo' => $this->marker.'_REGULAR']);
        fwrite(STDOUT, "\nCONC_TEST result processes={$this->processCount} confirmed={$this->confirmedReservations} rejected={$this->rejectedEnrollments} reservations=1\n");
    }

    private function startWorker($userId, $cycleId, $scheduleId, $readyPath, $gatePath, $attemptPath)
    {
        $script = base_path('tests/Support/booking-enrollment-worker.php');
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = proc_open([
            PHP_BINARY,
            $script,
            (string) $userId,
            (string) $cycleId,
            (string) $scheduleId,
            $readyPath,
            $gatePath,
            $attemptPath,
        ], $descriptors, $pipes, base_path());

        if (!is_resource($process)) {
            $this->fail('Could not start an enrollment worker process.');
        }

        fclose($pipes[0]);
        $this->processes[] = ['process' => $process, 'pipes' => $pipes];
    }

    private function countedTables()
    {
        return [
            'yoguinis',
            'horarios',
            'ciclos',
            'ciclo_horarios',
            'clases',
            'inscripciones',
            'inscripcion_horarios',
            'reservas',
            'recuperaciones',
        ];
    }

    private function tableCounts()
    {
        $counts = [];
        foreach ($this->countedTables() as $table) {
            $counts[$table] = (int) DB::connection('booking_concurrency')->table($table)->count();
        }
        return $counts;
    }

    private function captureFixtureIds()
    {
        if (!$this->cycleId || !$this->userIds) {
            return;
        }

        $connection = DB::connection('booking_concurrency');
        $this->enrollmentIds = $connection->table('inscripciones')
            ->where('ciclo_id', $this->cycleId)
            ->whereIn('user_id', $this->userIds)
            ->pluck('id')
            ->map(function ($id) { return (int) $id; })
            ->all();
        $this->reservationIds = $connection->table('reservas')
            ->where('clase_id', $this->classId)
            ->whereIn('user_id', $this->userIds)
            ->pluck('id')
            ->map(function ($id) { return (int) $id; })
            ->all();
        $this->creditIds = $connection->table('recuperaciones')
            ->whereIn('user_id', $this->userIds)
            ->pluck('id')
            ->map(function ($id) { return (int) $id; })
            ->all();
        $this->inscripcionHorarioIds = $connection->table('inscripcion_horarios')
            ->whereIn('inscripcion_id', $this->enrollmentIds)
            ->pluck('id')
            ->map(function ($id) { return (int) $id; })
            ->all();
    }

    private function markedFixtureRowCount()
    {
        $connection = DB::connection('booking_concurrency');
        $marker = $this->marker;
        $counts = [
            $connection->table('yoguinis')->where('nombre', 'like', $marker.'%')->orWhere('email', 'like', strtolower($marker).'%')->count(),
            $connection->table('horarios')->where('nivel', 'like', $marker.'%')->orWhere('profesor', 'like', $marker.'%')->count(),
            $connection->table('ciclos')->where('nombre', 'like', $marker.'%')->count(),
            $connection->table('clases')->where('nivel', 'like', $marker.'%')->orWhere('profesor', 'like', $marker.'%')->count(),
            $connection->table('inscripciones')->whereIn('user_id', $this->userIds)->orWhere('estado', 'like', $marker.'%')->count(),
            $connection->table('reservas')->whereIn('user_id', $this->userIds)->orWhere('tipo', 'like', $marker.'%')->count(),
            $connection->table('recuperaciones')->whereIn('user_id', $this->userIds)->count(),
            $connection->table('ciclo_horarios')->where('ciclo_id', $this->cycleId)->count(),
            $connection->table('inscripcion_horarios')->whereIn('inscripcion_id', $this->enrollmentIds)->count(),
        ];

        return array_sum($counts);
    }

    private function waitForFiles(array $paths)
    {
        $deadline = microtime(true) + 20;
        do {
            if (count(array_filter($paths, 'file_exists')) === count($paths)) {
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);

        $this->fail('Timed out while synchronizing concurrent enrollment workers.');
    }

    private function rollbackOpenTransaction()
    {
        if (DB::connection('booking_concurrency')->transactionLevel() > 0) {
            DB::connection('booking_concurrency')->rollBack();
        }
    }

    private function closeWorkers()
    {
        foreach ($this->processes as $worker) {
            if (is_resource($worker['process'])) {
                proc_terminate($worker['process']);
                foreach (array_slice($worker['pipes'], 1) as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($worker['process']);
            }
        }
        $this->processes = [];
    }

    private function cleanFixtures()
    {
        $this->captureFixtureIds();
        $connection = DB::connection('booking_concurrency');

        if ($this->creditIds) {
            $connection->table('recuperaciones')->whereIn('id', $this->creditIds)->delete();
        }
        if ($this->reservationIds) {
            $connection->table('reservas')->whereIn('id', $this->reservationIds)->delete();
        }
        if ($this->inscripcionHorarioIds) {
            $connection->table('inscripcion_horarios')->whereIn('id', $this->inscripcionHorarioIds)->delete();
        }
        if ($this->enrollmentIds) {
            $connection->table('inscripciones')->whereIn('id', $this->enrollmentIds)->delete();
        }
        if ($this->classId) {
            $connection->table('clases')->where('id', $this->classId)->delete();
        }
        if ($this->cycleId) {
            $connection->table('ciclo_horarios')
                ->where('ciclo_id', $this->cycleId)
                ->where('horario_id', $this->scheduleId)
                ->delete();
            $connection->table('ciclos')->where('id', $this->cycleId)->delete();
        }
        if ($this->scheduleId) {
            $connection->table('horarios')->where('id', $this->scheduleId)->delete();
        }
        if ($this->userIds) {
            $connection->table('yoguinis')->whereIn('id', $this->userIds)->delete();
        }

        $directories = array_unique(array_map('dirname', $this->temporaryFiles));
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                @rmdir($directory);
            }
        }
    }
}