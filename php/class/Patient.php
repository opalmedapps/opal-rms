<?php

namespace ORMS;

use Orms\Config;

class Patient
{
    #definition of a Patient
    private $patientSer              = NULL;

    private $firstName               = NULL;
    private $lastName                = NULL;
    private $ssn                     = NULL;
    private $ssnExpDate              = NULL;
    private $patientId               = NULL;
    private $patientId_MGH           = NULL;
    private $smsNum                  = NULL;
    private $opalPatient             = NULL;
    private $languagePreference      = NULL;


    public function __construct(array $args = NULL)
    {
        foreach(array_keys(get_object_vars($this)) as $field) {
            $this->$field = (isset($args[$field])) ? $args[$field] : NULL;
        }

        $this->_sanitizeObject();
    }

    #updates the Patient object with a serial number from the ORMS database
    #if the patientSer is already set, the function simply returns the ser num
    public function getPatientSer(): ?int
    {
        if(empty($this->patientSer)) {
            $this->_completeObject();
        }

        return $this->patientSer;
    }

    //inserts a new patient row in the ORMS database
    //also updates the patientSer property
    public function insertPatientInDatabase(): int
    {
        $dbh = Config::getDatabaseConnection("ORMS");

        $insertPatient = $dbh->prepare("
            INSERT INTO Patient(FirstName,LastName,SSN,SSNExpDate,PatientId)
            VALUES (:fn,:ln,:ssn,:ssnExp,:patId)"
        );

        $insertPatient->execute([
            ":fn"       => $this->firstName,
            ":ln"       => $this->lastName,
            ":ssn"      => $this->ssn,
            ":ssnExp"   => $this->ssnExpDate,
            ":patId"    => $this->patientId,
        ]);

        $this->patientSer = $dbh->lastInsertId();
        if($this->patientSer === 0) {
            throw new Exception("Could not insert new patient");
        }

        return $this->patientSer;
    }

    #updates the patient ssn and/or expiration date in the ORMS database
    public function updateSSNInDatabase(): void
    {
        $dbh = Config::getDatabaseConnection("ORMS");

        if($this->patientSer === NULL) {
            throw new Exception("No patient ser");
        }

        $updateSSN = $dbh->prepare("
            UPDATE Patient
            SET
                Patient.SSN = CASE
                        WHEN Patient.SSN != :ssn1 THEN :ssn2
                        ELSE Patient.SSN
                      END,
                Patient.SSNExpDate = CASE
                        WHEN (Patient.SSN != :ssn3 OR Patient.SSNExpDate < :expDate1) THEN :expDate2
                        ELSE Patient.SSNExpDate
                      END
            WHERE
                Patient.PatientSerNum = :serNum");

        $updateSSN->execute([
            ":ssn1" => $this->ssn,
            ":ssn2" => $this->ssn,
            ":ssn3" => $this->ssn,
            ":expDate1" => $this->ssnExpDate,
            ":expDate2" => $this->ssnExpDate,
            ":serNum" => $this->patientSer
        ]);
    }

    /*
    public function updatePatientInDatabase(): ?int
    {

        return numRowsAffected
    }*/

    #completes the Patient object by getting missing data from the ORMS database
    #uses the ramq and the patient id
    #available modes are 'ONLY_PATIENT_SER' OR 'ALL'
    private function _completeObject(string $mode = "ONLY_PATIENT_SER"): void
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $query = $dbh->prepare("
            SELECT DISTINCT
                Patient.PatientSerNum,
                Patient.LastName,
                Patient.FirstName,
                Patient.SSN,
                Patient.SSNExpDate,
                Patient.PatientId,
                Patient.PatientId_MGH,
                Patient.SMSAlertNum,
                Patient.SMSSignupDate,
                Patient.OpalPatient,
                Patient.LanguagePreference
            FROM
                Patient
            WHERE
                Patient.PatientId = :patId OR Patient.PatientId_MGH = :patIdMGH");
        $query->execute([
            ":patId" => $this->patientId,
            ":patIdMGH" => $this->patientId_MGH
        ]);

        $result = $query->fetch();
        if($result !== FALSE)
        {
            if($mode === "ONLY_PATIENT_SER")
            {
                $this->patientSer = $result["PatientSerNum"];
            }
            elseif($mode === "ALL")
            {
                $this->patientSer              = $result["PatientSer"];
                $this->firstName               = $result["FirstName"];
                $this->lastName                = $result["LastName"];
                $this->ssn                     = $result["SSN"];
                $this->ssnExpDate              = $result["SSNExpDate"];
                $this->patientId               = $result["PatientId"];
                $this->patientId_MGH           = $result["PatientId_MGH"];
                $this->smsNum                  = $result["SMSAlertNum"];
                $this->opalPatient             = $result["OpalPatient"];
                $this->languagePreference      = $result["LanguagePreference"];
            }
        }

        $this->_sanitizeObject();
    }

     #function to convert appointment fields into an ORMS db compatible form
     private function _sanitizeObject(): void
     {
        foreach(array_keys(get_object_vars($this)) as $field)
        {
            #apply some regex
            if(gettype($this->$field) === 'string')
            {
                $this->$field = str_replace("\\","",$this->$field); #remove backslashes
                #$this->$field = str_replace("'","\'",$this->$field); #escape quotes
                $this->$field = str_replace('"',"",$this->$field); #remove double quotes
                $this->$field = preg_replace("/\n|\r/","",$this->$field); #remove new lines and tabs
                $this->$field = preg_replace("/\s+/"," ",$this->$field); #remove multiple spaces
                $this->$field = preg_replace("/^\s/","",$this->$field); #remove spaces at the start
                $this->$field = preg_replace("/\s$/","",$this->$field); #remove space at the end

                if(ctype_space($this->$field) || $this->$field === "") $this->$field = NULL;
            }
        }

        #insert zeros for incomplete MRNs
        $this->patientId = str_pad($this->patientId,7,"0",STR_PAD_LEFT);

        #remove the ramq if the ramq is just a placeholder (XXXXYYMMDD)
        if(preg_match("/^[A-Z]{4}[0-9]{6}$/",$this->ssn)) {
            $this->ssn = NULL;
            $this->ssnExpDate = "0000";
        }

        #if the patient has no ramq, use the mrn as the ramq
        if($this->ssn === NULL) {
            $this->ssn = $this->patientId;
            $this->ssnExpDate = "0000";
        }
        elseif($this->ssnExpDate === NULL) {
            $this->ssnExpDate = "0000";
        }

        #uppercase names and ssn
        $this->firstName = strtoupper($this->firstName);
        $this->lastName = strtoupper($this->lastName);
        $this->ssn = strtoupper($this->ssn);

    }
}

?>
