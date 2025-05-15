<?php

declare(strict_types=1);

require __DIR__."/../../vendor/autoload.php";

use Orms\Appointment\AppointmentInterface;
use Orms\DateTime;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;
use Orms\Util\ArrayUtil;
use Orms\Util\Encoding;

$messageList = SmsInterface::getPossibleSmsMessages();

//get the list of appointments to process
//group appointments by patient
$appointments = AppointmentInterface::getOpenAppointments((new DateTime())->modify("tomorrow"),(new DateTime())->modify("tomorrow")->modify("tomorrow"));

//filter all appointments where sms is not enabled and the reminder has already been sent
$appointments = array_values(array_filter($appointments,fn($x) =>
    $x["smsActive"] === true
    && $x["smsType"] !== null
    && $x["smsReminderSent"] === false
));

$appointments = array_map(function($x) {
    $x["patient"] = PatientInterface::getPatientById($x["patientId"]) ?? throw new Exception("Unknown patient");
    return $x;
},$appointments);

//filter all appointments where the patient doesn't have a phone number
$appointments = array_values(array_filter($appointments,fn($x) => $x["patient"]->phoneNumber !== null));

//filter all appointments where the ScheduledTime is between midnight and 2AM
$appointments = array_values(array_filter($appointments, function($x){
    $start = new DateTime($x["scheduledDatetime"]->format('Y-m-d') . ' 00:00:00');
    $end = new DateTime($x["scheduledDatetime"]->format('Y-m-d') . ' 02:00:00');
    return $x["scheduledDatetime"] <= $start || $x["scheduledDatetime"] > $end;
}));
$appointments = Encoding::utf8_encode_recursive($appointments);

$patients = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($appointments, "patientId");

//merge appointment entries for the same patient
//combine all appointments a patient has into a string with the appropriate messages(s)
$patients = array_map(function(array $appts) use ($messageList) {
    $groupedAppointments = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($appts, "specialityGroupId", "smsType");

    $reminderString = "";
    foreach($groupedAppointments as $speciality => $v)
    {
        foreach($v as $type => $arr)
        {
            $reminderString .= $messageList[$speciality][$type]["REMINDER"][$arr[0]["patient"]->languagePreference]["message"];

            $appListString = array_reduce($arr, function($acc, $y) {
                $time = $y["scheduledDatetime"]->format("H:i");
                $date = $y["scheduledDatetime"]->format("Y-m-d");

                $name = $y["clinicDescription"];
                //hardcoded aria appointment name conversion
                if($y["sourceSystem"] === "Aria") {
                    if(preg_match("/^.EB/",$y["appointmentCode"]) === 1) {
                        $name = "Radiotherapy";
                    }
                    elseif(preg_match("/^(Consult|CONSULT)/",$y["appointmentCode"]) === 1) {
                        $name = "Consult";
                    }
                    elseif(preg_match("/^FOLLOW UP /",$y["appointmentCode"]) === 1) {
                        $name = "Follow Up";
                    }
                    else {
                        $name = $y["appointmentCode"];
                    }
                }

                return match($y["patient"]->languagePreference) {
                    "French" => $acc ."$name le $date à $time\n",
                    default  => $acc ."$name on $date at $time\n"
                };
            }, "");

            $reminderString = preg_replace("/<app>/", $appListString, $reminderString);
            $reminderString .= "\n\n----------------\n\n";
        }
    }
    $reminderString = preg_replace("/\n\n----------------\n\n$/", "", $reminderString); //remove last newline

    return [
        "patient"           => $appts[0]["patient"],
        "reminderMessage"   => $reminderString,
        "appointmentIds"    => array_column($appts,"appointmentId")
    ];
}, $patients);

//send sms to patient
//also mark each appointment as having a reminder sent already
foreach($patients as $pat)
{
    if($pat["reminderMessage"] !== "" && $pat["patient"]->phoneNumber !== null) {
        /** @phpstan-ignore-next-line */ //phpstan can't detect that phoneNumber can't be null...
        SmsInterface::sendSms($pat["patient"]->phoneNumber, $pat["reminderMessage"]);
    }

    foreach($pat["appointmentIds"] as $x) {
        AppointmentInterface::updateAppointmentReminderFlag((int) $x);
    }
}
