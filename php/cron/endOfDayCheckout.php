<?php

declare(strict_types=1);

require __DIR__."/../../vendor/autoload.php";

use Orms\DataAccess\AppointmentAccess;

AppointmentAccess::completeCheckedInAppointments();
