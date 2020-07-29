<?php

namespace JOetjen\AbstractCLI;

/**
 * Class CLIException
 *
 * @author  Jan Oetjen <oetjenj@gmail.com>
 * @version 0.0.3
 */
class CLIException extends \Exception
{

  /**
   * Create a new CLIException instance. The message of this exception is produced by using sprintf.
   * See {@link http://php.net/sprintf sprintf} for information on further details of the format string.
   *
   * @param string $format  The formatting string.
   * @param mixed  ...$args The arguments used in the format string.
   */
  public function __construct($format, $args = null)
  {
    /** @noinspection SuspiciousAssignmentsInspection */
    $args = func_get_args();
    $msg  = call_user_func_array('sprintf', $args);

    parent::__construct($msg);
  }

}
