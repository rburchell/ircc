<?php
if ($this->output->IsBuffer($ex[1]))
	$this->output->DrawBuffer($ex[1]);
else
	$this->output->Output(BUFFER_CURRENT, "That is not a valid window.");
?>
