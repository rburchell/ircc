
<?php

/*---------------------------------------------------------
		Vote torc! Vote insanity!

		torc - torx irc client
		Copyright (C) 2003 rainman <rainman@darkwired.org>

		This program is free software; you can redistribute it and/or modify
		it under the terms of the GNU General Public License as published by
		the Free Software Foundation; either version 2 of the License, or
		(at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

		Class functions:
			- do the same as ncurse class without ncurses

---------------------------------------------------------*/
die("sorry, don't use this - not sure wtf it is. (term)");
class term {

	var $ircoutput;
	var $columns, $lines;
	var $stdin;
	var $userinputt;
	var $sendhis = array();
	var $sendhispt;
	var $sendhislu;
	var $scrollfd = 0;

	function term(){

		$this->stdin = fopen('php://stdin', 'r');
		stream_set_blocking($this->stdin, false);

		$this->columns = `tput cols`;
		$this->lines = `tput lines`;
		
		$this->addtoircout("NOTE: YOU ARE USING TERM OUTPUT\n");
		$this->addtoircout("THIS IS VERY EXPERIMENTAL\n");
		$this->addtoircout("AND VERY BUGGY\n\n\n");

	}


	function addtoircout($line, $fd = -1){

		$this->columns = `tput cols`;
		$this->lines = `tput lines`;


		$this->ircout_content .= $line;

		if($fd == -1)
			$this->buildircout($this->scrollfd);
		else
			$this->buildircout($fd);

	}

	function buildircout($fd = 0){

	for($x = 0; $x <= $this->lines; $x++){
		echo "\n";
	}

	$ex = explode("\n", $this->ircout_content);

	$cex = count($ex);

	for($x = $cex - $this->lines; $x < $cex; $x++){
		echo "\n".$ex[$x];
	}
	
	$this->resetuserinput();

		/*$fd += 2;

		$ex = explode("\n", $this->ircoutput);

		`clear`;


		$linetype;
		$ll = 0;
		$linesleft = $this->lines-1;
		for($x = count($ex)-$fd; $x >= 0; $x--){
			if($linesleft == 0){
				$x = -1;
			} else {
				if(!empty($ex[$x])){
					$linesleft--;
					echo $ex[$x]."\n";
				}
			}
		}*/

		//$this->setuserinput();


	}

	function getuserinput(){

		//while(stream_select($read = array($this->stdin), $write = NULL, $except = NULL, 0)){
		$c = fgetc($this->stdin);
		if(ord($c) != 0){
			//print("TORX: ".ord($c)."\n");
			if ($c == chr(13) || $c == chr(10)){
				//echo "KORK: ".$this->userinputt."\n";
				$usrp = $this->userinputt;
				$this->sendhis[] = $usrp;
				$this->sendhispt = count($this->sendhis)-1;
				$this->userinputt = '';
				$this->setuserinput();
				$this->sendhislu = true;
				return $usrp;
			} elseif($c == chr(0x7F) || $c == chr(0x08)){
				$this->userinputt = substr($this->userinputt, 0, strlen($this->userinputt)-1);
				$this->setuserinput();
				return false;
			} elseif($c == chr(0x1B)){
				if(stream_select($read = array($this->stdin), $write = NULL, $except = NULL, 0)){
					$c = fgetc($this->stdin);
					if($c == chr(0x5B)){
						if(stream_select($read = array($this->stdin), $write = NULL, $except = NULL, 0)){
							$c = fgetc($this->stdin);
							$this->addtoircout("TORX: ".ord($c)."\n");
							switch($c){
							case chr(0x41):
								if($this->sendhispt >= 0){
									if(!$this->sendhislu){
										$this->sendhispt--;
										$this->sendhislu = true;
									}
									$this->userinputt = $this->sendhis[$this->sendhispt];
									$this->sendhispt--;
									$this->setuserinput();
								}
							break;

							case chr(0x42):
								if($this->sendhispt+1 < count($this->sendhis)){
									if($this->sendhislu){
										$this->sendhispt++;
										$this->sendhislu = false;
									}
									$this->sendhispt++;
									$this->userinputt = $this->sendhis[$this->sendhispt];
									$this->setuserinput();
								}
							break;

							case chr(0x35):
// JKLJKLJKLJKLJLKJLKJLK								$this->buildircout(5);
								fgetc($this->stdin);
							break;

							case chr(0x36);
								$this->scrollfd = $this->scrollfd-$this->lines;
								if($this->scrollfd < 0)
									$this->scrollfd = 0;
								$this->buildircout($this->scrollfd);

							}
						}
					}
				}
					
			} else {
				$this->userinputt .= $c;
				$this->setuserinput();
				return false;
			}
		}
	}

	function updateinfo($user, $chan){
	}

	//INTERNAL USE ONLY BELOW
	function setuserinput(){
		//echo $this->userinputt.'_'."\n";
	}

	function resetuserinput(){
		echo $this->userinputt;
	}

	function quit(){
	}


}

?>
