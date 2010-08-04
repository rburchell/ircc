<?php
if (!isset($ex[2]))
	$this->irc->spart($ex[1]);
else
	$this->irc->spart($ex[1], $ex[2]);
?>
