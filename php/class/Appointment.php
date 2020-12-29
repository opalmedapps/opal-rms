<?php

namespace Orms;

use \Exception;
use Orms\Config;
use Orms\Patient;

class Appointment
{
    #definition of an Appointment
    private Patient $patient;
    private ?string $appointmentCode     = NULL;
    private ?string $creationDate        = NULL;
    private ?string $id                  = NULL;
    private ?string $referringMd         = NULL;
    private ?string $resource            = NULL;
    private ?string $resourceDesc        = NULL;
    private ?string $scheduledDate       = NULL;
    private ?string $scheduledDateTime   = NULL;
    private ?string $scheduledTime       = NULL;
    private ?string $site                = NULL;
    private ?string $sourceStatus        = NULL;
    private ?string $specialityGroup     = NULL;
    private ?string $status              = NULL;
    private ?string $system              = NULL;

    /**
     *
     * @param string[] $appointmentInfo
     * @param Patient|null $patientInfo
     * @return void
     * @throws Exception
     */
    public function __construct(array $appointmentInfo,Patient $patientInfo = NULL)
    {
        foreach(array_keys(get_object_vars($this)) as $field) {
            $this->$field = (!empty($appointmentInfo[$field])) ? $appointmentInfo[$field] : NULL;
        }

        $this->patient = $patientInfo ?? new Patient();

        $this->_sanitizeObject();
    }

