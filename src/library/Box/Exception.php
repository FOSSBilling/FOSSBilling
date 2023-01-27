<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Box_Exception extends Exception
{
	/**
	 * Creates a new translated exception.
	 *
	 * @param   string   error message
	 * @param   array|null    translation variables
	 * @param   int 	 The exception code.
	 * @param 	bool 	 If the varibles in this should be considered protect, if so, hide them from the stack trace. 
	 */
	public function __construct(string $message, array|null $variables = null, int $code = 0, bool $protected = false)
	{
		$config = include PATH_ROOT . '/config.php';
		$debug = $config['debug'] ?? false;
		$logStack = $config['log_stacktrace'] ?? true;
		$stackLength = $config['stacktrace_length'] ?? 25;

		if ($debug && $logStack) {
			error_log('An exception has been thrown. Stacktrace:');
			error_log($this->stackTrace($stackLength, $protected));
		}

		// Translate the exception
		$message = __trans($message, $variables);

		// Pass the message to the parent
		parent::__construct($message, $code);
	}


	/**
	 * Big thank you to jhurliman and jambroseclarke on Stack Overflow for this backtrace formatter.
	 * We have slightly modified it for our purposes
	 * https://stackoverflow.com/a/32365961
	 */
	private function stackTrace($Length = 25, $protected = false)
	{
		$stack = debug_backtrace($Length);
		$output = '';

		$stackLen = count($stack);
		for ($i = 1; $i < $stackLen; $i++) {
			$entry = $stack[$i];

			$func = $entry['function'] . '(';
			if (isset($entry['args'])) {
				$argsLen = count($entry['args']);
				for ($j = 0; $j < $argsLen; $j++) {
					$my_entry = $entry['args'][$j];
					if ($protected) {
						$func .= "***";
					} else if (is_string($my_entry)) {
						$func .= $my_entry;
					}
					if ($j < $argsLen - 1) $func .= ', ';
				}
			}
			$func .= ')';

			$entry_file = 'NO_FILE';
			if (array_key_exists('file', $entry)) {
				$entry_file = str_replace(PATH_ROOT, '', $entry['file']);
			}
			$entry_line = 'NO_LINE';
			if (array_key_exists('line', $entry)) {
				$entry_line = $entry['line'];
			}
			$output .= $entry_file . ':' . $entry_line . ' - ' . $func . PHP_EOL;
		}
		return $output;
	}
}
