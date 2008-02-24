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

class irc
{
	public $sp; 				// the socket file pointer
	public $output;				// everything that is ready to be sent to whoever called us
	public $usernick;			// the nick of the user

	public $line; 				// the line we read from the irc input
	public $sender;				// the nick that sent this message
						// will be messed up if it's not a normal message but then it's not used
	public $ex;				// the explode(' ', $line), we use this a lot so it's an attribute
	public $msg;				// this is everything after the : (reread each line)
	public $autonick;			// if this is true we auto change nicks

	public $chans = array();		// A lookup of channel name to buffer id
	public $sCurrentTarget;			// which window to send privmsg etc to (set by class torc)

	public $torc;

	function irc(&$torc)
	{
		$this->torc = $torc;
	}

	function connect(
		$server,				// (string) the server to connect to
		$port,					// (int) the port to connect to
		$ssl,					// (bool) wether this connection uses ssl
		$username,				// (string) username send in USER command
		$hostname,				// (string) hostname send in USER command
		$servername,				// (string) servername send in USER command
		$realname,				// (string) realname send in USER command
		$nick,					// (string) nick to specify in connect NICK command
		$pass = ''				// (string) optional password for server
	)
	{
		$this->autonick = 1;
		
		if($ssl)
		{
			// if we use ssl we must prefix our connect host with ssl://
			$cntserver = 'ssl://'.$server;
		}
		else
		{
			$cntserver = $server;
		}

		$this->sp = fsockopen($cntserver, $port, $errno, $errstr, 10);

		if(!$this->sp){
			$this->torc->output->Output(BUFFER_CURRENT, 'Connect error: '.$errno.' - '.$errstr);
			return 1;		// in case of error: output the error and exit this function
		}

		//echo socket_set_blocking($this->sp, 0);

		// because PASS, NICK and USER commands are also required for connect
		// we'll run them here
		// USER and PASS aren't changed later, so we send raw data to socket here
		// for NICK we use the setnick() function

		if(!empty($pass))
			$this->sendline('PASS '.$pass);
		$this->sendline('NICK ' . $nick);
		$this->sendline('USER '.$username.' '.$hostname.' '.$servername.' :'.$realname);

		// well, this should be it
		// processing of the data we got back will not be done here
		// incorrect USER params are ignored
		// incorect PASS params will be seen and will kill the connection
		// incorect NICK params will be passed to nick()
		// a PING will just be processed by pingreply()
		// (we hope)

		return 0;
	}

	/*
	 * Careful!
	 *  This blocks if called when there is no output to read - of course, this is a bad thing.
	 *  This should only be called from the main polling loop really.
	 */
	function procline ()
	{
		$this->line .= fgets($this->sp);

		if(substr($this->line, -1) != "\n")
		{
			return 1;
		}

		// This is all ugly, really. Backwards compatibility.
		$this->line = trim($this->line);
		$this->ex = Utils::ParseLine($this->line);
		$this->line = "";
		$this->msg = $this->ex[count($this->ex) - 1];
		$this->sender = explode("!", $this->ex[0]);
		$this->sender = $this->sender[0];


		if($this->ex[1] == 'NOTICE')
		{
			$this->procnotice();
		}
		else if($this->ex[1] == 'PRIVMSG')
		{
			$this->procprivmsg();
		}
		else if(((int)$this->ex[1]) != 0)
		{
			// detects > :Darkwired 375 rian :- Darkwired Message of the Day -
			// and messages with other numbers
			$this->procnumeric();
		}
		else if($this->ex[1] == 'MODE')
		{
			if($this->ex[2] == $this->usernick)
			{
				// detects > :rian MODE rian :+i
				$this->procusermode();
			}
			else
			{
				// detects > :rainman__!~rainman@127.0.0.1 MODE #test +o rian
				// and > :Darkwired MODE #test2 +nt
				$this->procchanmode();
			}
		}
		else if($this->ex[1] == 'JOIN')
			$this->procchanjoin();
		else if($this->ex[1] == 'PART')
			$this->procchanpart();
		else if($this->ex[1] == 'KICK')
			$this->procchankick();
		else if($this->ex[1] == 'QUIT')
			$this->procquit();
		else if($this->ex[1] == 'NICK')
			$this->procnick();
		else if($this->ex[0] == 'PING')
			$this->procping();
		else if($this->ex[0] == 'PONG')
			$this->procpong();
		else if($this->ex[1] == 'TOPIC')
			$this->proctopic();

		return 0;
	}

