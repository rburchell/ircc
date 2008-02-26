<?php
if ($this->IRC)
{
	// XXX ick
	foreach ($this->output->aBuffers as $oBuffer)
	{
		if ($oBuffer->oServer === $this->IRC)
			$oBuffer->oServer = null;
	}

	$this->output->Output(BUFFER_CURRENT, "Quit from " . $this->IRC->sServerName);
	$this->IRC->squit($msgf);
	$this->DeleteConnection($this->IRC);
	
}
?>
