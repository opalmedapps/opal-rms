<?php

class Appointment
{

    #definition of an Appointment
    public $appointmentSer      = NULL;

    public $patient             = NULL;
    public $appointmentCode     = NULL;
    public $creationDate        = NULL;
    public $id                  = NULL;
    public $referringMd         = NULL;
    public $resource            = NULL;
    public $resourceDesc        = NULL;
    public $scheduledDate       = NULL;
    public $scheduledDateTime   = NULL;
    public $scheduledTime       = NULL;
    public $site                = NULL;
    public $sourceStatus        = NULL;
    public $status              = NULL;
    public $system              = NULL;
    public $type                = NULL;

    public function __construct(array $appointmentInfo, Patient $patientInfo = NULL)
    {
        foreach(array_keys(get_object_vars($this)) as $field)
        {
            $this->$field = (isset($args[$field])) ? $args[$field] : NULL;
        }

        $this->$patient = $patientInfo;
    }

    public function insertAppointmentInDatabase()
    {

    }

    #delete similar appointment function
}

?>