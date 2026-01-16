<?php

namespace App\Domain\Marketplace\Exceptions;

use Exception;

/**
 * Exception thrown when attempting to add more than 3 quotes to a mission
 */
class MaximumQuotesExceededException extends Exception
{
 public function __construct(string $missionId)
 {
  parent::__construct("Mission {$missionId} already has the maximum of 3 quotes");
 }
}
