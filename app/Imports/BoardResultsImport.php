<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Minimal Excel importer for the board-result template — returns the
 * sheet as a raw 2D array. The controller does the header-mapping and
 * validation because that logic needs live access to the class's
 * subject list + student roster and would over-couple this class.
 */
class BoardResultsImport implements ToArray
{
    public function array(array $array): void
    {
        // no-op — Excel::toArray() collects the return value below.
    }
}
