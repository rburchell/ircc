<?php
if ($ex[1] == "add")
{
	$aServers = $this->Config->autoconnect_servers;
	if (!$aServers)
	{
		$this->Config->autoconnect_servers = array();
		$aServers = array();
	}

	$bFound = false;

	foreach ($aServers as $aServer)
	{
		if ($aServer['name'] == $ex[2])
			$bFound = true;
	}

	if ($bFound == false)
	{
		// XXX yeah, sure, I should add port to this. But who cares.
		$aServer['name'] = $ex[2];
		$aServer = array($aServer);
		$this->Config->autoconnect_servers = array_merge($this->Config->autoconnect_servers, $aServer);
		$this->output->Output(BUFFER_CURRENT, "Added " . $ex[2] . " to autoconnect.");
	}
	else
	{
		$this->output->Output(BUFFER_CURRENT, $ex[2] . " is already on autoconnect.");
	}
}
else if ($ex[1] == "del")
{
	unset($this->Config->autoconnect_servers[$ex[2]]);
	$this->output->Output(BUFFER_CURRENT, "Removed " . $ex[2] . " from autoconnect.");
}
?>
