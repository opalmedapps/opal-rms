<?php

declare(strict_types=1);

namespace Orms\System;

use Orms\Config;

class Mail
{
    public static function sendEmail(string $subject, string $message): void
    {
        $recepients = implode(",", Config::getApplicationSettings()->system->emails);
        if($recepients === "") return;

        $headers = [
            "From" => "opal@muhc.mcgill.ca"
        ];

        /** @psalm-suppress UnusedFunctionCall */
        mail($recepients, $subject, $message, $headers);
    }

}
