<?php

namespace App\Exceptions;

use Exception;

class RowValidationException extends Exception
{
    //
    public array $errors;
    public int $rowNumber;
    public array $rawRow;

    public function __construct(array $errors, int $rowNumber, array $rawRow)
    {
        parent::__construct('Row validation failed');
        $this->errors = $errors;
        $this->rowNumber = $rowNumber;
        $this->rawRow = $rawRow;
    }

}
