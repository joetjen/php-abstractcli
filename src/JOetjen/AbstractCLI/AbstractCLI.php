<?php

namespace JOetjen\AbstractCLI;

/**
 * Class AbstractCLI
 *
 * This class provides all the functions to defined, parse and process command line options and arguments. Also this
 * class comes with a few predefined command line options, namely -V|--version, -h|--help and -v|--verbose.
 *
 * To use this class extend it with your own class and in the constructor define the command line options and arguments
 * you want to provide to the end user. See {@link addOption()} and {@link addArgument()} for more information about how
 * to add those. Additionally you can set the program version with {@link setVersion()}, the summary with
 * {@link setSummary()} and the footer with {@link setFooter()}.
 *
 * @author  Jan Oetjen <oetjenj@gmail.com>
 * @version 0.0.3
 */
abstract class AbstractCLI
{

  /**
   * As soon as the parser reaches this option it will call the function from the corresponding 'call' attribute.
   */
  const TYPE__FUNCTION = 'function';

  /**
   * This option or argument has a mandatory value parameter.
   */
  const TYPE__MANDATORY = 'mandatory';

  /**
   * This option or argument has an optional value parameter.
   */
  const TYPE__OPTIONAL = 'optional';

  /**
   * This option is a boolean switch. It is off (false) by default and can be turned on (true) by using this option
   * on the command line.
   */
  const TYPE__SWITCH = 'switch';

  /**
   * An array of all valid option and argument types.
   *
   * @var array
   */
  private static $types = array(
    self::TYPE__SWITCH,
    self::TYPE__OPTIONAL,
    self::TYPE__MANDATORY,
    self::TYPE__FUNCTION,
  );

  /**
   * All added arguments get stored here with their data structure. Add arguments with {@link addArguments()}.
   *
   * @var array
   */
  private $arguments = array();

  /**
   * The name of the commandline program. The parser expects the first argument of the original arguments array
   * (see {$link run()}) to be the name of the commandline program.
   *
   * @var null|string
   */
  private $command;

  /**
   * A footer message for the help output. Set with {@link setFooter()}.
   *
   * @var null|string
   */
  private $footer;

  /**
   * All added options get stored here with the data structure. Add options with {@link addOption()}.
   *
   * @var array
   */
  private $options = array();

  /**
   * An array containing all parsed values.
   *
   * @var array
   */
  private $parsed = array();

  /**
   * A summary of the program. Set with {@link setSummary()}.
   *
   * @var null|string
   */
  private $summary;

  /**
   * The version of the program. If this is not set the commandline parser with output the last modification date
   * of the commandline program. Use {@link setVersion()} to set the version.
   *
   * @var null|string
   */
  private $version;

  /**
   * Create a new parser instance. This also automatically add the following commandline options:
   *   -h|--help     Print the help text and exit.
   *   -v|--verbose  Make the program more talkative.
   *   -V|--version  Print the version and exit.
   *
   * @throws CLIException
   */
  public function __construct()
  {
    $this->addOption(array(
      'short' => 'v',
      'long'  => 'verbose',
      'type'  => self::TYPE__SWITCH,
      'desc'  => 'Make the script more talkative!',
    ));

    $this->addOption(array(
      'short' => 'h',
      'long'  => 'help',
      'type'  => self::TYPE__FUNCTION,
      'desc'  => 'This help text.',
      'call'  => 'printHelp',
    ));

    $this->addOption(array(
      'short' => 'V',
      'long'  => 'version',
      'type'  => self::TYPE__FUNCTION,
      'desc'  => 'Show version and quit.',
      'call'  => 'printVersion',
    ));
  }

  /**
   * Create an instance of the AbstractCLI child this function is called on, parse the arguments supplied and call
   * the instance's {@link execute()} method with an array of the parsed arguments.
   *
   * All CLIExceptions thrown during execution are caught and displayed in a formatted manner.
   *
   * @param array $args An array containing arguments and options from the commandline. The first entry in this array
   *                    must the name of the program.
   *
   * @return mixed|null Returns the return value of {@link execute()}.
   */
  final public static function run(array $args)
  {
    $cli = new static();

    try {
      $cli->parse($args);
      $cli->checkArgumentCount();

      return $cli->execute();
    } catch (CLIException $e) {
      echo sprintf(' * ERROR: %s%s', $e->getMessage(), PHP_EOL);

      exit(1);
    }
  }

