<?php

require_once __DIR__."/../VirtualWaitingRoom/php/loadConfigs.php";

class OpalCronAlert
{
    //email address
    public $email = "zeyu.dou@mail.mcgill.ca, victor.matassa@muhc.mcgill.ca, yickkuan.mo@muhc.mcgill.ca";
    public $header = "CC: susie.judd@muhc.mcgill.ca,John.Kildea@muhc.mcgill.ca \r\n";

    /**
     * @param PDO $database database connection
     * @return string error message, empty if no error
     */
    private function CheckOpalCron(PDO $database)
    {
        $message = "";
        $sql = "
            SELECT now() - C.CronDateTime AS seconds, CronDateTime 
            FROM CronLog C
            WHERE C.CronDateTime > curdate()
            AND CronStatus = 'Started'
            ORDER BY C.CronDateTime desc
            limit 1
            ";
        $query = $database->prepare($sql);
        $query -> execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        if($row["seconds"] > 900){
            $message = "Opal's Cronjob was stopped at ".$row["CronDateTime"].".\n\n";

        }

        return $message;
    }

    /**
     * @param PDO $database database connection
     * @return bool return true if the function is working, false for error
     */
    public function SendMail(PDO $database)
    {
        $list = $this->CheckOpalCron($database);
        if($list){
            $message = "Hi there,\n\n We got some opal cronjob errors, here is some detail\n\n".$list."Thanks.\n";
            mail($this->email,OTHER_ERROR,$message, $this->header);
            echo "Success, email send.\n".$message;
            return true;
        }
        else{
            echo "No error,Success.\n";
            return true;
        }
        return false;
    }
}