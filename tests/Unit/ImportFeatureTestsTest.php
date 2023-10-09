<?php

namespace Abivia\Ledger\Tests\Unit;

use Abivia\Ledger\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class ImportFeatureTestsTest extends TestCase
{
    public function testImportFeatures()
    {
        Artisan::call('ledger:_ift');

        $this->assertTrue(true);
    }
}
