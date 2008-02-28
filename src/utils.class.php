<?php
/*
 * ircc - a handy, portable console irc client
 *
 * Copyright (C) 2008 Robin Burchell <w00t@inspircd.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 */

/*
 * This is a kind of ugly hack.
 * Error handler won't have an instance of the torc class (or might not), so we pop errors into here from our handler.
 * Every second (to prevent flooding the fuck out of a user), an error will be popped off the array and displayed in the current buffer.
 */
$aErrors = array();

/*
 * Provides various miscellaneous routines and functions.
 */
class Utils
{
	/*
	 * Returns an array of:
	 *  prefix
	 *  command
	 *  params
	 * This obeys IRC line format, i.e.
	 *  :w00t PRIVMSG foo :bar moo cow
	 * gives
	 * [0]: w00t
	 * [1]: PRIVMSG
	 * [2]: foo
	 * [3]: bar moo cow
	 *
	 * On the other hand,
	 * msg moo cow
	 * will return:
	 * [0]: ""
	 * [1]: msg
	 * [2]: moo
	 * [3]: cow
	 *
	 * Passing malformed lines isn't a good idea.
	 */
	static public function ParseLine(&$sLine)
	{
		$i = 0;				// where in the array we're up to
		$j = 0;				// which pos in the original array should be treated as a command

		$aRet = array();
		$aParm = explode(" ", $sLine);

		if ($aParm[0][0] == ":")
		{
			// We have a prefix.
			$aRet[0] = substr($aParm[0], 1);
			$i = 1;
			$j = 1;
		}
		else
		{
			// No prefix.
			$aRet[0] = "";
		}

		for (; $i < count($aParm); $i++)
		{
			if ($i == $j)
				$aParm[$i] = strtoupper($aParm[$i]); // uppercase commands

			if ($aParm[$i][0] == ":")
			{
				// Strip :
				$aParm[$i] = substr($aParm[$i], 1);

				// Merge all further params
				$aRet[$i] = implode(" ", array_slice($aParm, $i));
				break; // and ignore everything else.
			}
			else
			{
				// It's a single param.
				$aRet[$i] = $aParm[$i];
			}
		}

		return $aRet;
	}

	// error handler function
	static public function ErrorHandler($errno, $errstr, $errfile, $errline)
	{
		file_put_contents("error.log", $errstr . "\n", FILE_APPEND);

		// YUCK. See the main socket loop for why this is necessary.
		$aMsg = explode(" ", $errstr);
		if ($aMsg[0] != "stream_select():")
		{
			global $aErrors;
			$aError['message'] = "ERROR: " . $errno . ": " . $errstr . " in " . $errfile . ":" . $errline;
			$aBacktrace = debug_backtrace();
			foreach ($aBacktrace as $iStack => $aBT)
			{
				if ($iStack == 0)
					continue; // don't care about the call to error handler
				$sMsg = "#" . ($iStack - 1) . ": " . $aBT['file'] . ":" . $aBT['line'] . " - ";
				$sMsg .= isset($aBT['object']) ? get_class($aBT['object']) : "";
				$sMsg .=  "::" . $aBT['function'] . "(" . implode(", ", $aBT['args']) . ")";
				$aError['backtrace'][] = $sMsg;
			}
			$aErrors[] = $aError;
		}
	}

	// Split the line into smaller parts so it fits into the window, returns array of lines
	static public function SplitLine($line, $columns)
	{
		$padding = 6;
		$pad_str = str_repeat(' ', $padding);

		// If it fits already, just return it as is
		if (strlen($line) <= $columns)
			return array($line);

		$ret = array();

		// First line is $columns long, break at word boundary
		$tmp = wordwrap($line, $columns, "\n", true);
		$tmp = explode("\n", $tmp);

		// Now get this first line
		$ret[] = array_shift($tmp);

		// And get the stuff which didn't fit on this first line
		$line = $pad_str.implode(' ', $tmp);

		// Every other line is padded by $padding spaces to the right
		$split = wordwrap($line, $columns - $padding, "\n".$pad_str, true);
		$split = explode("\n", $split);
		return array_merge($ret, $split);
	}
}
?>
