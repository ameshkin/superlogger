<?php
namespace Ameshkin\Logger;

use DateTime;
use RuntimeException;
use Psr\Log\AbstractLogger;
use Dotenv;

/**
 * Class Log
 * @package Ameshkin\Logger
 */
class Log extends AbstractLogger
{

  /**
   * File directory for log file
   * @var string
   */
  private $logFilePath;


  protected $logLevels = array(
    0 => 'Debug',
    1 => 'Info',
    2 => 'Notice',
    3 => 'Warning',
    4 => 'Error',
    5 => 'Alert',
    6 => 'Critical',
    7 => 'Emergency',
  );

  /**
   * File Handle for writing to log files
   * @var resource
   */
  private $fileHandle;

  /**
   * Log constructor.
   * @param $env
   */
  public function __construct($env)
  {

    $dotenv = Dotenv\Dotenv::createImmutable($env);
    $dotenv->load();

    if ($_ENV['LOG'] === '2')
    {
      if (!file_exists($_ENV['LOGDIR']))
      {
        mkdir($_ENV['LOGDIR'], $_ENV['PERMISSIONS'], true);
      }

      if (strpos($_ENV['LOGDIR'], 'php://') === 0)
      {
        $this->setLogToStdOut($_ENV['LOGDIR']);
        $this->setFileHandle('w+');
      }
      else
      {
        $this->setLogFilePath($_ENV['LOGDIR']);
        if (file_exists($this->logFilePath) && !is_writable($this->logFilePath))
        {
          throw new RuntimeException('The file could not be written to. Check that appropriate permissions have been set.');
        }
        $this->setFileHandle('a');
      }

      if (!$this->fileHandle)
      {
        throw new RuntimeException('The file could not be opened. Check permissions.');
      }
    }

  }

  /**
   * @param string $stdOutPath
   */
  public function setLogToStdOut($stdOutPath)
  {
    $this->logFilePath = $stdOutPath;
  }

  /**
   * Figures out the filepath from environmental variables
   * @param string $logDirectory
   */
  public function setLogFilePath($logDirectory)
  {
    if ($_ENV['FILENAME'])
    {
      if (strpos($_ENV['FILENAME'], '.log') !== false || strpos($_ENV['FILENAME'], '.txt') !== false)
      {
        $this->logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $_ENV['FILENAME'];
      }
      else
      {
        $this->logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $_ENV['FILENAME'] . '.' . $_ENV['EXTENSION'];
      }
    }
    else
    {
      $this->logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $_ENV['PREFIX'] . date($_ENV['LOG_FILE_DATE_FORMAT']) . '.' . $_ENV['EXTENSION'];
    }
  }

  /**
   * @param $writeMode
   */
  public function setFileHandle($writeMode)
  {
    $this->fileHandle = fopen($this->logFilePath, $writeMode);
  }

  /**
   * Class destructor
   */
  public function __destruct()
  {
    if ($this->fileHandle)
    {
      fclose($this->fileHandle);
    }
  }

  /**
   * Writes a line to the log without prepending a status or timestamp
   *
   * @param string $input Line to write to the log
   * @return void
   */
  public function write($input)
  {

    if (null !== $this->fileHandle)
    {
      if (fwrite($this->fileHandle, $input) === false)
      {
        throw new RuntimeException('The file could not be written to. Check that appropriate permissions have been set.');
      }
      else
      {
        $this->lastLine = trim($input);
        $this->logLineCount++;

        if ($_ENV['FLUSHFREQUENCY'] && $this->logLineCount % $_ENV['FLUSHFREQUENCY'] === 0)
        {
          fflush($this->fileHandle);
        }
      }
    }
  }

  /**
   * Formats the message
   * @param $input
   * @param $level
   * @param int $object
   * @return array
   * @throws \Exception
   */
  protected function formatMessage($input, $level, $is_object = 0)
  {

    $level = $this->logLevels[$level];
    $timestamp = '[' . $this->getTimestamp() . '] ';
    $level = '[' . $level . '] ';

    if ($is_object)
    {
      if ($_ENV['JSON'])
      {
        $string = $timestamp . $level . PHP_EOL . json_encode($input, JSON_PRETTY_PRINT);
      }
      else
      {
        $string = $timestamp . $level;
        $object = $input;
      }

    }
    else
    {
      $string = $timestamp . $level . $input;
    }

    // display backtrace after all instances of _e() are finished
    if ($_ENV['BACKTRACE'] && $_ENV['DEBUG'])
    {
      $backtrace = debug_backtrace($_ENV['BACKTRACE_OPTIONS'], $_ENV['BACKTRACE_LEVEL']);

      if ($_ENV['JSON'])
      {
        $backtrace = json_encode($backtrace, JSON_PRETTY_PRINT);
      }
    }

    if (defined('STDIN'))
    {
      $string = $string . PHP_EOL;
    }
    else
    {
      $string = $string . '<br>';
    }

    return ["object" => $object,
      "string" => $string . PHP_EOL, "timestamp" => $timestamp, "level" => $level, "backtrace" => $backtrace, ];
  }

  /**
   * @return string
   * @throws \Exception
   */
  private function getTimestamp()
  {
    $originalTime = microtime(true);
    $date = new DateTime(date($_ENV['LOG_LINE_DATE_FORMAT'], $originalTime));
    return $date->format($_ENV['LOG_LINE_DATE_FORMAT']);
  }

  /**
   * Main error logging function
   * @param mixed $input
   * @param int $level
   * @param int $exit
   * @param int $important
   * @throws \Exception
   */
  public function log($input, $level = 7, $exit = 0, $important = 0)
  {

    // if debug OR important set to 1
    if ($_ENV['DEBUG'] || $important)
    {

      if (is_object($input) || is_array($input))
      {
        $is_object = 1;
        $output = self::formatMessage($input, $level, $is_object);
      }
      else
      {
        $is_object = 0;
        $output = self::formatMessage($input, $level, $is_object);
      }

      switch ($_ENV['LOG'])
      {
        case 0: // output to browser
          echo $output['string'];

          // if krumo is enabled, installed and we are NOT in shell then use it
          if ($_ENV['KRUMO'] && function_exists('krumo'))
          {
            krumo($output['object']);

            if ($output['backtrace'])
            {
              krumo($output['backtrace']);
            }
          }
          else
          {
            if ($is_object)
            {
              echo $output['object'];
            }

            if ($output['backtrace'])
            {
              echo $output['backtrace'];

            }
          }

          break;
        case 1: // write object/array  to error log
          error_log($output['string']);
          if ($is_object)
          {
            error_log(print_r($output['object'], true));
            if ($output['backtrace'])
            {
              error_log(print_r($output['backtrace'], true));
            }
          }
          else
          {
            if ($output['backtrace'])
            {
              error_log(print_r($output['backtrace'], true));
            }
          }

          break;
        case 2: // custom log file
          self::write($output['string']);

          if ($is_object)
          {
            self::write(print_r($output['object'], true));
          }

          if ($output['backtrace'])
          {
            self::write(print_r($output['backtrace'], true));
          }

          break;
      }
    }

    if ($exit)
    {
      exit;
    }
  }
}
