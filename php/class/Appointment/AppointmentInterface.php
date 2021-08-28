<?php

declare(strict_types=1);

namespace Orms\Appointment;

use Orms\DataAccess\AppointmentAccess;

class AppointmentInterface
{
    /**
     *
     * @return list<array{
     *  AppointmentCode: string
     * }>
     */
    public static function getAppointmentCodes(int $specialityGroupId): array
    {
        return AppointmentAccess::getAppointmentCodes($specialityGroupId);
    }

}
