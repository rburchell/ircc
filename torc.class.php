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

set_error_handler(array("torc", "ErrorHandler"));
date_default_timezone_set("UTC"); // XXX guess the tz?
set_time_limit(0);
error_reporting(0);
ob_implicit_flush();

define('IRCC_VER', 'ircc-0.01');

include "ncurse.class.php";
include "irc.class.php";

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

	function torc($server, $mode, $nick, $ssl, $port)
	{	
		switch($mode)
		{
			case 'ncurses':
				$this->output = new ncurse();
				break;
			default:
				echo "Unsupported.\n";
				$this->usage();
			break;
		}


		$this->irc = new irc();

		$this->output->addtoircout(IRCC_VER . " - irc client\n");

		$username = 'torc';
		if(!empty($_ENV['LOGNAME']))
		{
			$username = $_ENV['LOGNAME'];
		}
		elseif(!empty($_ENV['USER']))
		{
			$username = $_ENV['USER'];
		}

		if($nick == -2)
			$nick = $username;

		if($server != -2)
		{
			$this->output->addtoircout('connecting to ['.$server.'], port '.$port.', ssl mode: '.(int)$ssl."\n");
			$this->irc->connect($server, $port, $ssl, $username, "torc", "server", "torc - torx irc user", $nick);
		}
		else
		{
			$this->output->addtoircout("use the /SERVER command to connect to a server\n");
			$this->output->addtoircout("/QUIT to quit\n\n\n");
		}

		$updct = 100;
		$prct = 0;

		while(true)
		{
			if (($input = $this->output->getuserinput()))
			{
				//we have a line of input
				if(substr($input, 0, 1) == "/")
				{
					// Old line parser.
/*
					$ex = explode(" ", $input);
					$cmd = substr($ex[0], 1);
					$msg = $ex;
					$msg[0] = "";
					$msg[1] = "";
					$msg = trim(implode(" ", $msg));
					$msgh = $ex;
					$msgh[0] = "";
					$msgh[1] = "";
					$msgh[2] = "";
					$msgh = trim(implode(" ", $msgh));
					$msgf = $ex;
					$msgf[0] = "";
					$msgf = trim(implode(" ", $msgf));
					$ex[count($ex)-1] = trim($ex[count($ex)-1]);

					$this->output->addtoircout($msg . " | " . $msgf . "\n");
*/
//					$ex = array_slice(parse_line($input), 1); // the array_slice is to keep backwards compatibility.


					// Tear off /
					$input = substr($input, 1);
					// This is all ugly, really. Backwards compatibility.
					$ex = parse_line($input);
					$cmd = $ex[0];
					$msg = implode($ex, " ");
					$msgf = implode(array_slice($ex, 1), " "); // same as $msg, except without the command prefix.

					switch(strtolower($cmd))
					{
						case 'server':
							if(!empty($ex[2]))
								$port = (int)$ex[2];
							else
								$port = 6667;

							if (empty($ex[3]))
								$ex[3] = "";

							$this->irc->connect($ex[1], $port, $ex[3], $username, "torc", "server", "torc - torx irc user", $nick);
							break;
						case 'connect':
							if(!empty($ex[2]))
								$port = (int)$ex[2];
							else
								$port = 6667;

							$this->irc->connect($ex[1], $port, $ex[3], $username, "torc", "server", "torc - torx irc user", $nick);
							break;
						case 'quit':
							$this->irc->squit($msgf);
							$this->output->quit();
							die();
							break;
						case 'join':
							$this->irc->sjoin($ex[1]);
							break;
						case 'nick':
							$this->irc->snick($ex[1]);
							break;
						case 'part':
							$this->irc->spart($ex[1], $ex[2]);
							break;
						case 'oper':
							$this->irc->soper($ex[1], $ex[2]);
							break;
						case 'mode':
							$this->irc->smode($ex[1], $ex[2]);
							break;
						case 'topic':
							$this->irc->stopic($ex[1], $msg);
							break;
						case 'notice':
							$this->irc->snotice($ex[1], $msg);
							break;
						case 'names':
							$this->irc->snames($ex[1]);
							break;
						case 'kick':
							$this->irc->skick($ex[1], $ex[2], $msgh);
							break;
						case 'op':
							$this->irc->smode($ex[1], "+o ".$ex[2]);
							break;
						case 'deop':
							$this->irc->smode($ex[1], "-o ".$ex[2]);
							break;
						case 'ver':
							$this->irc->sversion($ex[1]);
							break;
						case 'me':
							$this->irc->saction($msgf);
							break;
						case 'quote':
						case 'raw':
							$this->irc->sendline($msgf);
							break;
						case 'say':
							$this->output->addtoircout($this->irc->getuser().trim($input)."\n");
							$this->irc->say($input);
							break;
						case 'exec':
							if($ex[1] == '-o')
							{
								$exout = explode("\n", trim(`$msg`));
								foreach($exout as $sayout)
								{
									$this->irc->say($sayout);
								}
							}
							else
							{
								$this->irc->addout(trim(`$msgf`));
							}
							break;
						case 'setb':
							$this->output->addtoircout("setting ".$ex[1]." to ".(int)trim($msg)."\n");
							$this->irc->set($ex[1], (int)trim($msg));
							break;

						case 'privmsg':
						case 'msg':
							$this->irc->sprivmsg($ex[1], $msg);
							break;
						default:
							$this->output->addtoircout('warning: unknow command ['.$cmd."], sending raw to server\n");
							$this->irc->sendline($cmd." ".$msgf);
							break;
					}
				}
				else
				{
					$this->irc->say($input);
				}
			}

			$updct++;
			if($updct>30)
			{
				// XXX we shouldn't have to reset these constantly..
				$this->output->SetDisplayVar("nick", $this->irc->usernick);
				$this->output->SetDisplayVar("window", $this->irc->chan);
				$this->output->setuserinput();
				$updct = 0;
			}

			$prct++;
			if($prct>3)
			{
				if($this->irc->sp)
					$this->irc->procline();

				$out = explode("\n", $this->irc->getout());
				foreach($out as $send)
				{
					$t = trim($send);
					if(!empty($t))
						$this->output->addtoircout($send."\n");
					unset($t);
				}
				$prct = 0;
			}
		}
	}
	
	function usage()
	{
		global $torc_ver;
		die($torc_ver."

usage: ircc [options]
  available options:
    -c kork        connect to server kork
    -p 123             port to connect to
    -s                 enable ssl connection
    -n torx            use torx as nick

");
	}

	// error handler function
	function ErrorHandler($errno, $errstr, $errfile, $errline)
	{
		echo $errstr . ": " . $errfile . ":" . $errline;
		die();
		switch ($errno)
		{
			case E_USER_ERROR:
				$this->output->addtoircout("ERROR: [$errno] $errstr<br />\n
											Fatal error on line $errline in file $errfile\n
											PHP " . PHP_VERSION . " (" . PHP_OS . ")\n
											Aborting...\n");
				exit(1);
				break;
			case E_USER_WARNING:
				$this->output->addtoircout("WARNING: [$errno] $errstr\n");
				break;
			case E_USER_NOTICE:
				$this->output->addtoircout("NOTICE: [$errno] $errstr\n");
				break;
			default:
				$this->output->addtoircout("Unknown error type: [$errno] $errstr\n");
				break;
		}

		/* Don't execute PHP internal error handler */
		return true;
	}
}

//argument switching

$mode = 'ncurses';
$nick = -2;
$ssl = false;
$port = 6667;
$server = -2;

$argv = $_SERVER['argv'];
$argc = $_SERVER['argc'];

for($x = 1; $x < $argc; $x++)
{
	switch ($argv[$x])
	{
		case '-n':
			$nick = $argv[$x+1];
			$x++;
			break;
		case '-c':
			$server = $argv[$x+1];
			$x++;
			break;
		case '-s':
			$ssl = true;
			break;
		case '-p':
			$port = (int)$argv[$x+1];
			$x++;
			break;
		case '-m':
			$mode = $argv[$x+1];
			$x++;
			break;
		default:
			torc::usage();
		break;
	}


}

new torc($server, $mode, $nick, $ssl, $port);

?>
