<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tareas programadas. En cPanel basta UN cron cada minuto:
|   * * * * * /usr/local/bin/php /home/USUARIO/app/artisan schedule:run >> /dev/null 2>&1
|
| Todas son idempotentes: si el cron se ejecuta dos veces o se recupera tras días caído,
| no duplican cobros ni avisos. Los horarios están en hora de Lima.
*/

$timezone = config('app.business_timezone');

// Cada hora: si el servidor estuvo caído, la siguiente ejecución recupera lo pendiente.
Schedule::command('billing:generate-charges')
    ->hourlyAt(5)
    ->timezone($timezone)
    ->withoutOverlapping();

Schedule::command('contracts:complete-finished')
    ->dailyAt('00:20')
    ->timezone($timezone)
    ->withoutOverlapping();

// Después de generar cobros; en horario de trabajo para que el aviso llegue cuando se puede actuar.
Schedule::command('reminders:dispatch')
    ->hourlyAt(15)
    ->between('07:00', '21:00')
    ->timezone($timezone)
    ->withoutOverlapping();
