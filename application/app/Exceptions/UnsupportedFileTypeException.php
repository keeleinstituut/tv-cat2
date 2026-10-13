<?php

namespace App\Exceptions;

use RuntimeException;

/** The filters service cannot read this kind of file. */
class UnsupportedFileTypeException extends RuntimeException
{
}
