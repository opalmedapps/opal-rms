<?php

declare(strict_types=1);

namespace Orms;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Exception;
use League\OpenAPIValidation\Schema\Exception\InvalidSchema;
use League\OpenAPIValidation\Schema\Exception\KeywordMismatch;
use League\OpenAPIValidation\Schema\SchemaValidator;
use Respect\Validation\Exceptions\ValidatorException;

class Http
{
    /**
     *
     * @return mixed[]
     */
    public static function getPostContents(): array
    {
        $requestParams = [];

        if(!empty($_POST))
        {
            $requestParams = $_POST;
        }
        elseif(preg_match("/application\/json/", $_SERVER["CONTENT_TYPE"]))
        {
            $requestParams = json_decode(file_get_contents("php://input") ?: "", true) ?? [];
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
            if(gettype($param) === "string")
            {
                $param = str_replace("\\", "", $param); //remove backslashes
                $param = str_replace('"', "", $param); //remove double quotes
                $param = preg_replace("/\n|\r/", "", $param); //remove new lines and tabs
                $param = preg_replace("/\s+/", " ", $param ?? ""); //remove multiple spaces
                $param = preg_replace("/^\s/", "", $param ?? ""); //remove spaces at the start
                $param = preg_replace("/\s$/", "", $param ?? ""); //remove space at the end

                if(is_string($param) === true && ctype_space($param) || $param === "") $param = null;
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
        $publicApiPath = "/var/www/OnlineRoomManagementSystem/php/api/public/v1";

        $specification = Reader::readFromYamlFile(
            fileName: "$publicApiPath/openapi.yml",
            baseType: OpenApi::class,
            resolveReferences: true
        );

        //get the specification section for the api being called
        $apiPath = str_replace([$publicApiPath,".php"], "", $_SERVER["SCRIPT_FILENAME"] ?? "");
        $method = mb_strtolower($_SERVER["REQUEST_METHOD"] ?? "");
        $content = preg_replace("/;\s?charset=.+$/", "", $_SERVER["CONTENT_TYPE"] ?? ""); //remove the charset

        $specification = $specification->paths[$apiPath] ?? throw new Exception("Unknown api");
        $specification = $specification->$method->requestBody ?? throw new Exception("Unknown method");
        $specification = $specification->content[$content] ?? throw new Exception("Unknown content type");

        $input = self::getPostContents();

        //parse the inputs and ensure they conform to the api spec
        (new SchemaValidator(SchemaValidator::VALIDATE_AS_REQUEST))->validate($input, $specification->schema);

        return $input;
    }

    public static function generateApiParseError(Exception $e): string
    {
        //various errors can be returned from the parser
        //handle all of them and decompose the errors if needed
        if($e instanceof KeywordMismatch) {
            $failedFields = $e->dataBreadCrumb()?->buildChain() ?? [];
            $failedString = implode("' -> '", $failedFields);
            $errorString = "Field '$failedString': {$e->getMessage()}";
        }
        elseif($e instanceof InvalidSchema) {

            $trueError = $e->getPrevious();

            if($trueError instanceof ValidatorException) {
                $errorString = $trueError->getFullMessage();
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
