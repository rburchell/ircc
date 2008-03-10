<?php
if ($this->output->IsBuffer($ex[1]))
	$this->output->DrawBuffer($ex[1]);
else
{
	if ($ex[1] == "close")
	{
		if ($this->output->IsBuffer($ex[2]) && $ex[2] != BUFFER_STATUS /* no closing root window. */)
		{
			$this->output->DeleteBuffer($ex[2]);
			$this->output->Output(BUFFER_CURRENT, "Closed successfully.");
		}
		else
		{
			$this->output->Output(BUFFER_CURRENT, "This is an immortal window; only god may terminate it. (or, you fail, and it doesn't exist)");
		}
	}
	else
		$this->output->Output(BUFFER_CURRENT, "That is not a valid window or command.");
}
?>