    #insert or update an appointment in the ORMS database
    #returns rows inserted
    public function insertOrUpdateAppointmentInDatabase(): bool
    {
        #get the necessary ids that are attached to the appointment
        $clinicSer = $this->_getClinicSerNum("INSERT_IF_NULL");
        $appCodeId = $this->_getAppointmentCodeId("INSERT_IF_NULL");

        $patientSer = $this->_getPatientSer("INSERT_IF_NULL");

        if($patientSer === NULL || $clinicSer === NULL || $appCodeId === NULL) {
            throw new Exception("Missing database ids");
        }

        #check if an sms entry exists for the appointment
        $this->_verifySmsAppointment($clinicSer,$appCodeId);

        //update the patient ssn or ssnExpDate if they have changed
        $this->patient->updateSSNInDatabase();

        #insert row into database
        $dbh = Config::getDatabaseConnection("ORMS");

        $insertAppointment = $dbh->prepare("
            INSERT INTO MediVisitAppointmentList
            (PatientSerNum,Resource,ResourceDescription,ClinicResourcesSerNum,ScheduledDateTime,ScheduledDate,ScheduledTime,AppointmentCode,AppointmentCodeId,AppointId,AppointIdIn,AppointSys,Status,MedivisitStatus,CreationDate,ReferringPhysician,LastUpdatedUserIP)
            VALUES(:patSer,:res,:resDesc,:clinSer,:schDateTime,:schDate,:schTime,:appCode,:appCodeId,:appId,:appIdIn,:appSys,:status,:mvStatus,:creDate,:refPhys,:callIP)
            ON DUPLICATE KEY UPDATE
                PatientSerNum           = VALUES(PatientSerNum),
                Resource                = VALUES(Resource),
                ResourceDescription     = VALUES(ResourceDescription),
                ClinicResourcesSerNum   = VALUES(ClinicResourcesSerNum),
                ScheduledDateTime       = VALUES(ScheduledDateTime),
                ScheduledDate           = VALUES(ScheduledDate),
                ScheduledTime           = VALUES(ScheduledTime),
                AppointmentCode         = VALUES(AppointmentCode),
                AppointmentCodeId       = VALUES(AppointmentCodeId),
                AppointId               = VALUES(AppointId),
                AppointIdIn             = VALUES(AppointIdIn),
                AppointSys              = VALUES(AppointSys),
                Status                  = CASE WHEN Status = 'Completed' THEN 'Completed' ELSE VALUES(Status) END,
                MedivisitStatus         = VALUES(MedivisitStatus),
                CreationDate            = VALUES(CreationDate),
                ReferringPhysician      = VALUES(ReferringPhysician),
                LastUpdatedUserIP       = VALUES(LastUpdatedUserIP)
        ");

        $insertResult = $insertAppointment->execute([
            ":patSer"       => $patientSer,
            ":res"          => $this->resource,
            ":resDesc"      => $this->resourceDesc,
            ":clinSer"      => $clinicSer,
            ":schDateTime"  => $this->scheduledDateTime,
            ":schDate"      => $this->scheduledDate,
            ":schTime"      => $this->scheduledTime,
            ":appCode"      => $this->appointmentCode,
            ":appCodeId"    => $appCodeId,
            ":appId"        => $this->id,
            ":appIdIn"      => $this->id,
            ":appSys"       => $this->system,
            ":status"       => $this->status,
            ":mvStatus"     => $this->sourceStatus,
            ":creDate"      => $this->creationDate,
            ":refPhys"      => $this->referringMd,
            ":callIP"       => empty($_SERVER["REMOTE_ADDR"]) ? gethostname() : $_SERVER["REMOTE_ADDR"]
        ]);

        #rowCount return 1 on insert and 2 on update due to the ON DUPLICATE KEY UPDATE
        #however, if no row was updated (information was identical to whats already there), rowCount returns 0
        #user should just know if insert/update was successful
        #return $insertAppointment->rowCount(); #> 0 ? TRUE : FALSE;
        return $insertResult;
    }

    #deletes all similar appointments in the database
    #similar is defined as having the same PatientSerNum, ScheduledDateTime, Resource, and AppointmentCode
    #returns number of rows deleted
    public function deleteSimilarAppointments(): int
    {
        #get the patient ser num that is attached to the appointment
        $patientSer = $this->_getPatientSer("INSERT_IF_NULL");

        if($patientSer === NULL) {
            throw new Exception("Missing database serial numbers");
        }
        //update the patient ssn or ssnExpDate if they have changed
        // else {
        //     $this->patient->updateSSNInDatabase();
        // }

        $dbh = Config::getDatabaseConnection("ORMS");

        $deleteAppointment = $dbh->prepare("
            UPDATE MediVisitAppointmentList
            SET
                Status = 'Deleted'
            WHERE
                PatientSerNum = :patSer
                AND ScheduledDateTime = :schDateTime
                AND Resource = :res
                AND AppointmentCode = :appCode"
        );

        $deleteAppointment->execute([
            ":patSer" => $patientSer,
            ":schDateTime" => $this->scheduledDateTime,
            ":res" => $this->resource,
            ":appCode" => $this->appointmentCode
        ]);

        return $deleteAppointment->rowCount();

    }

    #returns the serial num of the first resource matching the Appointment's resource description
    #returns NULL if there is no match
    private function _getClinicSerNum(string $mode = "SER_NUM_ONLY"): ?int
    {
        $dbh = Config::getDatabaseConnection("ORMS");

        $queryClinic = $dbh->prepare("
            SELECT
                ClinicResourcesSerNum
            FROM
                ClinicResources
            WHERE
                ResourceName = :desc
                AND ResourceCode = :code
                AND Speciality = :spec
        ");

        $queryClinic->execute([
            ":desc" => $this->resourceDesc,
            ":code" => $this->resource,
            ":spec" => $this->specialityGroup
        ]);

        $clinicSer = $queryClinic->fetchAll()[0]["ClinicResourcesSerNum"] ?? NULL;

        if($clinicSer === NULL && $mode === "INSERT_IF_NULL")
        {
            $dbh->prepare("
                INSERT INTO ClinicResources(ResourceCode,ResourceName,Speciality,SourceSystem)
                VALUES(:resCode,:resName,:spec,:sys)
            ")->execute([
                ":resCode" => $this->resource,
                ":resName" => $this->resourceDesc,
                ":spec"    => $this->specialityGroup,
                ":sys"     => $this->system
            ]);

            $clinicSer = $dbh->lastInsertId();
            if($clinicSer === 0) {
                throw new Exception("Could not insert new resource");
            }
        }

        return $clinicSer;
    }

    private function _getAppointmentCodeId(string $mode = "SER_NUM_ONLY"): ?int
    {
        $dbh = Config::getDatabaseConnection("ORMS");

        $queryAppId = $dbh->prepare("
            SELECT
                AppointmentCodeId
            FROM
                AppointmentCode
            WHERE
                AppointmentCode = :code
                AND Speciality = :spec
        ");

        $queryAppId->execute([
            ":code" => $this->appointmentCode,
            ":spec" => $this->specialityGroup
        ]);

        $appId = $queryAppId->fetchAll()[0]["AppointmentCodeId"] ?? NULL;

        if($appId === NULL && $mode === "INSERT_IF_NULL")
        {
            $dbh->prepare("
                INSERT INTO AppointmentCode(AppointmentCode,Speciality,SourceSystem)
                VALUES(:code,:spec,:sys)
            ")->execute([
                ":code" => $this->appointmentCode,
                ":spec" => $this->specialityGroup,
                ":sys"  => $this->system
            ]);

            $appId = $dbh->lastInsertId();
            if($appId === 0) {
                throw new Exception("Could not insert new resource");
            }
        }

        return $appId;
    }

    private function _verifySmsAppointment(int $clinicSer,int $appointmentCodeId): void
    {
        #add-ons are created from existing appointment types
        #however, there is no restriction on the frontend that the add-on is a valid one
        #so we disable inserting sms appointments for add-ons
        if($this->system === "InstantAddOn") {
            return;
        }

        $dbh = Config::getDatabaseConnection("ORMS");

        $queryAppId = $dbh->prepare("
            SELECT
                SmsAppointmentId
            FROM
                SmsAppointment
            WHERE
                ClinicResourcesSerNum = :clin
                AND AppointmentCodeId = :app
                AND Speciality = :spec
                AND SourceSystem = :sys
        ");

        $queryAppId->execute([
            ":clin" => $clinicSer,
            ":app"  => $appointmentCodeId,
            ":spec" => $this->specialityGroup,
            ":sys"  => $this->system
        ]);

        $appId = $queryAppId->fetchAll()[0]["SmsAppointmentId"] ?? NULL;

        if($appId === NULL)
        {
            $dbh->prepare("
                INSERT INTO SmsAppointment(ClinicResourcesSerNum,AppointmentCodeId,Speciality,SourceSystem)
                VALUES(:clin,:app,:spec,:sys)
            ")->execute([
                ":clin" => $clinicSer,
                ":app"  => $appointmentCodeId,
                ":spec" => $this->specialityGroup,
                ":sys"  => $this->system
            ]);

            $emails = Config::getConfigs("alert")["EMAIL"] ?? [];

            $recepient = implode(",",$emails);
            $subject = "ORMS - New appointment type detected";
            $message = "New appointment type detected: {$this->resourceDesc} ({$this->resource}) with {$this->appointmentCode} in the {$this->specialityGroup} speciality group from system {$this->system}.";
            $headers = [
                "From" => "opal@muhc.mcgill.ca"
            ];

            mail($recepient,$subject,$message,$headers);
        }
    }

    #returns the serial num of the Patient object inside the Appointment
    private function _getPatientSer(string $mode = "SER_NUM_ONLY"): ?int
    {
        $patSer = $this->patient->getPatientSer();

        if($patSer === NULL && $mode === "INSERT_IF_NULL") {
            $patSer = $this->patient->insertPatientInDatabase();
        }

        return $patSer;
    }

    #function to convert appointment fields into an ORMS db compatible form
    private function _sanitizeObject(): void
    {
        #apply some regex
        foreach(array_keys(get_object_vars($this)) as $field)
        {
            if(gettype($this->$field) === 'string')
            {
                $this->$field = str_replace("\\","",$this->$field); #remove backslashes
                #$this->$field = str_replace("'","\'",$this->$field); #escape quotes
                $this->$field = str_replace('"',"",$this->$field); #remove double quotes
                $this->$field = preg_replace("/\n|\r/","",$this->$field); #remove new lines and tabs
                $this->$field = preg_replace("/\s+/"," ",$this->$field ?? ""); #remove multiple spaces
                $this->$field = preg_replace("/^\s/","",$this->$field ?? ""); #remove spaces at the start
                $this->$field = preg_replace("/\s$/","",$this->$field ?? ""); #remove space at the end
            }
        }

        #make sure date and time are in the right format
        if(!preg_match("/\d\d\d\d-\d\d-\d\d/",$this->scheduledDate ?? "")) {
            throw new Exception("Incorrect date format");
        }

        if(!preg_match("/\d\d:\d\d:\d\d/",$this->scheduledTime ?? "")) {
            throw new Exception("Incorrect time format");
        }

        #make sure the site of the appointment matches the site in the config
        $acceptedSite = Config::getConfigs("orms")["SITE"];
        if($acceptedSite !== $this->site) {
            throw new Exception("Site is not supported");
        }

        #make sure the appointment id that we'll use in the orms system is in the correct format
        #3 possibilities: visit (8 digits), appointment: (YYYYA + 8 digits), cancelled appointment: (YYYYC + 7 digits)
        #if the appointment origin is InstantAddOn, any id is valid
        if(!preg_match("/^([0-9]{4}A[0-9]{8}|[0-9]{4}C[0-9]{7}|[0-9]{8})$/",$this->id ?? "") && !preg_match("/InstantAddOn|Aria/",$this->system ?? "")) {
            throw new Exception("Incorrect appointment id format");
        }

        #what about InstantAddOn ?
        #other possible systems are group visits; 999999999G88888888 :  Group Appointment ID  (Appointment sequential#  G  Patient sequential number )  Ex: 20560969G4224207
        #eClinibase; 9999999E : Eclinibase appointment / visit id Ex: 1373791E
        #currently not used
    }

}

?>
