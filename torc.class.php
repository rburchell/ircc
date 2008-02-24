#!/usr/local/bin/php
<?php
/*
 * ircc - a handy, portable console irc client
 *
 * Copyright (C) 2008 Robin Burchell <w00t@inspircd.org>
 * Copyright (C) 2003 rainman <rainman@darkwired.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

set_error_handler("ErrorHandler");
date_default_timezone_set("UTC"); // XXX guess the tz?
set_time_limit(0);
error_reporting(E_ALL);
ob_implicit_flush();

define('IRCC_VER', 'ircc-0.01');
define('BUFFER_STATUS', 0);
define('BUFFER_CURRENT', -1);

require("ncurse.class.php");
require("irc.class.php");

/*
 * This is a kind of ugly hack.
 * Error handler won't have an instance of the torc class (or might not), so we pop errors into here from our handler.
 * Every second (to prevent flooding the fuck out of a user), an error will be popped off the array and displayed in the current buffer.
 */
$aErrors = array();

// error handler function
function ErrorHandler($errno, $errstr, $errfile, $errline)
{
	global $aErrors;
	$aErrors[] = "ERROR: " . $errno . ": " . $errstr . " in " . $errfile . ":" . $errline;
}

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
function parse_line($sLine)
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

class torc
{
	var $irc, $output;
	var $username, $nick;

	function shutdown($msg = "")
	{
		$this->irc->squit($msg);
		$this->output->quit();
		die();
	}

	function poll()
	{
		// Polls this->output->stdin and all IRC sockets for activity, initiating callbacks if necessary.
		$aRead = array();
		$aWrite = $aExcept = array(); // XXX we should eventually poll for write too.

		$aRead[] = $this->output->stdin;

		if ($this->irc->sp)
			$aRead[] = $this->irc->sp;

		// It's annoying to have to @suppress warnings on stream_select(), but PHP raises E_NOTICE if select
		// is interrupted by a signal etc, and I have no way of trapping that.
		$iStreams = @stream_select($aRead, $aWrite, $aExcept, 1);

		if ($iStreams == 0)
			return;

		foreach ($aRead as $iSocket)
		{
			if ($iSocket == $this->output->stdin)
			{
				// stdin ready, hit the callback
				$this->callback_process_stdin();
			}
			else
			{
				// irc callback
				$this->irc->procline();
			}
		}
	}

	// Callback which is hit whenever stdin is ready for read.
	function callback_process_stdin()
	{
		if (($input = $this->output->getuserinput()))
		{
			//we have a line of input
			if(substr($input, 0, 1) == "/")
			{
				// Tear off /
				$input = substr($input, 1);
				// This is all ugly, really. Backwards compatibility.
				$ex = parse_line($input);
				$cmd = strtolower($ex[0]);
				$msg = implode($ex, " ");
				$msgf = implode(array_slice($ex, 1), " "); // same as $msg, except without the command prefix.

				// XXX this doesn't allow for aliases yet.
				if (file_exists("commands/" . $cmd . ".command.inc.php"))
					include("commands/" . $cmd . ".command.inc.php");
				else
					$this->irc->sendline($cmd." ".$msgf);
			}
			else
			{
				$this->irc->say($input);
			}
		}
	}

	function __construct()
	{
		$sStatus = "Status";
		$this->output = new ncurse($this);
		$this->output->SetDisplayVar("nick", ""); // Bit of a hack. Stops the AddBuffer below exploding things.
		$this->output->AddBuffer($sStatus); // Create status buffer. ALWAYS at position 0.
		$this->irc = new irc($this);

		$this->output->Output(BUFFER_STATUS, IRCC_VER . " - irc client\n");

		$sMotd = file_get_contents("ircc.motd");
		$aLines = explode("\n", $sMotd);
		foreach ($aLines as $sLine)
		{
			$this->output->Output(BUFFER_STATUS, $sLine);
		}

		$this->username = 'ircc';
		if(!empty($_ENV['LOGNAME']))
		{
			$this->username = $_ENV['LOGNAME'];
		}
		elseif(!empty($_ENV['USER']))
		{
			$this->username = $_ENV['USER'];
		}

		$this->nick = $this->username;

		while (true)
		{
			// XXX we shouldn't have to reset these constantly..
			$this->output->SetDisplayVar("nick", $this->irc->usernick);

			// poll() may hang a while until activity on stdin or IRC
			$this->poll();

			// Update time display.
			$this->output->setuserinput();

			// Pop an error off and display to the user, if there is one.
			// Only display one to avoid flooding.
			global $aErrors;
			if (($sMsg = array_pop($aErrors)))
				$this->output->Output(BUFFER_CURRENT, $sMsg);
		}
	}
}

$oTorc = new torc();

?>
