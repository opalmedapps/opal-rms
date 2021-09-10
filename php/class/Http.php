<?php

declare(strict_types=1);

namespace Orms;

use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\KeywordMismatch;

class Http
{
    /**
     *
     * @return mixed[]
     */
    public static function getRequestContents(): array
    {
        $requestParams = $_GET;

        if($_POST !== []) {
            $requestParams = array_merge($requestParams,$_POST);
        }
        elseif(preg_match("/application\/json/", $_SERVER["CONTENT_TYPE"] ?? ""))
        {
            $json = json_decode(file_get_contents("php://input") ?: "", true) ?? [];
            $requestParams = array_merge($requestParams,$json);
        }

        foreach($requestParams as &$x) {
            if(is_string($x) === true && ctype_space($x) || $x === "") $x = null;
        }

        return $requestParams;
    }

    /**
     *
     * @param mixed[] $params
     * @return mixed[]
     */
    public static function sanitizeRequestParams(array $params): array
    {
        foreach($params as &$param)
        {
            if(is_string($param) === true)
            {
                $param = str_replace("\\", "", $param); //remove backslashes
                $param = str_replace('"', "", $param); //remove double quotes
                $param = preg_replace("/\n|\r/", "", $param); //remove new lines and tabs
                $param = preg_replace("/\s+/", " ", $param ?? ""); //remove multiple spaces
                $param = preg_replace("/^\s/", "", $param ?? ""); //remove spaces at the start
                $param = preg_replace("/\s$/", "", $param ?? ""); //remove space at the end

                if(is_string($param) === true && ctype_space($param) || $param === "") $param = null;
            }
            elseif(is_array($param) === true) {
                $param = self::sanitizeRequestParams($param);
            }
        }

        return $params;
    }

    /**
     *
     * @return mixed[]
     * @throws Exception
     */
    public static function parseApiInputs(): array
    {
        //get from config
        $basePath = Config::getApplicationSettings()->environment->basePath;
        $apiSpecFile = "$basePath/php/api/public/v1/openapi.yml";

        //get the specifications for the api being called
        $apiPath = str_replace([$basePath,".php"], "", $_SERVER["SCRIPT_FILENAME"] ?? "");
        $method = mb_strtolower($_SERVER["REQUEST_METHOD"] ?? "");
        $contentType = preg_replace("/;\s?charset=.+$/", "", $_SERVER["CONTENT_TYPE"] ?? ""); //remove the charset

        //parse the request inputs and ensure they conform to the api spec
        if($method === "get") {
            $inputs = self::sanitizeRequestParams($_GET);
            $request = (new ServerRequest($method,$apiPath))->withQueryParams($inputs);
        }
        else {
            $inputs = self::getRequestContents();
            $request = (new ServerRequest($method,$apiPath))->withHeader("Content-Type",$contentType)->withBody(Utils::streamFor(json_encode($inputs)));
        }

        $validator = (new ValidatorBuilder())->fromYamlFile($apiSpecFile)->getServerRequestValidator();
        /** @psalm-suppress ArgumentTypeCoercion */ //bug with psalm; it can't properly detect a static return type
        $validator->validate($request);

        return $inputs;
    }

    public static function generateApiParseError(Exception $e): string
    {
        //various errors can be returned from the parser
        //handle all of them and decompose the errors if needed

        if($e instanceof InvalidBody) {
            $prevErr = $e->getPrevious();

            if($prevErr instanceof KeywordMismatch) {
                $failedFields = $prevErr->dataBreadCrumb()?->buildChain() ?? [];
                $failedString = implode("' -> '", $failedFields);
                $errorString = "Field '$failedString': {$prevErr->getMessage()}";
            }
            else {
                $errorString = $e->getMessage();
            }
        }
        else {
            $errorString = $e->getMessage();
        }

        return $errorString;
    }

    /**
     * Returns a response to client without stopping script execution. Since the connection with the client gets closed, no further responses can be sent.
     *
     */
    public static function generateResponseJsonAndContinue(int $httpCode, mixed $data = null, string $error = null): void
    {
        $response = [
            "status" => ($error === null) ? "Success" : "Error",
            "error"  => $error,
            "data"   => $data
        ];
        $response = array_filter($response, fn($x) => $x !== null);

        $returnString = json_encode($response) ?: throw new Exception("Unable to generate a response");

        ob_start();
        http_response_code($httpCode);
        header("Content-Type: application/json");
        echo $returnString;
        header("Connection: close");
        header("Content-Length: ".ob_get_length());
        ob_end_flush();
        ob_flush();
        flush();
    }

    /**
     *
     * @return never
     */
    public static function generateResponseJsonAndExit(int $httpCode, mixed $data = null, string $error = null): void
    {
        self::generateResponseJsonAndContinue($httpCode, $data, $error);
        exit;
    }

}
