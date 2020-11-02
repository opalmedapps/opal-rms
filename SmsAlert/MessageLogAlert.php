<?php

require_once __DIR__."/../VirtualWaitingRoom/php/loadConfigs.php";

class MessageLogAlert
{
    //email address
    public $email = "zeyu.dou@mail.mcgill.ca, victor.matassa@muhc.mcgill.ca, yickkuan.mo@muhc.mcgill.ca";
    public $header = "CC: susie.judd@muhc.mcgill.ca,John.Kildea@muhc.mcgill.ca \r\n";

    /**
     * @param PDO $database database connection
     * @return string error message, empty if no error
     */
    private function CheckMessageNumber(PDO $database)
    {
        $message = "";
        $sql = "
            SELECT ClientPhoneNumber, count(*) AS NumMessages
            FROM SmsLog S
            WHERE S.SmsTimestamp > NOW()-900
            GROUP BY ClientPhoneNumber
            HAVING count(*) > 10;
            ";
        $query = $database->prepare($sql);
        $query -> execute();

        while($row = $query->fetch(PDO::FETCH_ASSOC)){
            $message .= $row["ClientPhoneNumber"]." got ".$row["NumMessages"]." messages.\n";
        }
        if($message) {
            $message = "These patients got more than 10 messages recently:\n".$message."\n";
        }

        return $message;
    }

    /**
     * @param PDO $database database connection
     * @return bool return true if the function is working, false for error
     */
    public function SendMail(PDO $database)
    {
        $list = $this->CheckMessageNumber($database);
        if($list){
            $message = "Hi there,\n\n We got some unknown errors, here is some detail.\n\n".$list."Thanks.\n";
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