  /**
   * Get the idx-nth argument or return default if argument is not found.
   *
   * @param int        $idx     The index of the argument (starts with 0).
   * @param mixed|null $default The default value to return if argument is not set (null by default).
   *
   * @return mixed|null
   */
  final public function getArgument($idx, $default = null)
  {
    if (array_key_exists($idx, $this->parsed)) {
      return $this->parsed[ $idx ];
    }

    return $default;
  }

  /**
   * Find the option value in the parsed array or return default if not found.
   *
   * @param string     $name    The name (long or short) of the option.
   * @param mixed|null $default The default value to return if option is not found or not set (null by default).
   *
   * @return mixed|null
   */
  final public function getOption($name, $default = null)
  {
    $option = $this->findOption(array('long' => $name));

    if ( ! $option) {
      $option = $this->findOption(array('short' => $name));
    }

    if ($option) {
      $key = $option[ array_key_exists('long', $option) ? 'long' : 'short' ];

      if (array_key_exists($key, $this->parsed)) {
        return $this->parsed[ $key ];
      }
    }

    return $default;
  }

  /**
   * Return true if the option is set. False otherwise.
   *
   * @param string $name The name of the option.
   *
   * @return bool
   */
  final public function is($name)
  {
    return $this->getOption($name, false) !== false;
  }

  /**
   * Add an argument definition.
   *
   * The passed array must contain the following indexes:
   *   name  The name of the argument for display in the summary and error messages. This should be all be upper-case.
   *   type  The type of the argument. Possible values are {@link TYPE__MANDATORY} and {@link TYPE__OPTIONAL}.
   *
   * Additionally the following indexes can be used:
   *   check  The name of a callback function of check the validity of the passed valued. It should throw a
   *          CLIException if the value is wrong.
   *
   *          void callback( $value )
   *
   * If the argument "name" end with "..." the argument can receive unlimited values. Be careful only to use this on
   * the last optional argument added as otherwise no further optional arguments will receive values.
   *
   * @param array $params The definition of the new argument.
   *
   * @return AbstractCLI returns an instance of the CLI.
   *
   * @throws CLIException
   */
  final protected function addArgument(array $params)
  {
    if ( ! array_key_exists('name', $params)) {
      throw new CLIException('Arguments must have a "name"!');
    }

    $name = $params['name'];

    if ( ! array_key_exists('type', $params)) {
      throw new CLIException('Argument "%s" must have a type!', $name);
    }

    $type = $params['type'];

    if ( ! in_array($type, array(self::TYPE__OPTIONAL, self::TYPE__MANDATORY), true)) {
      throw new CLIException('Type "%s" is not valid for argument "%s"!', $type, $name);
    }

    $this->arguments[] = $params;

    return $this;
  }

  /**
   * Add an option definition.
   *
   * The array must at least have either a 'short' index or a 'long' index defining the either short or the long name
   * of the option. Option names must be unique, and are case sensitive.
   *
   * The passed array also must contain the following indexes:
   *   type  The type of the argument. Possible values are {@link TYPE__SWITCH}, {@link TYPE__FUNCTION},
   *         {@link TYPE__MANDATORY} and {@link TYPE__OPTIONAL}.
   *
   * Additionally the following indexes can be used:
   *   check  The name of a callback function of check the validity of the passed valued. It should throw a
   *          CLIException if the value is wrong.
   *
   *          void callback( $value )
   *
   * Options of type {@link TYPE__MANDATORY} or {@link TYPE__OPTIONAL} must have an index called 'name' defining the
   * name of the value.
   *
   * Options of type {@link TYPE__FUNCTION} must have an index called 'call' defining the function to call as soon as
   * the option is parsed.
   *
   * @param array $params The definition of the new argument.
   *
   * @return AbstractCLI returns an instance of the CLI.
   *
   * @throws CLIException
   */
  final protected function addOption(array $params)
  {
    if ( ! array_key_exists('short', $params) && ! array_key_exists('long', $params)) {
      throw new CLIException('An option must at least have a short or long name!');
    }

    $name = $params[ array_key_exists('long', $params) ? 'long' : 'short' ];

    if ( ! array_key_exists('type', $params)) {
      throw new CLIException('Option "%s" must have a type!', $name);
    }

    $type = $params['type'];

    if ( ! in_array($type, self::$types, true)) {
      throw new CLIException('"%s" is no valid option type for option "%s"!', $type, $name);
    }

    if ( ! array_key_exists('name', $params) && in_array($type, array(self::TYPE__OPTIONAL, self::TYPE__MANDATORY), true)) {
      throw new CLIException('Option "%s", of type %s, must have a "name" attribute!', $name, $type);
    }

    if ($type === self::TYPE__FUNCTION && ! array_key_exists('call', $params)) {
      throw new CLIException('Option "%s", of type %s, must have a "call" attribute!', $name, $type);
    }

    if ($this->findOption($params) !== false) {
      throw new CLIException('The short or long name for "%s" is already taken by another option!', $name);
    }

    $this->options[] = $params;

    return $this;
  }

