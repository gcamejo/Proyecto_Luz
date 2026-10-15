<?php

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dotenv = Dotenv\Dotenv::parse(file_get_contents($basePath.'/.env'));
$database = getenv('BOOKING_CONCURRENCY_DB_DATABASE');
$connection = config('database.connections.mysql');
if (($dotenv['APP_ENV'] ?? null) !== 'local'
    || ($dotenv['DB_CONNECTION'] ?? null) !== 'mysql'
    || ($dotenv['DB_DATABASE'] ?? null) !== $database
    || app()->environment() !== 'local'
    || ($connection['database'] ?? null) !== $database) {
    fwrite(STDERR, 'Worker refused a non-local or mismatched database target.');
    exit(2);
}

$connection['url'] = null;

config([
    'database.connections.booking_concurrency' => $connection,
    'database.default' => 'booking_concurrency',
]);
Illuminate\Support\Facades\DB::purge('booking_concurrency');

$readyPath = $argv[4];
$gatePath = $argv[5];
$attemptPath = $argv[6];
file_put_contents($readyPath, 'ready');

$deadline = microtime(true) + 20;
while (!file_exists($gatePath) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!file_exists($gatePath)) {
    fwrite(STDERR, 'Worker start gate timed out.');
    exit(1);
}

file_put_contents($attemptPath, 'attempting');

try {
    app(App\Services\InscripcionService::class)->enroll(
        App\Models\Yoguini::findOrFail((int) $argv[1]),
        App\Models\Ciclo::findOrFail((int) $argv[2]),
        [(int) $argv[3]]
    );
    fwrite(STDOUT, 'reserved');
    exit(0);
} catch (Illuminate\Validation\ValidationException $exception) {
    fwrite(STDOUT, 'full');
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}