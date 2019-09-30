<?php

class Patient
{
    #definition of a Patient
    public $patientSer              = NULL;

    public $firstName               = NULL;
    public $lastName                = NULL;
    public $ssn                     = NULL;
    public $ssnExpDate              = NULL;
    public $patientId               = NULL;
    public $patientId_MGH           = NULL;
    public $smsNum                  = NULL;
    public $opalPatient             = NULL;
    public $languagePreference      = NULL;


    public function __construct(array $args = NULL)
    {
        foreach(array_keys(get_object_vars($this)) as $field)
        {
            $this->$field = (isset($args[$field])) ? $args[$field] : NULL;
        }

    }

    #public function getPatientInfor
}

?>