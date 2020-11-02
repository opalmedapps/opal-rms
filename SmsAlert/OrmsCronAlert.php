<?php

require_once __DIR__."/../VirtualWaitingRoom/php/loadConfigs.php";

class OrmsCronAlert
{
    //email address
    public $email = "zeyu.dou@mail.mcgill.ca, victor.matassa@muhc.mcgill.ca, yickkuan.mo@muhc.mcgill.ca";
    public $header = "CC: susie.judd@muhc.mcgill.ca,John.Kildea@muhc.mcgill.ca \r\n";

    /**
     * @param PDO $database database connection
     * @return string error message, empty if no error
     */
    private function CheckCronStatus(PDO $database)
    {
        $message = "";
        $sql = "
            SELECT UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(LastReceivedSmsFetch) AS seconds, LastReceivedSmsFetch
            FROM Cron
            ";
        $query = $database->prepare($sql);
        $query -> execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        if($row["seconds"] > 300){
            $message = "ORMS' cronjob was stopped at ".$row["LastReceivedSmsFetch"].".\n\n";
            $sql = "
                UPDATE Cron
                SET LastReceivedSmsFetch = now()
                WHERE System = 'ORMS'
                ";
            $query = $database->prepare($sql);
            $query -> execute();
        }

        return $message;
    }

    /**
     * @param PDO $database database connection
     * @return bool return true if the function is working, false for error
     */
    public function SendMail(PDO $database)
    {
        $list = $this->CheckCronStatus($database);
        if($list){

            $message = "Hi there,\n\n We got some cronjob errors, here is some detail.\n\n".$list."Thanks.\n";
            mail($this->email,OTHER_ERROR,$message,$this->header);
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