	function SetUserNick($sNick)
	{
		$this->usernick = $sNick;
		$this->torc->output->SetDisplayVar("nick", $sNick);
	}

	function skick($chan, $victim, $reason = "Expelled")
	{
		$this->sendline('KICK '.trim($chan).' '.trim($victim).' :'.trim($target));
	}

	function smode($target, $mode)
	{
		$this->sendline('MODE '.trim($target).' '.trim($mode));
	}

	function spart($chan, $reason = 'Parting')
	{
		$this->sendline('PART '.trim($chan).' :'.trim($reason));
		$this->torc->output->DeleteBuffer($this->chans[$chan]);
		unset($this->chans[$chan]);
	}

	function squit($reason)
	{
		$reasont = trim($reason);
		if(empty($reason))
			$reason = 'Leaving';
		$this->sendline('QUIT :'.trim($reason));
	}

	function sprivmsg($target, $msg, $disp = true)
	{
		$this->sendline('PRIVMSG '.trim($target).' :'.trim($msg));
		if($disp)
			$this->torc->output->Output(BUFFER_CURRENT, 'msg '.$target.' '.$msg);
	}

	function snotice($target, $msg)
	{
		$this->sendline('NOTICE '.trim($target).' :'.trim($msg));
		$this->torc->output->Output(BUFFER_CURRENT, 'notice -'.$target.'- '.$msg);
	}

	function stopic($target, $new = '')
	{
		if(trim($new) != '')
		{
			$this->sendline('TOPIC '.$target.' :'.$new);
		}
		else
		{
			$this->sendline('TOPIC '.$target);
		}
	}

	function say($msg){
		$this->sprivmsg($this->sCurrentTarget, trim($msg), false);
		$this->torc->output->Output(BUFFER_CURRENT, '<'.$this->usernick.'> '.$msg);
	}

	function saction($msg){
		$this->sprivmsg($this->sCurrentTarget, chr(1).'ACTION '.trim($msg).chr(1),false);
		$this->torc->output->Output(BUFFER_CURRENT, '*'.$this->usernick . ' '.trim($msg).'*');
	}


	function GetBufferID($sTarget)
	{
		if (isset($this->chans[$sTarget]))
			return $this->chans[$sTarget];

		return -1;
	}

	/*-----------------------------------
	BELOW FUNCTIONS FOR INTERNAL USE ONLY
	-----------------------------------*/
	function proctopic()
	{
		$this->torc->output->Output($this->GetBufferID($this->ex[2]), $this->sender.' set topic for '.$this->ex[2].' to '.$this->msg);
	}

	function procpong()
	{
	}

	function procping()
	{
		$this->sendline('PONG '.$this->ex[1]);
	}

	function procnick()
	{
		// We want to update our nick IF: it's us changing nick, or if we haven't yet set a nick.
		if ($this->usernick == $this->sender || empty($this->usernick))
			$this->SetUserNick($this->ex[2]);

		// XXX we need to output this on all buffers, or something.
		$this->torc->output->Output(BUFFER_CURRENT, $this->sender.' is now known as '.$this->ex[2]);
	}

	function procquit()
	{
		$this->torc->output->Output(BUFFER_CURRENT, 'Quit: '.$this->sender.' ['.$this->msg.']');
	}

	function procchankick()
	{
		$this->torc->output->Output($this->GetBufferID($this->ex[2]), $this->ex[3].' was kicked off '.$this->ex[2].' by '.$this->sender.': '.$this->msg);
	}

	function procchanpart()
	{
		$this->torc->output->Output($this->GetBufferID($this->ex[2]), $this->sender.' has left channel '.$this->ex[2].': '.$this->msg);
	}

