<?php
// XXX I don't think this is needed
//$this->output->Output(BUFFER_STATUS, $this->irc->getuser().trim($input)."\n");
if ($this->IRC)
	$this->IRC->say($input);
?>
