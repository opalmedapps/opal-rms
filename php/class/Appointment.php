<?php

class Appointment
{

    #definition of an Appointment
    private $appointmentSer      = NULL;

    private $patient             = NULL; #Patient object
    private $appointmentCode     = NULL;
    private $creationDate        = NULL;
    private $id                  = NULL;
    private $referringMd         = NULL;
    private $resource            = NULL;
    private $resourceDesc        = NULL;
    private $scheduledDate       = NULL;
    private $scheduledDateTime   = NULL;
    private $scheduledTime       = NULL;
    private $site                = NULL;
    private $sourceStatus        = NULL;
    private $specialityGroup     = NULL;
    private $status              = NULL;
    private $system              = NULL;

    #constructor
    public function __construct(array $appointmentInfo, Patient $patientInfo = NULL)
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
        #get the patient and clinic ser nums that are attached to the appointment
        $clinicSer = $this->_getClinicSerNum("INSERT_IF_NULL");
        $patientSer = $this->_getPatientSer("INSERT_IF_NULL");

        if($patientSer === NULL || $clinicSer === NULL) {
            throw new Exception("Missing database serial numbers");
        }
        //update the patient ssn or ssnExpDate if they have changed
        else {
            $this->patient->updateSSNInDatabase();
        }

        #insert row into database
        $dbh = Config::getDatabaseConnection("ORMS");

        $insertAppointment = $dbh->prepare("
            INSERT INTO MediVisitAppointmentList
            (PatientSerNum,Resource,ResourceDescription,ClinicResourcesSerNum,ScheduledDateTime,ScheduledDate,ScheduledTime,AppointmentCode,AppointId,AppointIdIn,AppointSys,Status,MedivisitStatus,CreationDate,ReferringPhysician,LastUpdatedUserIP)
            VALUES(:patSer,:res,:resDesc,:clinSer,:schDateTime,:schDate,:schTime,:appCode,:appId,:appIdIn,:appSys,:status,:mvStatus,:creDate,:refPhys,:callIP)
            ON DUPLICATE KEY UPDATE
                ScheduledDateTime = VALUES(ScheduledDateTime),
                ScheduledDate     = VALUES(ScheduledDate),
                ScheduledTime     = VALUES(ScheduledTime),
                AppointSys        = VALUES(AppointSys),
                Status            = VALUES(Status),
                MedivisitStatus   = VALUES(MedivisitStatus)");

        $insertResult = $insertAppointment->execute([
            ":patSer"       => $patientSer,
            ":res"          => $this->resource,
            ":resDesc"      => $this->resourceDesc,
            ":clinSer"      => $clinicSer,
            ":schDateTime"  => $this->scheduledDateTime,
            ":schDate"      => $this->scheduledDate,
            ":schTime"      => $this->scheduledTime,
            ":appCode"      => $this->appointmentCode,
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
                AND AppointmentCode = :appCode");

        $deleteAppointment->execute([
            ":patSer" => $patientSer,
            ":schDateTime" => $this->scheduledDateTime,
            ":res" => $this->resource,
            ":appCode" => $this->appointmentCode
        ]);

        return $deleteAppointment->rowCount();

    }

    // private function getAppointmentSer()

    #returns the serial num of the first resource matching the Appointment's resource description
    #returns NULL if there is no match
    private function _getClinicSerNum(string $mode = "SER_NUM_ONLY"): ?int
    {
        $dbh = Config::getDatabaseConnection("ORMS");

        $queryClinic = $dbh->prepare("
            SELECT
                ClinicResources.ClinicResourcesSerNum
            FROM
                ClinicResources
            WHERE
                ClinicResources.ResourceName = :resName");

        $queryClinic->execute([":resName" => $this->resourceDesc]);

        $clinicSer = ($queryClinic->fetch())["ClinicResourcesSerNum"] ?? NULL;

        if($clinicSer === NULL && $mode === "INSERT_IF_NULL") {
            $clinicSer = $this->_insertNewResource();
        }

        return $clinicSer;
    }

    #insert a new resource into the ORMS db
    private function _insertNewResource(): int
    {
        $dbh = Config::getDatabaseConnection("ORMS");

        $queryClinicInsert = $dbh->prepare("
            INSERT INTO ClinicResources(ResourceName,Speciality,ClinicScheduleSerNum)
            VALUES(:resName,:spec,NULL)
        ");

        $queryClinicInsert->execute([
            ":resName" => $this->resourceDesc,
            ":spec" => $this->specialityGroup,
        ]);

        $clinicSer = $dbh->lastInsertId();
        if($clinicSer === 0) {
            throw new Exception("Could not insert new resource");
        }

        return $clinicSer;
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
                $this->$field = preg_replace("/\s+/"," ",$this->$field); #remove multiple spaces
                $this->$field = preg_replace("/\s$/","",$this->$field); #remove space at the end
            }
        }

        #make sure date and time are in the right format
        if(!preg_match("/\d\d\d\d-\d\d-\d\d/",$this->scheduledDate)) {
            throw new Exception("Incorrect date format");
        }

        if(!preg_match("/\d\d:\d\d:\d\d/",$this->scheduledTime)) {
            throw new Exception("Incorrect time format");
        }

        #current supported sites are RVH and MGH
        if(!preg_match("/^(RVH)$/",$this->site)) {
            throw new Exception("Site is not supported");
        }

        #make sure the appointment id that we'll use in the orms system is in the correct format
        #3 possibilities: visit (8 digits), appointment: (YYYYA + 8 digits), cancelled appointment: (YYYYC + 7 digits)
        #if the appointment origin is InstantAddOn, any id is valid
        if(!preg_match("/^([0-9]{4}A[0-9]{8}|[0-9]{4}C[0-9]{7}|[0-9]{8})$/",$this->id) && !preg_match("/InstantAddOn|Aria/",$this->system)) {
            throw new Exception("Incorrect appointment id format");
        }

        #what about InstantAddOn ?
        #other possible systems are group visits; 999999999G88888888 :  Group Appointment ID  (Appointment sequential#  G  Patient sequential number )  Ex: 20560969G4224207
        #eClinibase; 9999999E : Eclinibase appointment / visit id Ex: 1373791E
        #currently not used
    }

}

?>
