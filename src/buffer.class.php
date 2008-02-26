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


class BufferLine
{
	public $iTime;					// Epoch TS of when this line was created
	public $sLine;					// The line itself

	public function __construct(&$sLine, $iTime = "")
	{
		if (empty($iTime))
			$iTime = time();

		$this->iTime = $iTime;
		$this->sLine = $sLine;
	}
}

class Buffer
{
	public $topic;					// "topic" string.
	public $aLines = array();			// The contents of the window
	public $iLines = 0;				// Count of total lines in the buffer
	public $scroll = 0;				// How many lines we are scrolled (0 is none)
	public $oClient;					// Parent instance
	public $active;					// True if there is unread data on this buffer
	public $sName;					// Window title. e.g. nick of the person you're in query with or channel name.

	public function __construct(&$oClient, &$sName)
	{
		$this->oClient = $oClient;
		$this->sName = $sName;
	}

	public function AddToBuffer(&$sBuf)
	{
		$this->aLines[] = new BufferLine($sBuf);
		$this->iLines++;

		if ($this->scroll > 0)
			$this->scroll++; // we want to keep scrolled up by the right amount of lines =)

		// The following code will nuke lines out of memory after it gets big enough.
		//if (count($aRender) > $this->lines - 2)
		//{
		//	$iDiff = count($aRender) - ($this->lines - 2);
		//	$aRender = array_slice($aRender, $iDiff);
		//}
		//
		//while (count($this->aLines) > 10)
		//{
		//	// XXX I'm positive this could be done more efficiently with array_slice. - example above
		//	array_shift($this->aLines);
		//}
	}

	public function &GetBuffer()
	{
		return $this->buf;
	}

	/*
	 * Scrolls this buffer up by 2/3 of a page
	 */
	public function ScrollUp()
	{
		$fScrollModifier = $this->oClient->Config->buffer_scroll;
		if (!$fScrollModifier)
			$fScrollModifier = 0.5;
		$this->scroll = $this->scroll - ($this->oClient->output->lines * $fScrollModifier);
		if($this->scroll < 0)
			$this->scroll = 0;
	}

	/*
	 * Scrolls this buffer down by 2/3 of a page
	 */
	public function ScrollDown()
	{
		// XXX.. this should probably keep a count of total lines rather than having to constantly re-explode the buffer.
		$fScrollModifier = $this->oClient->Config->buffer_scroll;
		if (!$fScrollModifier)
			$fScrollModifier = 0.5;
		$this->scroll = $this->scroll + ($this->oClient->output->lines * $fScrollModifier);
		if ($this->scroll > $this->iLines - 1)
			$this->scroll = $this->iLines - 1;
	}

	/* Returns a displayable version of the buffer as an array */
	public function Display()
	{
		$aRet = array();
		$iLines = 0;

		// Get each line at the start of the viewport (total lines - scroll), and append to array.
		for ($x = $this->iLines - ($this->scroll + ($this->oClient->output->lines - 2)); $iLines < ($this->oClient->output->lines - 2); $x++) 
		{
			// Short (sorta wrong) fix: cast $x to int - so it works with buffer_scroll at 0.1, etc. We should
			// really intval() scroll when we set it instead.
			$x = (int)$x;
			$iLines++;
			if (isset($this->aLines[$x]))
			{
				$aRet[] = &$this->aLines[$x];
			}
			else
			{
				$aRet[] = null;
			}
		}

		return $aRet;
	}

}

?>
