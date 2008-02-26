<?php
// XXX this needs some living
if($ex[1] == '-o' && $this->IRC)
{
	$exout = explode("\n", trim(`$msg`));
	foreach($exout as $sayout)
	{
		$this->IRC->say($sayout);
	}
}
else
{
	$this->output->Output(BUFFER_CURRENT, trim(`$msgf`));
}
?>
