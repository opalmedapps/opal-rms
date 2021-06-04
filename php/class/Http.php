<?php declare(strict_types=1);

namespace Orms;

use League\OpenAPIValidation\Schema\SchemaValidator;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;

use Exception;
use League\OpenAPIValidation\Schema\Exception\InvalidSchema;
use League\OpenAPIValidation\Schema\Exception\KeywordMismatch;
use Respect\Validation\Exceptions\ValidatorException;

class Http
{
    /**
     *
     * @return mixed[]
     */
    static function getPostContents(): array
    {
        $requestParams = [];

        if(!empty($_POST))
        {
            $requestParams = $_POST;
        }
        elseif(preg_match("/application\/json/",$_SERVER["CONTENT_TYPE"]))
        {
            $requestParams = json_decode(file_get_contents("php://input") ?: "",TRUE) ?? [];
        }

        foreach($requestParams as &$x) {
            if(ctype_space($x) || $x === "") $x = NULL;
        }

        return $requestParams;
    }

    /**
     *
     * @param mixed[] $params
     * @return mixed[]
     */
    static function sanitizeRequestParams(array $params): array
    {
        foreach($params as &$param)
        {
            if(gettype($param) === "string")
            {
                $param = str_replace("\\","",$param); //remove backslashes
                $param = str_replace('"',"",$param); //remove double quotes
                $param = preg_replace("/\n|\r/","",$param); //remove new lines and tabs
                $param = preg_replace("/\s+/"," ",$param ?? ""); //remove multiple spaces
                $param = preg_replace("/^\s/","",$param ?? ""); //remove spaces at the start
                $param = preg_replace("/\s$/","",$param ?? ""); //remove space at the end

                if(ctype_space($param) || $param === "") $param = NULL;
            }
        }

        return $params;
    }

    /**
     *
     * @return mixed[]
     * @throws Exception
     */
    static function parseApiInputs(): array
    {
        //get from config
        $publicApiPath = "/var/www/OnlineRoomManagementSystem/php/api/public/v1";

        $specification = Reader::readFromYamlFile(
            fileName: "$publicApiPath/openapi.yml",
            baseType: OpenApi::class,
            resolveReferences: TRUE
        );

        //get the specification section for the api being called
        $apiPath = str_replace($publicApiPath,"",$_SERVER["SCRIPT_FILENAME"] ?? "");
        $method = strtolower($_SERVER["REQUEST_METHOD"] ?? "");
        $content = preg_replace("/;\s?charset=.+$/","",$_SERVER["CONTENT_TYPE"] ?? ""); //remove the charset

        $specification = $specification->paths[$apiPath] ?? throw new Exception("Unknown api");
        $specification = $specification->$method->requestBody ?? throw new Exception("Unknown method");
        $specification = $specification->content[$content] ?? throw new Exception("Unknown content type");

        $input = self::getPostContents();

        //parse the inputs and ensure they conform to the api spec
        (new SchemaValidator(SchemaValidator::VALIDATE_AS_REQUEST))->validate($input,$specification->schema);

        return $input;
    }

    static function generateApiParseError(Exception $e): string
    {
        //various errors can be returned from the parser
        //handle all of them and decompose the errors if needed
        if($e instanceof KeywordMismatch) {
            $failedFields = $e->dataBreadCrumb()?->buildChain() ?? [];
            $failedString = implode("' -> '",$failedFields);
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
     *
     * @return never
     * @throws Exception
     */
    static function generateResponseJsonAndExit(int $httpCode,mixed $data = NULL,string $error = NULL): void
    {
        $response = [
            "status" => ($error === NULL) ? "Success" : "Error",
            "error"  => $error,
            "data"   => $data
        ];
        $response = array_filter($response,fn($x) => $x !== NULL);

        $returnString = json_encode($response) ?: throw new Exception("Unable to generate a response");

        http_response_code($httpCode);
        header("Content-Type: application/json");
        echo $returnString;
        exit;
    }

}