  /**
   * Check if the parsed array contains at least an entry for each mandatory argument. This functions throws an
   * Exception on error or otherwise silently passes.
   *
   * @return AbstractCLI returns an instance of the CLI.
   *
   * @throws CLIException
   */
  final protected function checkArgumentCount() {
    $idx = 0;

    foreach($this->arguments as $argument) {
      if ($argument['type'] === self::TYPE__MANDATORY) {
        if ( ! array_key_exists($idx, $this->parsed)) {
          throw new CLIException('"%s" is a required argument!', $argument['name']);
        }

        ++$idx;
      }
    }

    return $this;
  }

  /**
   * Called after the arguments are parsed into an option array.
   *
   * @return mixed|null
   *
   * @throws CLIException  The execute method should throw an error or exit with an exit value other than zero if the
   *                       program could not run successfully.
   */
  abstract protected function execute();

  /**
   * Format and print the footer if one was defined.
   */
  protected function printFooter()
  {
    if ( ! is_null($this->footer)) {
      echo PHP_EOL;
      echo $this->realignText($this->footer);
      echo PHP_EOL;
    }
  }

  /**
   * Format and print the program help.
   */
  protected function printHelp()
  {
    $this->printUsage();

    $maxLen = 0;
    $output = array();

    foreach ($this->options as $option) {
      if (array_key_exists('desc', $option)) {
        $key = '';

        if (array_key_exists('short', $option)) {
          $key .= sprintf('-%s', $option['short']);
        }

        if (array_key_exists('long', $option)) {
          $key .= empty($key) ? '    ' : ', ';
          $key .= sprintf('--%s', $option['long']);
        }

        if ($option['type'] === self::TYPE__OPTIONAL) {
          $key .= sprintf(' [%s]', strtoupper($option['name']));
        }

        if ($option['type'] === self::TYPE__MANDATORY) {
          $key .= sprintf(' %s', strtoupper($option['name']));
        }

        $output[ $key ] = $option['desc'];
        $maxLen = max($maxLen, strlen($key));
      }
    }

    uksort($output, function ($a, $b) {
      $_a = strtolower(preg_replace('/^\s*-/', '', $a));
      $_b = strtolower(preg_replace('/^\s*-/', '', $b));

      return $_a === $_b ? 0 : ($_a < $_b ? -1 : 1);
    });

    if ( ! empty($output)) {
      echo 'OPTIONS:';
      echo PHP_EOL;

      foreach ($output as $opt => $desc) {
        echo $this->realignText(sprintf('  %s %s%s', str_pad($opt, $maxLen), $desc, PHP_EOL), $maxLen + 3);
      }

    }

    $this->printFooter();
  }

  /**
   * Format and print the program summary if one was defined.
   */
  protected function printSummary()
  {
    if ( ! is_null($this->summary)) {
      echo $this->realignText($this->summary);
      echo PHP_EOL;
    }
  }

  /**
   * Format and print the program usage.
   */
  protected function printUsage()
  {
    $usage = sprintf('USAGE: %s', $this->command);

    if ( ! empty($this->options)) {
      $usage .= ' [OPTIONS...]';
    }

    foreach ($this->arguments as $argument) {
      if ($argument['type'] === self::TYPE__MANDATORY) {
        $usage .= sprintf(' %s', strtoupper($argument['name']));
      }
    }

    foreach ($this->arguments as $argument) {
      if ($argument['type'] === self::TYPE__OPTIONAL) {
        $usage .= sprintf(' [%s]', strtoupper($argument['name']));
      }
    }

    echo $this->realignText($usage);
    echo PHP_EOL;

    $this->printSummary();

    echo PHP_EOL;
  }

  /**
   * Format and print the program name and version.
   */
  protected function printVersion()
  {
    $this->version = $this->version ?: date("Y-m-d H:i:s", filemtime($this->command));

    echo $this->realignText(sprintf('%s v%s%s', $this->command, $this->version, PHP_EOL));
    echo PHP_EOL;
  }