	function procchanjoin()
	{
		$this->ex[2] = trim($this->ex[2]);

		$iId = $this->GetBufferID($this->ex[2]);

		// If the channel hasn't been created before, do so now.
		if ($iId == -1)
		{
			$this->torc->output->Output(BUFFER_CURRENT, "Creating new buffer as current one doesn't exist for " . $this->ex[2]);
			$iId = $this->torc->output->AddBuffer($this->ex[2]);
			$this->chans[$this->ex[2]] = $iId;
			$this->torc->output->DrawBuffer($iId);
			$this->torc->output->Output(BUFFER_CURRENT, "Created a new buffer, ID is " . $this->GetBufferID($this->ex[2]));
		}

		$this->torc->output->Output($this->GetBufferID($this->ex[2]), $this->sender. ' has joined '.substr($this->ex[2], 1));
	}

	function procchanmode()
	{
		$sOut = 'chanmode ' . $this->ex[2] . ' ' . $this->ex[3];
		if (isset($this->ex[4]))
			$sOut .= ' ' . $this->ex[4];
		$sOut .= ' by '.$this->sender;

		$this->torc->output->Output($this->GetBufferID($this->ex[2]), $sOut);
	}

	function procusermode(){
		$this->torc->output->Output(BUFFER_CURRENT, 'usermode '.$this->ex[2].' '.$this->msg.' by '.$this->sender);
	}

	function procnumeric()
	{
		//we can't use $this->msg here, it can't handle :Darkwired 254 rain- 2 :channels formed
		$ar = $this->ex;
		$ar[0] = '';
		$ar[1] = '';
		$ar[2] = '';
		$mg = implode(' ', $ar);
		$mg = trim($mg);

		if($this->autonick)
		{
			$this->autonick++;

			if($this->autonick > 5)
				$this->autonick = false;

			if($this->ex[1] == 437 || $this->ex[1] == 433)
				$this->sendline("NICK " . $this->ex[3].'_');
		}

		if ($this->ex[1] == "001")
			$this->SetUserNick($this->ex[2]);

		if(substr($mg, 0, 1) == ':')
			$mg = substr($mg, 1);

		$this->torc->output->Output(BUFFER_CURRENT, '-'.$this->sender . " (" . $this->ex[1] . ")" . '- '.$mg);
	}

	function procprivmsg()
	{
		if ($this->ex[2][0] == "#")
		{
			file_put_contents("privlog", "ch is " . $this->ex[2] . " and buf id is " . $this->GetBufferID($this->ex[2]), FILE_APPEND);
			$this->torc->output->Output($this->GetBufferID($this->ex[2]), '<'.$this->sender.'> '.$this->msg);
		}
		else
		{
			if (preg_match('/^'.chr(1).'VERSION(.*?)'.chr(1).'$/i', $this->msg))
			{
				$this->torc->output->Output(BUFFER_CURRENT, 'CTCP VERSION reply from '.$this->sender.': '.substr($this->msg, 9, strlen($this->msg)-10));
			}
			else
			{
				$this->torc->output->Output(BUFFER_CURRENT, '('.$this->sender.') '.$this->msg);
			}
		}

	}

	function procnotice()
	{
		if ($this->ex[2][0] == "#")
		{
			$this->torc->output->Output($this->GetBufferID($this->ex[2]), '-'.$this->ex[2].'/'.$this->sender.'- '.$this->msg);
		}
		else
		{
			if (preg_match('/^'.chr(1).'VERSION(.*?)'.chr(1).'$/i', $this->msg))
			{
				$this->torc->output->Output(BUFFER_CURRENT, 'CTCP VERSION reply from '.$this->sender.': '.substr($this->msg, 9, strlen($this->msg)-10));
			}
			else
			{
				$this->torc->output->Output(BUFFER_CURRENT, '-'.$this->sender.'- '.$this->msg);
			}
		}
	}

	function sendline($data)
	{
		// sends a line to our irc
		if(!@fwrite($this->sp, $data."\n"))
		{
			$this->torc->output->Output(BUFFER_CURRENT, 'Warning: error writing ['.$data.']');
		}
	}
}


?>
