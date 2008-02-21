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
error_reporting(E_ALL);
ob_implicit_flush();

define('IRCC_VER', 'ircc-0.01');
define('BUFFER_STATUS', 0);
define('BUFFER_CURRENT', -1);

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

	// Little hack to stop PHP dying if it encounters a null param.. dirty, but better than hacking fixes in for this 
	// everywhere. Such are the pitfalls of E_NOTICE.
	//for ($i = 0; $i < 40; $i++)
	//{
	//	if (!isset($aRet[$i]))
	//		$aRet[$i] = "";
	//}
	//
	// Back this out. It causes problems with IRC processing (picking last param for NOTICE etc), and isn't optimal.
	// This will need more thought.

	return $aRet;
}

class torc
{
	var $irc, $output;
	var $username, $nick;

	function shutdown($msg = "")
	{
		$this->output->quit();
		$this->irc->squit($msg);
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
				$cmd = $ex[0];
				$msg = implode($ex, " ");
				$msgf = implode(array_slice($ex, 1), " "); // same as $msg, except without the command prefix.

				switch(strtolower($cmd))
				{
					case 'w':
						if ($this->output->IsBuffer($ex[1]))
							$this->output->DrawBuffer($ex[1]);
						else
							$this->output->Output(BUFFER_CURRENT, "That is not a valid window.");
						break;
					case 'q':
					case 'query':
						// XXX find buffer by name and go to it
						$this->output->AddBuffer($ex[1]);
						break;
					case 'server':
					case 'connect':
						if(!empty($ex[2]))
							$port = (int)$ex[2];
						else
							$port = 6667;

						if (empty($ex[3]))
							$ex[3] = "";

						$this->irc->connect($ex[1], $port, $ex[3], $this->username, "torc", "server", "torc - torx irc user", $this->nick);
						break;
					case 'quit':
						$this->shutdown($msgf);
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
						$this->output->Output(BUFFER_STATUS, $this->irc->getuser().trim($input)."\n");
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
						$this->output->Output(BUFFER_STATUS, "setting ".$ex[1]." to ".(int)trim($msg)."\n");
						$this->irc->set($ex[1], (int)trim($msg));
						break;
					case 'privmsg':
					case 'msg':
						$this->irc->sprivmsg($ex[1], $msg);
						break;
					default:
						$this->output->Output(BUFFER_STATUS, 'warning: unknow command ['.$cmd."], sending raw to server");
						$this->irc->sendline($cmd." ".$msgf);
						break;
				}
			}
			else
			{
				$this->irc->say($input);
			}
		}
	}

	function torc($server, $mode, $nick, $ssl, $port)
	{
		$sStatus = "Status";
		$this->output = new ncurse($this);
		$this->output->SetDisplayVar("nick", ""); // Bit of a hack. Stops the AddBuffer below exploding things.
		$this->output->AddBuffer($sStatus); // Create status buffer. ALWAYS at position 0.
		$this->irc = new irc($this);

		$this->output->Output(BUFFER_STATUS, IRCC_VER . " - irc client\n");

		$this->username = 'torc';
		if(!empty($_ENV['LOGNAME']))
		{
			$this->username = $_ENV['LOGNAME'];
		}
		elseif(!empty($_ENV['USER']))
		{
			$this->username = $_ENV['USER'];
		}

		$this->nick = $this->username;
		$this->output->Output(BUFFER_STATUS, "use the /SERVER command to connect to a server");
		$this->output->Output(BUFFER_STATUS, "/QUIT to quit\n\n\n");

		while (true)
		{
			// XXX we shouldn't have to reset these constantly..
			$this->output->SetDisplayVar("nick", $this->irc->usernick);

			// poll() may hang a while until activity on stdin or IRC
			$this->poll();

			// Update time display.
			$this->output->setuserinput();
		}
	}
	
	function usage()
	{
		global $torc_ver;
		$this->shutdown($torc_ver."

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
		file_put_contents("error.log", $errstr . ": " . $errfile . ":" . $errline);
		die();
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
		default:
			torc::usage();
		break;
	}
}

new torc($server, $mode, $nick, $ssl, $port);

?>
