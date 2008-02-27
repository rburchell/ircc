<?php
if (isset($ex[1]))
{
	$vVal = $this->Config->GetKey($ex[1]);

	if ($vVal)
	{
		$this->output->Output(BUFFER_CURRENT, "key " . $ex[1] . ":");

		$aLines = explode("\n", print_r($vVal, true));
		foreach ($aLines as $sLine)
		{
			$this->output->Output(BUFFER_CURRENT, " " . $sLine);
		}
	}
}
else
{
	$this->output->Output(BUFFER_CURRENT, "invalid syntax: /get <key>");
}
?>
