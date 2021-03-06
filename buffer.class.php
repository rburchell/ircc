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

class Buffer
{
	public $topic;					// "topic" string.
	public $aLines = array();			// The contents of the window
	public $scroll = 0;				// How many lines we are scrolled (0 is none)
	public $ncurse;					// Parent ncurses instance
	public $active;					// True if there is unread data on this buffer
	public $sName;					// Window title. e.g. nick of the person you're in query with or channel name.

	public function __construct(&$ncurse, &$sName)
	{
		$this->ncurse = $ncurse;
		$this->sName = $sName;
	}

	public function AddToBuffer(&$sBuf)
	{
		$this->aLines[] = $sBuf;

		// The following code will nuke lines out of memory after it gets big enough.
		//while (count($this->aLines) > 10)
		//{
		//	// XXX I'm positive this could be done more efficiently with array_slice.
		//	array_shift($this->aLines);
		//}
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
		$this->scroll = $this->scroll + $this->ncurse->lines;
		if ($this->scroll > count($this->aLines))
			$this->scroll = count($this->aLines);
	}

	/* Returns a displayable version of the buffer as an array */
	public function Display()
	{
		$aRet = array();
		$iLines = 0;

		// Get each line at the start of the viewport (total lines - scroll), and append to array.
		for ($x = count($this->aLines) - ($this->scroll + ($this->ncurse->lines - 2)); $iLines < ($this->ncurse->lines - 2); $x++)
		{
			$iLines++;
			if (isset($this->aLines[$x]))
			{
				$aRet[] = &$this->aLines[$x];
			}
			else
			{
				$aRet[] = "";
			}
		}

		return $aRet;
	}

}

?>
