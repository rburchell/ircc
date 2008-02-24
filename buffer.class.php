<?php
/*
 * ircc - a handy, portable console irc client
 *
 * Copyright (C) 2008 Robin Burchell <w00t@inspircd.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

class Buffer
{
	public $topic;					// "topic" string.
	public $buf;					// The contents of the window itself
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

?>
