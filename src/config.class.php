<?php
/*
 * ircc - a handy, portable console irc client
 *
 * Copyright (C) 2008 Robin Burchell <w00t@inspircd.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the Licence.
 */

class Configuration
{
	private $aConfig = array();					// Configuration settings
	private $oClient;							// Instance of client class.

	public function __construct(&$oClient)
	{
		$this->oClient = $oClient;
		$this->LoadConfig();
	}

	public function __destruct()
	{
		$this->SaveConfig();
	}

	public function LoadConfig()
	{
	}

	public function SaveConfig()
	{
	}

	public function __set($sKey, $vValue)
	{
		$this->aConfig[$sKey] = $vValue;
	}

	public function __get($sKey)
	{
		if (isset($this->aConfig[$sKey]))
			return $this->aConfig[$sKey];

		return null;
	}
}

?>
