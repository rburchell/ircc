<?php
if ($this->IRC)
	if (!isset($ex[2]))
		$this->IRC->spart($ex[1]);
	else
		$this->IRC->spart($ex[1], $ex[2]);
?>
