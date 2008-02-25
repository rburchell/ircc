<?php
if($ex[1] == '-o')
{
	$exout = explode("\n", trim(`$msg`));
	foreach($exout as $sayout)
	{
		$this->irc->say($sayout);
	}
}
else
{
	$this->irc->addout(trim(`$msgf`));
}
?>
