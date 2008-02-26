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

class Client
{
	public $output;			/* ncurses stuff */
	public $Config;			/* Instance of configuration class. */

	private $aServers = array();		/* Array of IRC server connections. */
	public $IRC;			/* the "active" IRC connection -- WARNING: unlike torc/early ircc, this MAY be unset! */

	private $nick;			/* stores nickname, passed on new connection creation */
	private $username;		/* stores username, passed on new connection creation */
	public function __toString()
	{
		return "Client";
	}
	public function AddConnection()
	{
		$oConnection = new irc($this);
		$this->aServers[] = $oConnection;
		$this->SetActiveConnection($oConnection);
	}

	public function SetActiveConnection(&$oConnection)
	{
		$this->IRC = $oConnection;
	}

	// Retrieves the next server connection available, or NULL if none available.
	public function &GetNextConnection()
	{
		file_put_contents("getserv", "Finding a server connection.\n", FILE_APPEND);

		if (count($this->aServers) == 0)
		{
			$oStupidPHP = null;
			return $oStupidPHP;
		}

		if ($this->IRC == null)
		{
			file_put_contents("getserv", "Finding a server connection. this->irc is NULL, returning first\n", FILE_APPEND);
			if (isset($this->aServers[0]))
				return $this->aServers[0];
		}
		else
		{
			if (count($this->aServers) > 1)
			{
				$bFound = false;

file_put_contents("getserv", "this->irc is " . $this->IRC->sServerName . "\n", FILE_APPEND);
				foreach ($this->aServers as $oServer)
				{
file_put_contents("getserv", "Examining " . $oServer->sServerName . "\n", FILE_APPEND);
					// XXX this should really compare object, not name...
					if ($oServer === $this->IRC)
					{
file_put_contents("getserv", "true, returning next.\n", FILE_APPEND);
						$bFound = true;
					}
					else
					{
						if ($bFound == true)
file_put_contents("getserv", "and now returning " . $oServer->sServerName . "\n", FILE_APPEND);
							return $oServer;
					}
				}

				// If we get here it was the last server in the array
				foreach ($this->aServers as $oServer)
				{
file_put_contents("getserv", "found the last, returning the first: " . $oServer->sServerName . "\n", FILE_APPEND);
					return $oServer; // so return the first.
				}
			}
			else
			{
file_put_contents("getserv", "Meh, only one conn.\n", FILE_APPEND);
				return $this->aServers[0];
			}
		}
	}

	public function DeleteConnection(&$oConnection)
	{
		foreach ($this->aServers as $iIndex => $oServer)
		{
			if ($oServer === $oConnection)
			{
				$oServer = null;
				unset($this->aServers[$iIndex]);
				break;
			}
		}

		if ($this->IRC === $oConnection)
		{
			$this->IRC = $this->GetNextConnection();
		}
	}

	/*
	 * Initiates a shutdown of the client, sends QUIT to all connections, then shuts down ncurses, etc.
	 */
	public function shutdown($msg = "")
	{
		foreach ($this->aServers as $oServer)
		{
			$oServer->squit($msg);
		}

		$this->output = null;
		die();
	}

	/*
	 * Polls all file descriptors for activity and hits appropriate callbacks if activity has been detected.
	 */
	public function Poll()
	{
		/*
		 * CAREFUL:
		 *  Optimisers, note! This is something that catches a lot of people out.
		 *  select() syscall modifies the arrays it is passed, so we MUST regenerate the array for each call.
		 *  NOT doing this is suicide, and will give you hard to track down bugs/problems etc. (fds "disappearing" etc).
		 */
		$aRead = $aWrite = $aExcept = array(); // XXX we should eventually poll for write too.

		$aRead[] = $this->output->stdin;

		foreach ($this->aServers as $oServer)
		{
			if ($oServer->sp)
			{
				$aRead[] = $oServer->sp;
			}
		}

		/*
		 * It's annoying to have to @suppress warnings on stream_select(), but PHP raises E_NOTICE if select
		 * is interrupted by a signal etc, and I have no way of trapping that.
		 *
		 * Except, that in their infinite wisdom, not only have they created a nonsensical error on this condition
		 * (think SIGWINCH in a console application, you idiots!), but it also is un-suppressable!
		 *
		 * The error handler will suppress it instead, but YUCK.
		 */
		$iStreams = stream_select($aRead, $aWrite, $aExcept, 1);

		/*
		 * If no streams have activity, there's no point in checking what callbacks to hit. Duh.
		 */
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
				foreach ($this->aServers as $oServer)
				{
					if ($oServer->sp == $iSocket)
						$oServer->procline();
				}
			}
		}
	}

	/*
	 * Callback to read and process stdin whenever there is input available.
	 * XXX eventually this (and all other sockets) should be wrapped in a class with methods for callbacks.
	 */
	public function callback_process_stdin()
	{
		if (($input = $this->output->GetUserInput()))
		{
			//we have a line of input
			if(substr($input, 0, 1) == "/")
			{
				// Tear off /
				$input = substr($input, 1);
				// This is all ugly, really. Backwards compatibility.
				$ex = Utils::ParseLine($input);
				$cmd = strtolower($ex[0]);
				$msg = implode($ex, " ");
				$msgf = implode(array_slice($ex, 1), " "); // same as $msg, except without the command prefix.

				// XXX this doesn't allow for aliases yet.
				if (file_exists("./src/commands/" . $cmd . ".command.inc.php"))
					include("commands/" . $cmd . ".command.inc.php");
				else
				{
					if ($this->IRC)
						$this->IRC->sendline($cmd." ".$msgf);
				}
			}
			else
			{
				if ($this->IRC)
					$this->IRC->say($input);
			}
		}
	}

	/*
	 * ..it's a bird, ..it's a plane..
	 * .. no, moron, it's a constructor.
	 */
	public function __construct()
	{
		$oNull = null; // WHY can I not pass a reference to NULL for christ's sake
		$sStatus = "Status";
		$this->output = new ncurse($this);
		$this->output->SetDisplayVar("nick", ""); // Bit of a hack. Stops the AddBuffer below exploding things.
		$this->output->SetDisplayVar("scrolled", false); // XXX move these to output constructor
		$this->output->AddBuffer($oNull, $sStatus); // Create status buffer. ALWAYS at position 0.
		$this->Config = new Configuration($this);

		$this->output->Output(BUFFER_STATUS, IRCC_VER . " - irc client");

		$sMotd = file_get_contents("motd.txt");
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

		// Process autoconnects
		$aAutoconnect = $this->Config->autoconnect_servers;
		if ($aAutoconnect)
		{
			foreach ($aAutoconnect as $aServer)
			{
				$this->AddConnection();
				$this->output->Output(BUFFER_STATUS, "autoconnect: connecting to " . $aServer['name']); 
				$this->IRC->connect($aServer['name'], 6667, "", $this->username, "torc", "server", "torc - torx irc user", $this->nick);
			}
		}
	}

	public function Run()
	{
		while (true)
		{
			// poll() may hang a while until activity on stdin or IRC
			$this->Poll();

			// Update time display.
			$this->output->setuserinput();

			// Pop an error off and display to the user, if there is one.
			// Only display one to avoid flooding.
			global $aErrors;
			if (($aErr = array_pop($aErrors)))
			{
				$this->output->Output(BUFFER_CURRENT, $aErr['message']);
				foreach ($aErr['backtrace'] as $sMsg)
					$this->output->Output(BUFFER_CURRENT, $sMsg);
			}
		}
	}
}

?>
