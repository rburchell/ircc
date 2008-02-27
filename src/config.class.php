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
		if (file_exists("settings.ini"))
			eval(file_get_contents("settings.ini"));
	}

	public function SaveConfig()
	{
		file_put_contents("settings.ini", '$this->aConfig = ' . var_export($this->aConfig, true) . ';');
	}

	public function SetKey($sKey, $vValue)
	{
		$aParam = explode("/", ltrim($sKey, "/"));
		$aUse =& $this->aConfig;

		for($i = 0; $i < count($aParam) - 1; $i++)
		{
			if(!isset($aUse[$aParam[$i]]) || !is_array($aUse[$aParam[$i]]))
				$aUse[$aParam[$i]] = Array();

			$aUse =& $aUse[$aParam[$i]];
		}

		$sLast = end($aParam);
		if(empty($sLast))
			$aUse[] = $sValue;
		else
			$aUse[$sLast] = $vValue;

		$this->SaveConfig();
	}

	public function GetKey($sKey)
	{
		$aParam = explode("/", ltrim($sKey, "/"));
		$aUse =& $this->aConfig;

		for($i = 0; $i < count($aParam) - 1; $i++)
		{
			if(!isset($aUse[$aParam[$i]]) || !is_array($aUse[$aParam[$i]]))
				return null;

			$aUse =& $aUse[$aParam[$i]];
		}

		$sLast = end($aParam);
		if (isset($aUse[$sLast]))
			return $aUse[$sLast];

		return null;
	}
}

?>
