<?php

namespace Abivia\Ledger\Reports;

use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Models\ReportData;

abstract class AbstractReport
{
    abstract public function collect(Report $message);

    abstract public function prepare(ReportData $reportData);
}
