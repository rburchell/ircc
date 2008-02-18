#!/usr/local/bin/php
<?php $torc_ver = 'torc-1.3.0';
error_reporting(0); ?>

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
			- provide simple access to an ncurses gui

---------------------------------------------------------*/


//argument handling at end of file

set_time_limit(0);
ob_implicit_flush();

if(empty($torc_ver))
	$torc_ver = 'torc-dev';

include "ncurse.class.php";
//include "term.class.php";
include "irc.class.php";

class torc {
	var $irc, $output;

	function torc($server, $mode, $nick, $ssl, $port) {
	


		switch($mode){
			case 'ncurses':
				$this->output = new ncurse();
			break;

			case 'term':
//				$this->output = new term();
				echo "Unsupported.\n";
			break;

			default:
				$this->usage();
			break;
		}


		$this->irc = new irc();

		$this->output->addtoircout("torc - torx irc client\n");

		$username = 'torc';
		if(!empty($_ENV['LOGNAME'])){
			$username = $_ENV['LOGNAME'];
		} elseif(!empty($_ENV['USER'])){
			$username = $_ENV['USER'];
		}
		if($username == 'root'){
			$this->output->addtoircout("you are running torc as root! this can be very stupid! setting username to 'torc'\n");
			$username = 'torc';
		}


		if($nick == -2)
			$nick = $username;

		if($server != -2){
			$this->output->addtoircout('connecting to ['.$server.'], port '.$port.', ssl mode: '.(int)$ssl."\n");
			$this->irc->connect($server, $port, $ssl, $username, "torc", "server", "torc - torx irc user", $nick);
		} else {
			$this->output->addtoircout("use the /SERVER command to connect to a server\n");
			$this->output->addtoircout("/QUIT to quit\n\n\n");
		}

	//	$this->irc->connect("darkwired.ath.cx",6667, false, "rainman", "orion", "server", "rainman", "rianman");

		$updct = 100;
		$prct = 0;
		while(true){
			usleep(15000);
			if (($input = $this->output->getuserinput())){
				//we have a line of input
				if(substr($input, 0, 1) == "/"){

					$ex = explode(" ", $input);
					$cmd = substr($ex[0], 1);
					$msg = $ex;
					$msg[0] = "";
					$msg[1] = "";
					$msg = trim(implode(" ", $msg));
					$msgh = $ex;
					$msgh[0] = "";
					$msgh[1] = "";
					$msgh[2] = "";
					$msgh = trim(implode(" ", $msgh));
					$msgf = $ex;
					$msgf[0] = "";
					$msgf = trim(implode(" ", $msgf));
					$ex[count($ex)-1] = trim($ex[count($ex)-1]);

					switch(strtolower($cmd)){
						case 'server':
							
							if(!empty($ex[2]))
								$port = (int)$ex[2];
							else
								$port = 6667;

							$this->irc->connect($ex[1], $port, $ex[3], $username, "torc", "server", "torc - torx irc user", $nick);
						break;

						case 'connect':
							
							if(!empty($ex[2]))
								$port = (int)$ex[2];
							else
								$port = 6667;

							$this->irc->connect($ex[1], $port, $ex[3], $username, "torc", "server", "torc - torx irc user", $nick);
						break;
						case 'quit':
							$this->irc->squit($msgf);
							$this->output->quit();
							die("\n\nVote torc! Vote insanity!\n");
						break;

						case 'join':
							$this->irc->sjoin($ex[1]);
						break;

						case 'nick':
							$this->irc->snick($ex[1]);
						break;

						case 'part':
							$this->irc->spart($ex[1], $ex[2]);
						break;

						case 'oper':
							$this->irc->soper($ex[1], $ex[2]);
						break;

						case 'mode':
							$this->irc->smode($ex[1], $ex[2]);
						break;

						case 'topic':
							$this->irc->stopic($ex[1], $msg);
						break;

						case 'notice':
							$this->irc->snotice($ex[1], $msg);
						break;

						case 'names':
							$this->irc->snames($ex[1]);
						break;

						case 'kick':
							$this->irc->skick($ex[1], $ex[2], $msgh);
						break;

						case 'op':
							$this->irc->smode($ex[1], "+o ".$ex[2]);
						break;

						case 'deop':
							$this->irc->smode($ex[1], "-o ".$ex[2]);
						break;

						case 'ver':
							$this->irc->sversion($ex[1]);
						break;

						case 'me':
							$this->irc->saction($msgf);
						break;

						case 'quote':
						case 'raw':
							$this->irc->sendline($msgf);
						break;
						
						case 'say':
							$this->output->addtoircout($this->irc->getuser().trim($input)."\n");
							$this->irc->say($input);
						break;

						case 'exec':
							//$this->irc->addout('msg: '.$msg.', msgf: '.$msgf);
							if($ex[1] == '-o'){
								$exout = explode("\n", trim(`$msg`));
								foreach($exout as $sayout){
									$this->irc->say($sayout);
								}
							} else {
								$this->irc->addout(trim(`$msgf`));
							}
						break;

						case 'setb':
							$this->output->addtoircout("setting ".$ex[1]." to ".(int)trim($msg)."\n");
							$this->irc->set($ex[1], (int)trim($msg));
						break;

						case 'privmsg':
						case 'msg':
							$this->irc->sprivmsg($ex[1], $msg);
						break;

						default:
							$this->output->addtoircout('warning: unknow command ['.$cmd."], sending raw to server\n");
							$this->irc->sendline($cmd." ".$msgf);
						break;
					}
				} else {
					$this->irc->say($input);
				}
			}

			$updct++;
			if($updct>30){
				$this->output->setuserinput($this->irc->usernick, $this->irc->chan);
				$updct = 0;

			}

			$prct++;
			if($prct>3){
				if($this->irc->sp)
					$this->irc->procline();

				$out = explode("\n", $this->irc->getout());
				foreach($out as $send){
					$t = trim($send);
					if(!empty($t))
						$this->output->addtoircout($send."\n");
					unset($t);
				}
				$prct = 0;
			}
		}
	}
	
	
	function usage(){
		global $torc_ver;
		die("
".$torc_ver."
torx irc client by rainman <rainman@darkwired.org>

license: GNU GPL

thanks to dodo
greetz to #darkwired

url: https://www.darkwired.org


usage: torc [options]
  available options:
    -c kork            connect to server kork
    -p 123             port to connect to
    -s                 enable ssl connection
    -n torx            use torx as nick
    -m term|ncurses    use ncurses or term(experimental) for output


");
	}
}

//argument switching

$mode = 'ncurses';
$nick = -2;
$ssl = false;
$port = 6667;
$server = -2;

$argv = $_SERVER['argv'];
$argc = $_SERVER['argc'];

for($x = 1; $x < $argc; $x++){
	switch ($argv[$x]){

		case '-n':
			$nick = $argv[$x+1];
			$x++;
		break;

		case '-c':
			$server = $argv[$x+1];
			$x++;
		break;

		case '-s':
			$ssl = true;
		break;

		case '-p':
			$port = (int)$argv[$x+1];
			$x++;
		break;

		case '-m':
			$mode = $argv[$x+1];
			$x++;
		break;

		default:
			torc::usage();
		break;
	}


}

new torc($server, $mode, $nick, $ssl, $port);

?>
