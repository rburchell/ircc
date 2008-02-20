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

class Buffer
{
	var $topic;
	var $buf;
	var $scroll = 0;
	var $ncurse;
	var $active;					// fucking true if there is fucking shit to fucking show in this window.
	var $sName;						// Window title. e.g. nick of the person you're in query with or channel name.

	public function __construct(&$ncurse, &$sName)
	{
		$this->ncurse = $ncurse;
		$this->sName = $sName;
	}

	public function AddToBuffer(&$sBuf)
	{
		// XXX indentation should be done on draw, not on buffer add.
		while (strlen($sBuf) > $this->ncurse->columns - 3)
		{
			$this->buf .= substr($sBuf, 0, $this->ncurse->columns - 6) . "\n";
			$sBuf = '      ' . substr($sBuf, $this->ncurse->columns - 6);
		}


		$this->buf .= $sBuf . "\n";
	}

	public function &GetBuffer()
	{
		return $this->buf;
	}
}

class ncurse
{
	var $ncursesess;
	var $mainwin;
	var $userinputw;
	var $ircoutput;
	var $stdin;
	var $ircout_content;
	var $lines, $columns;
	var $userinputt;
	var $userinputp = 0;
	var $cl;
	var $sendhis = array();
	var $sendhispt;
	var $sendhislu;
	var $scrollfd = 0;
	var $aDisplayVars = array();		// This contains information (like name etc) that are used when painting the GUI
	var $aBuffers = array();			// Numerically indexed array of Buffer instances.
	var $iCurrentBuffer = 0;
	var $torc;							// Reference to instance of main class

	function ncurse(&$torc)
	{
		$this->torc = $torc;
		$this->stdin = fopen('php://stdin', 'r');

		$this->ncursesess = ncurses_init();
		ncurses_noecho();
		ncurses_start_color();
		ncurses_init_pair(1,NCURSES_COLOR_WHITE,NCURSES_COLOR_BLUE);
		ncurses_init_pair(2,NCURSES_COLOR_WHITE,NCURSES_COLOR_BLACK);

		ncurses_curs_set(0);

		$this->mainwin = ncurses_newwin(0, 0, 0, 0);

		ncurses_getmaxyx(&$this->mainwin, $this->lines, $this->columns);
		
		// Generate a blank line so we can write over the output
		for ($x = 0; $x < $this->columns; $x++)
		{
			$this->cl .= ' ';
		}

		$this->ircoutput = ncurses_newwin($this->lines-2, $this->columns, 0, 0);
		$this->userinputw = ncurses_newwin(2, $this->columns, $this->lines - 2, 0);

		ncurses_wcolor_set($this->ircoutput, 1);
		ncurses_wcolor_set($this->userinputw, 2);

		ncurses_refresh();
		ncurses_wrefresh($this->ircoutput);
		ncurses_wrefresh($this->userinputw);
	}

	/*
	 * Creates a new buffer, and returns the index it may be referenced by.
	 *
	 */
	function AddBuffer(&$sName)
	{
		for ($i = 0; /* nothing */; $i++)
		{
			if (!isset($this->aBuffers[$i]))
			{
				$this->aBuffers[$i] = new Buffer($this, $sName);
				return $i;
			}
		}
	}

	function DeleteBuffer($iBuffer)
	{
		$this->aBuffers[$iBuffer] = false;
		unset($this->aBuffers[$iBuffer]);
	}

	function IsBuffer($iBuffer)
	{
		if (isset($this->aBuffers[$iBuffer]))
			return true;

		return false;
	}

	/*
	 * "Switches" to a new buffer specified by unique ID and redraws it.
	 */
	function DrawBuffer($iBuffer)
	{
		file_put_contents("buffer.log", "drawing buffer " . $iBuffer . " with contents " . $this->aBuffers[$iBuffer]->GetBuffer());
		$this->iCurrentBuffer = $iBuffer;
		$this->torc->irc->sCurrentTarget = $this->aBuffers[$iBuffer]->sName;
		$this->SetDisplayVar("window", $this->aBuffers[$iBuffer]->sName);
		$this->setuserinput();
		$this->aBuffers[$iBuffer]->active = false;
		$ex = explode("\n", $this->aBuffers[$iBuffer]->GetBuffer());

		for($x = 0; $x < $this->lines-2; $x++)
		{
			ncurses_mvwaddstr($this->ircoutput, $x, 0, $this->cl);
		}

		$linetype = NULL;
		$ll = 0;
		$linesleft = $this->lines - 2;

		for ($x = count($ex) - $this->aBuffers[$iBuffer]->scroll; $x >= 0; $x--)
		{
			if($linesleft == 0)
			{
				$x = -1;
			}
			else
			{
				if(!empty($ex[$x]))
				{
					$linesleft--;
					ncurses_mvwaddstr($this->ircoutput, $linesleft, 0, $ex[$x]);
				}
			}
		}

		ncurses_wrefresh($this->ircoutput);
	}

