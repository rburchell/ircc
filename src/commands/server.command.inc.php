<?php
if(!empty($ex[2]))
	$port = (int)$ex[2];
else
	$port = 6667;

if (empty($ex[3]))
	$ex[3] = "";

$this->AddConnection();
$this->IRC->connect($ex[1], $port, "", $this->username, "torc", "server", "torc - torx irc user", $this->nick, $ex[3]);
?>
