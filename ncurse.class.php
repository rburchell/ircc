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

/* Constants for our ncurses use. */
define('NC_PAIR_IRCOUT',			1);					// Colour pair used for the IRC output window
define('NC_PAIR_INPUT',				2);					// Colour pair used for the input window
define('NC_PAIR_INPUT_ACTIVE',		3);					// Colour pair used for the input window, active window listing text.

declare(ticks = 1);


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
			$this->buf .= substr($sBuf, 0, $this->ncurse->columns) . "\n";
			$sBuf = '      ' . substr($sBuf, $this->ncurse->columns);
		}


		$this->buf .= $sBuf . "\n";
	}

	public function &GetBuffer()
	{
		return $this->buf;
	}

	/*
	 * Scrolls this buffer up by a page
	 */
	public function ScrollUp()
	{
		$this->scroll = $this->scroll - $this->ncurse->lines;
		if($this->scroll < 0)
			$this->scroll = 0;
	}

	/*
	 * Scrolls this buffer down by a page
	 */
	public function ScrollDown()
	{
		// XXX.. this should probably keep a count of total lines rather than having to constantly re-explode the buffer.
		$ex = explode("\n", $this->buf);
		$this->scroll = $this->scroll + $this->ncurse->lines;
		if ($this->scroll > count($ex))
			$this->scroll = count($ex);
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

	public $aDisplayVars = array();			// This contains information (like name etc) that are used when painting the GUI
	public $aBuffers = array();			// Numerically indexed array of Buffer instances.
	public $iCurrentBuffer = 0;			// Which buffer the user is currently viewing.
	public $torc;					// Reference to instance of main class

//16:29 <&w00t> nano calls endwin(), doupdate(), reinitialises terminal, reenables cursor, then deletes and recreates (and repopulates) it's windows

	public function __construct(&$torc)
	{
		// Set a signal handler to redraw on resize.
		pcntl_signal(SIGWINCH, array($this, "HandleResize"));

		$this->torc = $torc;
		$this->stdin = fopen('php://stdin', 'r');
		stream_set_blocking($this->stdin, 0); // disable blocking

		/*
		 * Initialise the terminal. We must do this before creating colour pairs.
		 */
		$this->InitialiseTerminal();

		/*
		 * Create our windows.
		 */
		$this->CreateWindows();
	}

	public function HandleResize($iSignal)
	{
		//$this->Output(BUFFER_CURRENT, "FFS RESIZE");
		ncurses_end();
		ncurses_doupdate();
		$this->InitialiseTerminal();
		$this->CreateWindows();
		$this->DrawBuffer($this->iCurrentBuffer);
		$this->setuserinput();
	}

	public function InitialiseTerminal()
	{
		$this->ncursesess = ncurses_init();
		ncurses_noecho(); // turn off echo to screen
		ncurses_start_color(); // initialise colour
		//ncurses_cbreak(); // turn off buffering
		ncurses_curs_set(0);

		/*
		 * Initialise the colour pairs that we'll use. First param is a define so we don't have to use
		 * magic numbers all through the app.
		 */
		ncurses_init_pair(NC_PAIR_IRCOUT, NCURSES_COLOR_WHITE,NCURSES_COLOR_BLUE);
		ncurses_init_pair(NC_PAIR_INPUT, NCURSES_COLOR_WHITE,NCURSES_COLOR_BLACK);
		ncurses_init_pair(NC_PAIR_INPUT_ACTIVE, NCURSES_COLOR_RED, NCURSES_COLOR_BLACK);
	}

	public function DeleteWindows()
	{
		ncurses_delwin($this->mainwin);
		ncurses_delwin($this->ircoutput);
		ncurses_delwin($this->userinputw);
	}

	public function CreateWindows()
	{
		$bResize = false;
		// If this is set, then we've "been here before", and we are probably being called from resize, so
		// we need to destroy our windows first, so as to not leak resources.
		if ($this->mainwin)
		{
			$bResize = true;
			$this->DeleteWindows();
		}

		$this->mainwin = ncurses_newwin(0, 0, 0, 0);

		$oldlines = $this->lines;
		$oldcol = $this->columns;

		ncurses_getmaxyx(&$this->mainwin, $this->lines, $this->columns);

		if ($bResize)
		{
			file_put_contents("resize.log", "Resized. Old lines/columns: " . $oldlines . "/" . $oldcol . " and new l/c " . $this->lines . "/" . $this->columns, FILE_APPEND);
		}
		
		// Generate a blank line so we can write over the output
		for ($x = 0; $x < $this->columns; $x++)
		{
			$this->cl .= ' ';
		}

		$this->ircoutput = ncurses_newwin($this->lines-2, $this->columns, 0, 0);
		$this->userinputw = ncurses_newwin(2, $this->columns, $this->lines - 2, 0);

		ncurses_wcolor_set($this->ircoutput, NC_PAIR_IRCOUT);
		ncurses_wcolor_set($this->userinputw, NC_PAIR_INPUT);

		ncurses_refresh();
		ncurses_wrefresh($this->ircoutput);
		ncurses_wrefresh($this->userinputw);
		ncurses_keypad($this->userinputw, true); // enable keypad.
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

	function SwitchToFirstActiveBuffer()
	{
		foreach ($this->aBuffers as $iBuffer => $oBuffer)
		{
			if ($oBuffer->active == true)
			{
				$this->DrawBuffer($iBuffer);
				break;
			}
		}
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
		static $bAlt = false;

		while ($c = ncurses_wgetch($this->userinputw))
		{
			if ($c == -1)
				break;

//			$this->torc->output->Output(BUFFER_CURRENT, "got " . $c);

			/*
			 * We do this in a seperate switch so we can unset meta combinations if they don't fit our demands easily.
			 */
			if ($bAlt)
			{
				$bAlt = false;

				switch (chr($c))
				{
					case 'a':
						$this->SwitchToFirstActiveBuffer();
						return;
						break;
				}
			}

			switch ($c)
			{
				case 27:
					// Alt modifier.
					$bAlt = true;
					break;
				case 13:
					$usrp = $this->userinputt;
					$this->sendhis[] = $usrp;
					$this->sendhispt = count($this->sendhis)-1;
					$this->userinputt = '';
					$this->setuserinput();
					$this->sendhislu = true;
					return $usrp;
					break;
				case NCURSES_KEY_BACKSPACE:
					$this->userinputt = substr($this->userinputt, 0, strlen($this->userinputt)-1);
					$this->setuserinput();
					break;		
				case NCURSES_KEY_NPAGE:
					$this->aBuffers[$this->iCurrentBuffer]->ScrollUp();
					$this->DrawBuffer($this->iCurrentBuffer);
					break;
				case NCURSES_KEY_PPAGE:	
					$this->aBuffers[$this->iCurrentBuffer]->ScrollDown();
					$this->DrawBuffer($this->iCurrentBuffer);
					break;
				case NCURSES_KEY_UP:
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
					break;
				case NCURSES_KEY_DOWN:
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
					break;
				default:
					$this->userinputt .= chr($c);
					$this->setuserinput();
					break;
			}
		}


		// And let caller know that nothing came out of the input buffer.
		return false;
	}
	
	function SetDisplayVar($sKey, $sVal)
	{
		$this->aDisplayVars[$sKey] = $sVal;
	}

	function setuserinput()
	{
		$sStatus = date("[H:i:s] ") . "[" . $this->aDisplayVars['nick'] . "] [Win: ";

		ncurses_mvwaddstr($this->userinputw, 0, 0, $this->cl);
		ncurses_mvwaddstr($this->userinputw, 0, 0, $sStatus);

		/* Now we need to draw states for our various windows. */
		$bShow = false;

		$aInactive = array();
		$aActive = array();
		$iChans = 0;


		// Draw a list of all windows. First, get two arrays of which are active and not.
		foreach ($this->aBuffers as $iBuffer => $oBuffer)
		{
			if ($oBuffer->active == true)
			{
				$aActive[] = $iBuffer;
			}
			else
			{
				$aInactive[] = $iBuffer;
			}
		}

		// Now generate out list. First of all, we want to draw only active windows. We also want them to stand out.
		foreach ($aActive as $iActive)
		{
			if ($bShow == false)
			{
				ncurses_wcolor_set($this->userinputw, NC_PAIR_INPUT_ACTIVE);
				ncurses_waddstr($this->userinputw, $iActive);
				ncurses_wcolor_set($this->userinputw, NC_PAIR_INPUT);
				$bShow = true;
			}
			else
			{
				ncurses_waddstr($this->userinputw, ",");
				ncurses_wcolor_set($this->userinputw, NC_PAIR_INPUT_ACTIVE);
				ncurses_waddstr($this->userinputw, $iActive);
				ncurses_wcolor_set($this->userinputw, NC_PAIR_INPUT);
			}
		}

		/*
		 * Now append inactive windows. We don't make these stand out of course.
		 */
		foreach ($aInactive as $iInactive)
		{
			if ($bShow == false)
			{
				ncurses_waddstr($this->userinputw, $iInactive);
				$bShow = true;
			}
			else
			{
				ncurses_waddstr($this->userinputw, ",");
				ncurses_waddstr($this->userinputw, $iInactive);
			}
		}

		ncurses_waddstr($this->userinputw, "]");

		ncurses_mvwaddstr($this->userinputw, 1, 0, $this->cl);
		ncurses_mvwaddstr($this->userinputw, 1, 0, "[" . $this->aDisplayVars['window'] . "] " . $this->userinputt . '_');


		ncurses_wrefresh($this->userinputw);
	}

	function quit()
	{
		ncurses_clear();
		ncurses_end();
	}
}

?>