	function Output($iBuffer, $sBuf)
	{
		if ($iBuffer == -1)
			$iBuffer = $this->iCurrentBuffer;

		$sBuf = date("[H:i] ") . $sBuf . "\n";
		$this->aBuffers[$iBuffer]->AddToBuffer($sBuf);

		if ($this->iCurrentBuffer == $iBuffer)
			$this->DrawBuffer($iBuffer); // force redraw
		else
			$this->aBuffers[$iBuffer]->active = true;
	}

	function getuserinput()
	{
		$read = array($this->stdin);
		$write = $except = NULL;

		while(stream_select($read, $write, $except, 0, 80000))
		{
			$c = ncurses_getch();

			if ($c == 13) // Constant would be nice.
			{
				$usrp = $this->userinputt;
				$this->sendhis[] = $usrp;
				$this->sendhispt = count($this->sendhis)-1;
				$this->userinputt = '';
				$this->setuserinput();
				$this->sendhislu = true;
				return $usrp;
			}
			elseif($c == NCURSES_KEY_BACKSPACE)
			{
				$this->userinputt = substr($this->userinputt, 0, strlen($this->userinputt)-1);
				$this->setuserinput();
				return false;
			}
			elseif ($c == NCURSES_KEY_NPAGE)
			{
				$this->scrollfd = $this->scrollfd - $this->lines;
				if($this->scrollfd < 0)
					$this->scrollfd = 0;
				$this->buildircout($this->scrollfd);
				return false;
			}
			elseif ($c == NCURSES_KEY_PPAGE)
			{
				$ex = explode("\n", $this->ircout_content);
				$this->scrollfd = $this->scrollfd + $this->lines;
				if ($this->scrollfd > count($ex))
					$this->scrollfd = count($ex);
				$this->buildircout($this->scrollfd);
				return false;
			}
			elseif ($c == NCURSES_KEY_UP)
			{
				if($this->sendhispt >= 0)
				{
					if(!$this->sendhislu)
					{
						$this->sendhispt--;
						$this->sendhislu = true;
					}
					$this->userinputt = $this->sendhis[$this->sendhispt];
					$this->sendhispt--;
					$this->setuserinput();
				}
				return false;
			}
			elseif ($c == NCURSES_KEY_DOWN)
			{
				if($this->sendhispt+1 < count($this->sendhis) - 1)
				{
					if($this->sendhislu)
					{
						$this->sendhispt++;
						$this->sendhislu = false;
					}
					$this->sendhispt++;
					$this->userinputt = $this->sendhis[$this->sendhispt];
					$this->setuserinput();
				}
				return false;
			}
			else
			{
				$this->userinputt .= chr($c);
				$this->setuserinput();
				return false;
			}
		}
	}
	
	function SetDisplayVar($sKey, $sVal)
	{
		$this->aDisplayVars[$sKey] = $sVal;
	}

	function setuserinput()
	{
		$sStatus = date("[H:i:s] ") . "[" . $this->aDisplayVars['nick'] . "]";

		$bShow = false;
		$sActive = "[Act: ";

		foreach ($this->aBuffers as $iBuffer => $oBuffer)
		{
			if ($oBuffer->active == true)
			{
				if ($bShow == false)
				{
					$bShow = true;
					$sActive .= $iBuffer;
				}
				else
				{
					$sActive .= ", " . $iBuffer;
				}
			}
		}

		if ($bShow)
		{
			$sStatus .= " " . $sActive . "]";
		}

		ncurses_mvwaddstr($this->userinputw, 0, 0, $this->cl);
		ncurses_mvwaddstr($this->userinputw, 0, 0, $sStatus);

		ncurses_mvwaddstr($this->userinputw, 1, 0, $this->cl);
		ncurses_mvwaddstr($this->userinputw, 1, 0, "[" . $this->aDisplayVars['window'] . "] " . $this->userinputt.'_');
		ncurses_wrefresh($this->userinputw);
	}

	function quit()
	{
		ncurses_clear();
		ncurses_end();
	}
}

?>