  /**
   * Set the footer message to the value given.
   *
   * @param $footer
   *
   * @return AbstractCLI returns an instance of the CLI.
   */
  final protected function setFooter($footer)
  {
    $this->footer = $footer;

    return $this;
  }

  /**
   * Set the summary to the value given.
   *
   * @param $summary
   *
   * @return AbstractCLI returns an instance of the CLI.
   */
  final protected function setSummary($summary)
  {
    $this->summary = $summary;

    return $this;
  }

  /**
   * Set the program version to the value given.
   *
   * @param $version
   *
   * @return AbstractCLI returns an instance of the CLI.
   */
  final protected function setVersion($version)
  {
    $this->version = $version;

    return $this;
  }

  /**
   * Expect an array with either a 'short' or 'long' index set and looks through all known options if there is one
   * matching.
   *
   * @param array $params
   *
   * @return array|bool Return the option of false if no matching option was found.
   */
  private function findOption(array $params)
  {
    return array_reduce($this->options, function ($memo, $option) use ($params) {
      if ($memo !== false) {
        return $memo;
      }

      if (array_key_exists('long', $option) &&
        array_key_exists('long', $params) &&
        $option['long'] === $params['long']
      ) {
        return $option;
      }

      if (array_key_exists('short', $option) &&
        array_key_exists('short', $params) &&
        $option['short'] === $params['short']
      ) {
        return $option;
      }

      return $memo;
    }, false);
  }

  /**
   * Initialize the option in the parsed array with a default of false for options of type {@link TYPE__SWITCH}.
   *
   * @param array $parsed The current parsed array.
   * @param array $option The option to consider.
   *
   * @return array The updated parsed array.
   */
  private function initParsed(array $parsed, array $option)
  {
    if ($option['type'] === self::TYPE__SWITCH) {
      return $this->setParsed($parsed, $option, false);
    }

    return $parsed;
  }

  /**
   * Parse args and return a parsed array.
   *
   * @param array $args The commandline arguments as array. The first entry must be the name of the program.
   *
   * @return AbstractCLI returns an instance of the CLI.
   *
   * @throws CLIException
   */
  private function parse(array $args)
  {
    $this->command = array_shift($args);
    $parsed = array_reduce($this->options, array($this, 'initParsed'), array());

    if (is_null($this->command)) {
      throw new CLIException('Arguments to method parse must be an array with the command name as first entry.');
    }

    while ( ! empty($args)) {
      $value = array_shift($args);
      $match = array();

      if (preg_match('/^--(.+)$/', $value, $match)) {
        list($args, $parsed) = $this->parseLongOption($match[1], $args, $parsed);
      } elseif (preg_match('/^-(.+)$/', $value, $match)) {
        list($args, $parsed) = $this->parseShortOptions($match[1], $args, $parsed);
      } else {
        list($args, $parsed) = $this->parseArguments($value, $args, $parsed);
      }
    }

    $this->parsed = $parsed;

    return $this;
  }

  /**
   * Parse the arguments and return an array containing the updated array. {@link TYPE__OPTIONAL} arguments whose name
   * end with "..." can receive unlimited values.
   *
   * @param string $value  The value to be set for the argument.
   * @param array  $args   The remaining arguments of the command line.
   * @param array  $parsed The current parsed array.
   *
   * @return array The updated remaining arguments and the updated parsed array.
   *
   * @throws CLIException
   */
  private function parseArguments($value, array $args, array $parsed)
  {
    $idx = 0;

    foreach ($this->arguments as $argument) {
      if ($argument['type'] === self::TYPE__MANDATORY) {
        if ( ! array_key_exists($idx, $parsed)) {
          if (array_key_exists('check', $argument)) {
            $this->{$argument['check']}($value);
          }

          $parsed[ $idx ] = $value;

          return array($args, $parsed);
        }

        ++$idx;
      }
    }

    foreach ($this->arguments as $argument) {
      if ($argument['type'] === self::TYPE__OPTIONAL) {
        if ( ! array_key_exists($idx, $parsed) ||  preg_match('/\.\.\.$/', $argument['name'])) {
          if (array_key_exists('check', $argument)) {
            $this->{$argument['check']}($value);
          }

          $parsed[ $idx ] = $value;

          return array($args, $parsed);
        }

        ++$idx;
      }
    }

    throw new CLIException('Too many arguments!');
  }

