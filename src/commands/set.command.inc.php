<?php
if (isset($ex[1]))
{
	$sOldVal = $this->Config->GetKey($ex[1]);
	if (isset($ex[2]))
		$ex[2] = implode(" ", array_slice($ex, 2));
	else
		$ex[2] = null;
	$this->Config->SetKey($ex[1], $ex[2]);
	$sMsg = "set: " . $ex[1] . " set to " . $ex[2];
	if ($sOldVal)
		$sMsg .= " (previously " . $sOldVal . ")";
	$this->output->Output(BUFFER_CURRENT, $sMsg);
	$this->output->DrawBuffer($this->output->iCurrentBuffer);
}
else
{
	$this->output->Output(BUFFER_CURRENT, "set: Insufficient parameters. Syntax: <key> [value]");
}
?>
