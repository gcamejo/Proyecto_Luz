<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Pago;
use App\Models\PagoAplicacion;
use App\Models\Yoguini;
use App\Services\PagoService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentConcurrencyTest extends TestCase
{
    private $processes = [];
    private $temporaryFiles = [];
    private $userId;
    private $chargeIds = [];
    private $paymentIds = [];
    private $applicationIds = [];
    private $countsBefore = [];
    private $marker;
    private $targetDatabaseActive = false;

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
                $this->cleanFixtures();
                $after = $this->tableCounts();
                $markerRows = $this->markedFixtureRowCount();
                fwrite(STDOUT, "\nPAY_CONC_TEST cleanup marker_rows={$markerRows} before=".json_encode($this->countsBefore)." after=".json_encode($after)."\n");
                $this->assertSame(0, $markerRows, 'Marked payment fixtures remain in the database.');
                $this->assertSame($this->countsBefore, $after, 'Affected table row counts changed after payment fixture cleanup.');
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

    public function test_two_simultaneous_credit_applications_for_one_student_apply_the_credit_only_once()
    {
        $this->marker = 'PAY_CONC_TEST_'.strtoupper(bin2hex(random_bytes(6)));
        $student = Yoguini::factory()->create([
            'nombre' => $this->marker,
            'apellido' => $this->marker,
            'email' => strtolower($this->marker).'@concurrency-test.invalid',
        ]);
        $this->userId = $student->id;
        $charge = Cargo::create([
            'user_id' => $student->id,
            'periodo' => Carbon::now(config('app.timezone'))->startOfMonth()->toDateString(),
            'concepto' => $this->marker,
            'monto_original' => '100.00',
            'descuento' => '0.00',
            'monto_final' => '100.00',
            'estado' => Cargo::PENDIENTE,
        ]);
        $payment = Pago::create([
            'user_id' => $student->id,
            'monto' => '100.00',
            'fecha_pago' => Carbon::now(config('app.timezone'))->toDateString(),
            'metodo' => 'efectivo',
            'nota' => $this->marker,
            'registrado_por' => $student->id,
            'estado' => Pago::VIGENTE,
        ]);

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'payment-credit-'.uniqid('', true);
        if (!mkdir($directory, 0700, true)) {
            $this->fail('Could not create temporary synchronization directory.');
        }
        $gatePath = $directory.DIRECTORY_SEPARATOR.'start';
        $this->temporaryFiles[] = $gatePath;

        // Se retiene el mutex del alumno hasta que ambos workers estén listos para competir.
        DB::connection('booking_concurrency')->beginTransaction();
        DB::connection('booking_concurrency')->table('yoguinis')->where('id', $student->id)->lockForUpdate()->first();

        $workers = ['one', 'two'];
        foreach ($workers as $worker) {
            $readyPath = $directory.DIRECTORY_SEPARATOR.'ready-'.$worker;
            $attemptPath = $directory.DIRECTORY_SEPARATOR.'attempt-'.$worker;
            $this->temporaryFiles[] = $readyPath;
            $this->temporaryFiles[] = $attemptPath;
            $this->startWorker($charge->id, $student->id, $readyPath, $gatePath, $attemptPath);
        }

        $this->waitForFiles(array_map(function ($worker) use ($directory) {
            return $directory.DIRECTORY_SEPARATOR.'ready-'.$worker;
        }, $workers));
        file_put_contents($gatePath, 'start');
        $this->waitForFiles(array_map(function ($worker) use ($directory) {
            return $directory.DIRECTORY_SEPARATOR.'attempt-'.$worker;
        }, $workers));
        DB::connection('booking_concurrency')->commit();

        $outcomes = [];
        foreach ($this->processes as $worker) {
            $stdout = trim(stream_get_contents($worker['pipes'][1]));
            $stderr = trim(stream_get_contents($worker['pipes'][2]));
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $this->assertSame(0, proc_close($worker['process']), $stderr);
            $outcomes[] = $stdout;
        }
        $this->processes = [];

        sort($outcomes);
        $this->assertSame(['applied', 'rejected'], $outcomes);
        $this->assertSame(1, PagoAplicacion::where('pago_id', $payment->id)->where('estado', PagoAplicacion::VIGENTE)->count());
        $this->assertSame('100.00', Money::decimal(Money::sum(PagoAplicacion::where('pago_id', $payment->id)->pluck('monto'))));
        $this->assertSame(Cargo::PAGADO, $charge->fresh()->estado);
        $this->assertSame(0, app(PagoService::class)->creditCents($student->id));
        $this->captureFixtureIds();
        fwrite(STDOUT, "\nPAY_CONC_TEST result processes=2 applied=1 rejected=1 applications=".count($this->applicationIds)."\n");
    }

    private function startWorker($chargeId, $userId, $readyPath, $gatePath, $attemptPath)
    {
        $process = proc_open([
            PHP_BINARY,
            base_path('tests/Support/payment-credit-worker.php'),
            (string) $chargeId,
            (string) $userId,
            $readyPath,
            $gatePath,
            $attemptPath,
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());

        if (!is_resource($process)) {
            $this->fail('Could not start a payment worker process.');
        }

        fclose($pipes[0]);
        $this->processes[] = ['process' => $process, 'pipes' => $pipes];
    }

    private function countedTables()
    {
        return ['yoguinis', 'cargos', 'pagos', 'pago_aplicaciones'];
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
        if (!$this->userId) {
            return;
        }

        $connection = DB::connection('booking_concurrency');
        $this->chargeIds = $connection->table('cargos')->where('user_id', $this->userId)->pluck('id')->map(function ($id) { return (int) $id; })->all();
        $this->paymentIds = $connection->table('pagos')->where('user_id', $this->userId)->pluck('id')->map(function ($id) { return (int) $id; })->all();
        $this->applicationIds = $connection->table('pago_aplicaciones')->whereIn('pago_id', $this->paymentIds)->pluck('id')->map(function ($id) { return (int) $id; })->all();
    }

    private function markedFixtureRowCount()
    {
        $connection = DB::connection('booking_concurrency');
        $marker = $this->marker;

        return $connection->table('yoguinis')->where('nombre', 'like', $marker.'%')->orWhere('email', 'like', strtolower($marker).'%')->count()
            + $connection->table('cargos')->where('concepto', 'like', $marker.'%')->count()
            + $connection->table('pagos')->where('nota', 'like', $marker.'%')->count()
            + $connection->table('pago_aplicaciones')->whereIn('pago_id', $this->paymentIds ?: [0])->count();
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

        $this->fail('Timed out while synchronizing concurrent payment workers.');
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

        if ($this->applicationIds) {
            $connection->table('pago_aplicaciones')->whereIn('id', $this->applicationIds)->delete();
        }
        if ($this->paymentIds) {
            $connection->table('pagos')->whereIn('id', $this->paymentIds)->delete();
        }
        if ($this->chargeIds) {
            $connection->table('cargos')->whereIn('id', $this->chargeIds)->delete();
        }
        if ($this->userId) {
            $connection->table('yoguinis')->where('id', $this->userId)->delete();
        }

        foreach (array_unique(array_map('dirname', $this->temporaryFiles)) as $directory) {
            if (is_dir($directory)) {
                @rmdir($directory);
            }
        }
    }
}