  /**
   * Parse the option used by it's long name from value and return an array containing the updated array. Should value
   * contain an equal sign (=), everything after it will be pushed back to the remaining arguments array.
   *
   * @param string $value  The value to be set for the option.
   * @param array  $args   The remaining arguments of the command line.
   * @param array  $parsed The current parsed array.
   *
   * @return array The updated remaining arguments and the updated parsed array.
   *
   * @throws CLIException
   */
  private function parseLongOption($value, array $args, array $parsed)
  {
    $split = explode('=', $value, 2);

    if (count($split) === 2) {
      array_unshift($args, $split[1]);
    }

    return $this->parseOption('long', $split[0], $args, $parsed);
  }

  /**
   * Parse the option name and return an array containing the updated array. Type must either be 'short' or 'long' and
   * is used the declare if 'name' is either the short or long name of the option.
   *
   * @param string $type   The type ('short' or 'long') of the option.
   * @param string $name   The name of the option.
   * @param array  $args   The remaining arguments of the command line.
   * @param array  $parsed The current parsed array.
   *
   * @return array The updated remaining arguments and the updated parsed array.
   *
   * @throws CLIException
   */
  private function parseOption($type, $name, array $args, array $parsed)
  {
    if (array_key_exists(0, $parsed)) {
      throw new CLIException('Options must come before arguments!');
    }

    $option = $this->findOption(array($type => $name));

    if ($option === false) {
      throw new CLIException('Unknown option "%s"!', $name);
    }

    switch ($option['type']) {
      case self::TYPE__FUNCTION:
        $this->{$option['call']}();

        exit(1);

      case self::TYPE__SWITCH:
        $parsed = $this->setParsed($parsed, $option, true);

        break;

      case self::TYPE__OPTIONAL:
        $value = count($args) > 1 ? array_shift($args) : true;

        if (array_key_exists('check', $option)) {
          $this->{$option['check']}($value);
        }

        $parsed = $this->setParsed($parsed, $option, $value);

        break;

      case self::TYPE__MANDATORY:
        if (count($args) < 1) {
          throw new CLIException('Missing parameter for option "%s"!', $name);
        }

        $value = array_shift($args);

        if (array_key_exists('check', $option)) {
          $this->{$option['check']}($value);
        }

        $parsed = $this->setParsed($parsed, $option, $value);

        break;
    }

    return array($args, $parsed);
  }

  /**
   * Parse the options used by their short name from value and return an array containing the updated array.
   *
   * @param string $value  The value to be set for the option.
   * @param array  $args   The remaining arguments of the command line.
   * @param array  $parsed The current parsed array.
   *
   * @return array The updated remaining arguments and the updated parsed array.
   *
   * @throws CLIException
   */
  private function parseShortOptions($value, array $args, array $parsed)
  {
    $split = str_split($value);

    foreach ($split as $name) {
      list($args, $parsed) = $this->parseOption('short', $name, $args, $parsed);
    }

    return array($args, $parsed);
  }

  /**
   * Realign text by breaking it into separate words and reassemble it ensuring it's line length is not more than
   * maxWidth. All lines except the first are left padded with $padding spaces.
   *
   * @param string $text     The text to realign.
   * @param int    $padding  The number of spaces to prepend to lines (except for the first line).
   * @param int    $maxWidth The maximum number of characters per line.
   *
   * @return string The realigned text.
   */
  private function realignText($text, $padding = 0, $maxWidth = 80)
  {
    $realignedText = '';

    if ( ! empty($text)) {
      $words = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
      $pos = 0;

      foreach ($words as $word) {
        if ($pos + strlen($word) >= $maxWidth) {
          $realignedText .= PHP_EOL;

          if ($padding > 0) {
            $realignedText .= implode('', array_fill(0, $padding, ' '));
          }

          $pos = $padding;
        }

        $realignedText .= $word;

        $pos += strlen($word);
      }
    }

    return $realignedText;
  }

  /**
   * Update the current parsed array with the value for the option and return the updated parsed array. If the option
   * has a long name it's used as the index in the parsed array, otherwise the short name will be used.
   *
   * @param array $parsed The current parsed array.
   * @param array $option The option to set the value for.
   * @param mixed $value  The value to set.
   *
   * @return array The updated parsed array.
   */
  private function setParsed(array $parsed, array $option, $value)
  {
    if (array_key_exists('long', $option)) {
      $parsed[ $option['long'] ] = $value;
    } else {
      $parsed[ $option['short'] ] = $value;
    }

    return $parsed;
  }

}
