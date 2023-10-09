<?php

namespace Abivia\Ledger\Tests;

use Opis\JsonSchema\Validator;

trait ValidatesJson
{
    protected static ?Validator $validator = null;

    public function validateResponse($response, string $with)
    {
        // GitHub can't find the schemas so skip this
        //        dd(file_exists(__DIR__ . '/.skipschemachecks'));
        if (file_exists(__DIR__.'/.skipschemachecks')) {
            return;
        }
        if (self::$validator === null) {
            self::$validator = new Validator();
            if (file_exists(__DIR__.'/.schemapath')) {
                self::$validator->resolver()->registerPrefix(
                    'https://ledger.abivia.com/api/json/',
                    trim(file_get_contents(__DIR__.'/.schemapath'))
                );
            }
        }

        $schemaResult = self::$validator->validate(
            $response,
            "https://ledger.abivia.com/api/json/$with.schema.json"
        );
        //        dd(file_exists(__DIR__ . '/.schemapath') ,$with ,$schemaResult);

        $valid = $schemaResult->isValid();
        //        dd( $valid  ,file_exists(__DIR__ . '/.schemapath') ,$with ,$schemaResult);

        $message = $valid ? '' : $schemaResult->error()->message();
        $this->assertTrue($valid, $message);
    }
}
