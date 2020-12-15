<?php declare(strict_types = 1);

#updates the database with the new structure for clinic and appointment codes and migrates the existing data
require_once __DIR__ ."/../../../vendor/autoload.php";
require_once __DIR__."/functions/ClinicResources.php";
require_once __DIR__."/functions/ClinicAppointments.php";
require_once __DIR__."/functions/SmsAppointment.php";

ClinicResources::regenerateClinicResources();
ClinicAppointments::regenerateClinicApp();
SmsAppointment::createSmsFeatureTable();


?>