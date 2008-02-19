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

	function ncurse()
	{
		$this->stdin = fopen('php://stdin', 'r');

		$this->ncursesess = ncurses_init();
		ncurses_start_color();
		ncurses_curs_set(0);

		$this->mainwin = ncurses_newwin(0, 0, 0, 0);

		ncurses_getmaxyx(&$this->mainwin, $this->lines, $this->columns);
		
		// Generate a blank line so we can write over the output
		for($x = 0; $x < $this->columns - 1; $x++)
		{
			$this->cl .= ' ';
		}

		$this->ircoutput = ncurses_newwin($this->lines-2, $this->columns, 0, 0);
		$this->userinputw = ncurses_newwin(2, $this->columns, $this->lines - 2, 0);

		ncurses_refresh();
		ncurses_wrefresh($this->ircoutput);
		ncurses_wrefresh($this->userinputw);
	}


	function addtoircout($line, $fd = -1)
	{
		// NEVER CALL THIS FUNCTION WITH INPUT WITH \n's
		while(strlen($line) > $this->columns-3)
		{
			$this->ircout_content .= substr($line,0, $this->columns-6)."\n";
			$line = '   '.substr($line, $this->columns-6);
		}

		$this->ircout_content .= $line;

		if($fd == -1)
			$this->buildircout($this->scrollfd);	
		else
			$this->buildircout($fd);

	}

	function buildircout($fd = 0)
	{
		$fd += 2;

		$ex = explode("\n", $this->ircout_content);

		for($x = 0; $x < $this->lines-2; $x++)
		{
			ncurses_mvwaddstr($this->ircoutput, $x, 0, $this->cl);
		}

		$linetype = NULL;
		$ll = 0;
		$linesleft = $this->lines-2;

		for($x = count($ex)-$fd; $x >= 0; $x--)
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

	function getuserinput()
	{
		$read = array($this->stdin);
		$write = $except = NULL;

		while(stream_select($read, $write, $except, 0, 80000))
		{
			//$c = fgetc($this->stdin);
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
			}
			elseif ($c == NCURSES_KEY_PPAGE)
			{
				$ex = explode("\n", $this->ircout_content);
				$this->scrollfd = $this->scrollfd + $this->lines;
				if ($this->scrollfd > count($ex))
					$this->scrollfd = count($ex);
				$this->buildircout($this->scrollfd);
			}
/*			elseif($c == chr(0x1B))
			{
				// This is all for pageup/down etc. It's also crack.
				$read = array($this->stdin);
				$write = $except = NULL;
				if(stream_select($read, $write, $except, 0, 80000))
				{
					$c = fgetc($this->stdin);
					if($c == chr(0x5B))
					{
						$read = array($this->stdin);
						$write = $except = NULL;
						if(stream_select($read, $write, $except, 0, 80000))
						{
							$c = fgetc($this->stdin);
							$this->addtoircout("TORX: ".ord($c)."\n");
							switch($c)
							{
							case chr(0x41):
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

							case chr(0x42):
								if($this->sendhispt+1 < count($this->sendhis))
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

							case chr(0x35):
								fgetc($this->stdin);
								break;

							case chr(0x53); // 36


							}
						}
					}
				}
			}*/
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
		ncurses_mvwaddstr($this->userinputw, 0, 0, $this->cl);
		ncurses_mvwaddstr($this->userinputw, 0, 0, date("[H:i:s] ") . "[" . $this->aDisplayVars['nick'] . "]");

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
