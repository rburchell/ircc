<?php
$this->output->Output(BUFFER_STATUS, "setting ".$ex[1]." to ".(int)trim($msg)."\n");
$this->irc->set($ex[1], (int)trim($msg));
?>
