<?php

declare(strict_types=1);

namespace Orms\System;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// Even if you're not using exceptions, you do still need to load the Exception class as it is used internally.
// https://github.com/PHPMailer/PHPMailer#installation--loading
use PHPMailer\PHPMailer\Exception;

use Orms\Config;

class Mail
{
    /**
     * @param string $subject The subject of the email
     * @param string $message The body of the email
    */
    public static function sendViaSMTP(string $subject, string $message): void
    {
        $recepients = Config::getApplicationSettings()->system->emails;
        if(!$recepients) return;

        //Create an instance; passing `true` (e.g., PHPMailer(true)) enables exceptions
        $mail = new PHPMailer();

        // Settings
        $mail->IsSMTP();
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;

        $mail->Host       = Config::getApplicationSettings()->system->emailHost;  // SMTP server
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;      // enables SMTP debug information (for testing)
        $mail->SMTPAuth   = true;                 // enable SMTP authentication
        $mail->Username   = Config::getApplicationSettings()->system->emailHostUser;     // SMTP account username
        $mail->Password   = Config::getApplicationSettings()->system->emailHostPassword; // SMTP account password
        $mail->Port       = Config::getApplicationSettings()->system->emailPort;         // set the SMTP port

        // Content
        $mail->setFrom(
            Config::getApplicationSettings()->system->emailSentFromAddress,
            'ORMS',
        );
        foreach(Config::getApplicationSettings()->system->emails as $email)
        {
            $mail->addAddress($email);
        }

        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
    }

    // The mail() function should be avoided when possible; it's both faster and safer to use SMTP to localhost.
    // See: https://exploitbox.io/paper/Pwning-PHP-Mail-Function-For-Fun-And-RCE.html
    public static function sendEmail(string $subject, string $message): void
    {
        $recepients = implode(",", Config::getApplicationSettings()->system->emails);
        if($recepients === "") return;

        $headers = [
            "From" => Config::getApplicationSettings()->system->emailSentFromAddress
        ];

        /** @psalm-suppress UnusedFunctionCall */
        mail($recepients, $subject, $message, $headers);
    }

}
