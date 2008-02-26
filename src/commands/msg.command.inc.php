<?php
if ($this->IRC)
	$this->IRC->sprivmsg($ex[1], implode(" ", array_slice($ex, 2)));
?>
