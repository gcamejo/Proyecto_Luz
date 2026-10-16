<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeriadosArgentinaSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = now(config('app.timezone'))->year;

        foreach ([$currentYear, $currentYear + 1] as $year) {
            foreach ($this->holidaysFor($year) as $date => $description) {
                DB::table('feriados')->updateOrInsert(
                    ['fecha' => $date],
                    ['descripcion' => $description]
                );
            }
        }
    }

    private function holidaysFor(int $year): array
    {
        $holidays = [];
        $add = static function (CarbonImmutable $date, string $description) use (&$holidays): void {
            $key = $date->toDateString();
            $holidays[$key] = isset($holidays[$key])
                ? $holidays[$key].' / '.$description
                : $description;
        };

        foreach ([
            ['01-01', 'Año Nuevo'],
            ['03-24', 'Día Nacional de la Memoria por la Verdad y la Justicia'],
            ['04-02', 'Día del Veterano y de los Caídos en la Guerra de Malvinas'],
            ['05-01', 'Día del Trabajador'],
            ['05-25', 'Día de la Revolución de Mayo'],
            ['06-20', 'Paso a la Inmortalidad del General Manuel Belgrano'],
            ['07-09', 'Día de la Independencia'],
            ['12-08', 'Inmaculada Concepción de María'],
            ['12-25', 'Navidad'],
        ] as [$monthDay, $description]) {
            $add(CarbonImmutable::createFromFormat('!Y-m-d', $year.'-'.$monthDay, 'UTC'), $description);
        }

        $easter = $this->easterDate($year);

        $add($easter->subDays(48), 'Carnaval');
        $add($easter->subDays(47), 'Carnaval');
        $add($easter->subDays(2), 'Viernes Santo');

        foreach ([
            ['06-17', 'Paso a la Inmortalidad del General Martín Miguel de Güemes'],
            ['08-17', 'Paso a la Inmortalidad del General José de San Martín'],
            ['10-12', 'Día del Respeto a la Diversidad Cultural'],
            ['11-20', 'Día de la Soberanía Nacional'],
        ] as [$monthDay, $description]) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $year.'-'.$monthDay, 'UTC');

            if (in_array($date->dayOfWeekIso, [2, 3], true)) {
                $date = $date->startOfWeek();
            } elseif (in_array($date->dayOfWeekIso, [4, 5], true)) {
                $date = $date->next(CarbonImmutable::MONDAY);
            }

            $add($date, $description);
        }

        ksort($holidays);

        return $holidays;
    }

    private function easterDate(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'UTC');
    }
}