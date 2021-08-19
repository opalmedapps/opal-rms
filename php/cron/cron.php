<?php declare(strict_types = 1);

require_once __DIR__."/../../vendor/autoload.php";

use Crunz\Schedule;
use Orms\Config;

//normally, the config file is loaded once and used throughout the request lifespan
//in this case, some crons will stay alive indefinitely and need the current state of the config file
$reloadConfigs = function(): Config
{
    Config::__init();
    return Config::getApplicationSettings();
};

$configs = Config::getApplicationSettings();
$schedule = new Schedule();

$currentAppointmentsTask = $schedule->run(function() use ($configs,$reloadConfigs) {
    while($configs->system->vwrAppointmentCronEnabled === true)
    {
        $configs = $reloadConfigs();
        /** @psalm-suppress ForbiddenCode */
        `php ../php/cron/generateVwrAppointments.php`;
        sleep(3);
    }
})
->when(fn() => $configs->system->vwrAppointmentCronEnabled === true)
->everyMinute()
->preventOverlapping()
->timezone(date_default_timezone_get())
->description("Virtual waiting room appointment file generator");

$incomingSmsTask = $schedule->run(function() use ($configs,$reloadConfigs) {
    while($configs->system->processIncomingSmsCronEnabled === true)
    {
        $configs = $reloadConfigs();
        /** @psalm-suppress ForbiddenCode */
        `php ../php/cron/processIncomingSmsMessages.php`;
        sleep(5);
    }
})
->when(fn() => $configs->system->processIncomingSmsCronEnabled === true)
->everyMinute()
->preventOverlapping()
->timezone(date_default_timezone_get())
->description("Incoming sms processor");

$appointmentReminderTask = $schedule->run("php ../php/cron/generateAppointmentReminders.php")
->when(fn() => $configs->system->appointmentReminderCronEnabled === true)
->daily()
->hour(18)
->preventOverlapping()
->timezone(date_default_timezone_get())
->description("Appointment sms reminder");


return $schedule;
