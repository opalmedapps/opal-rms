<?php declare(strict_types = 1);

require_once __DIR__."/../../vendor/autoload.php";

use Crunz\Schedule;
use Orms\Config;

$configs = Config::getApplicationSettings();
$schedule = new Schedule();

$currentAppointmentsTask = $schedule->run(function() use ($configs) {

    $x = $configs->system->vwrAppointmentCronEnabled;
    // file_put_contents("aaa.txt",var_dump($configs->system->vwrAppointmentCronEnabled,true),FILE_APPEND);

    // while($configs->system->vwrAppointmentCronEnabled === true) {
    //     file_put_contents("aaa.txt","aaa: " .$configs->system->vwrAppointmentCronEnabled."\n",FILE_APPEND);
    //     sleep(5);
    // }
})
->when(fn() => $configs->system->vwrAppointmentCronEnabled === true)
->everyMinute()
->preventOverlapping()
->timezone(date_default_timezone_get())
->description("Virtual waiting room appointment file generator");


return $schedule;
