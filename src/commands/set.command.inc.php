<?php
if (isset($ex[1]) && isset($ex[2]))
{
	$this->Config->$ex[1] = $ex[2];
	$this->output->Output(BUFFER_CURRENT, "set: " . $ex[1] . " set to " . $ex[2]);
	$this->output->DrawBuffer($this->output->iCurrentBuffer);
}
else
{
	$this->output->Output(BUFFER_CURRENT, "set: Insufficient parameters. Syntax: [key] [value]");
}
?>
