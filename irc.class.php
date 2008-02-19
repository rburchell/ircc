<?php
/*
 * ircc - a handy, portable console irc client
 *
 * Copyright (C) 2008 Robin Burchell <w00t@inspircd.org>
 * Copyright (C) 2003 rainman <rainman@darkwired.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

class irc {
	var $sp; 						// the socket file pointer
	var $output;					// everything that is ready to be sent to whoever called us
	var $usernick;					// the nick of the user
	var $chan;

	var $line; 						// the line we read from the irc input
	var $sender;					// the nick that sent this message
									//		will be messed up if it's not a normal message but then it's not used
	var $ex;						// the explode(' ', $line), we use this a lot so it's an attribute
	var $msg;						// this is everything after the : (reread each line)
	var $autonick;					// if this is true we auto change nicks
	var $prevnick;					// the previus nick

	var $sets, $chans = array();

	function irc()
	{
	}

	function connect(
		$server,				// (string) the server to connect to
		$port,					// (int) the port to connect to
		$ssl,						// (bool) wether this connection uses ssl
		$username,			// (string) username send in USER command
		$hostname,			// (string) hostname send in USER command
		$servername,		// (string) servername send in USER command
		$realname,			// (string) realname send in USER command
		$nick,					// (string) nick to specify in connect NICK command
		$pass = ''			// (string) optional password for server
	) {
		$this->autonick = 1;
		
		$this->sets = array();
		$this->sets['timestamp'] = true;

		if($ssl){		// if we use ssl we must prefix our connect host with ssl://
			$cntserver = 'ssl://'.$server;
		} else {
			$cntserver = $server;
		}

		$this->sp = fsockopen($cntserver, $port, $errno, $errstr, 10);

		if(!$this->sp){
			$this->addout('Connect error: '.$errno.' - '.$errstr);
			return 1;		// in case of error: output the error and exit this function
		}

		//echo socket_set_blocking($this->sp, 0);

		// because PASS, NICK and USER commands are also required for connect
		// we'll run them here
		// USER and PASS aren't changed later, so we send raw data to socket here
		// for NICK we use the setnick() function

		if(!empty($pass))
			$this->sendline('PASS '.$pass);
		$this->snick($nick);
		$this->sendline('USER '.$username.' '.$hostname.' '.$servername.' :'.$realname);

		// well, this should be it
		// processing of the data we got back will not be done here
		// incorrect USER params are ignored
		// incorect PASS params will be seen and will kill the connection
		// incorect NICK params will be passed to nick()
		// a PING will just be processed by pingreply()
		// (we hope)

		return 0;
	}

	// note that this function will hang the script while nothing is recieved
	// so users cannot send something, i hope to solve that in the ncurses interface
	// for bots this shouldn't be much of a problem
	function procline ()
	{
		$this->line = fgets($this->sp);		// this should ONLY be called by procline()	

		if(substr($this->line, -1) != "\n")
		{
			return 1;
		}

		// This is all ugly, really. Backwards compatibility.
		$this->line = trim($this->line);
		$this->ex = parse_line($this->line);
		//$this->msg = implode(array_slice($this->ex, 1), " ");
		$this->msg = $this->ex[count($this->ex) - 1];
		//msgf = implode(array_slice($ex, 1), " "); // same as $msg, except without the command prefix.
		$this->sender = explode("!", $this->ex[0]);
		$this->sender = $this->sender[0];

/*
		$this->line = trim($this->line);

		$this->msg = substr(strstr(substr($this->line, 1), ':'), 1);

		// there are lots of different messages we can get
		// we'll have to process all of them
		// i'll add an example line of what it detects

		$this->ex = explode(' ', $this->line);

		$this->sender = explode('!', $this->ex[0]);
		$this->sender = substr($this->sender[0], 1);
*/

		if($this->ex[0] == 'NOTICE' || ($this->ex[1] == 'NOTICE' && !preg_match("/@/i", $this->ex[0])))
			$this->procservernotice();
		// detects > NOTICE AUTH :*** Checking Ident
		// and > :omega.rizenet.org NOTICE AUTH :*** Checking ident...


		if($this->ex[1] == 'NOTICE' && preg_match("/@/i", $this->ex[0])){
			if($this->ex[2] == $this->usernick){
				$this->procusernotice();
				// detects > :rainman__!~rainman@127.0.0.1 NOTICE rian :hello
			} else {
				$this->procchannotice();
				// detects > :rainman__!~rainman@127.0.0.1 NOTICE #test :hello
			}
		}

		if($this->ex[1] == 'PRIVMSG'){
			if($this->ex[2] == $this->usernick){
				$this->procuserprivmsg();
				// detects > :rainman__!~rainman@127.0.0.1 PRIVMSG rian :hello
			} else {
				$this->procchanprivmsg();
				// detects > :rainman__!~rainman@127.0.0.1 PRIVMSG #test :hello
			}
		}

		if(((int)$this->ex[1]) != 0)
			$this->procservermsg();
		// detects > :Darkwired 375 rian :- Darkwired Message of the Day -
		// and messages with other numbers

		if($this->ex[1] == 'MODE'){
			if($this->ex[2] == $this->usernick){
				$this->procusermode();
				// detects > :rian MODE rian :+i
			} else {
				$this->procchanmode();
				// detects > :rainman__!~rainman@127.0.0.1 MODE #test +o rian
				// and > :Darkwired MODE #test2 +nt
			}
		}

		if($this->ex[1] == 'JOIN')
			$this->procchanjoin();

		if($this->ex[1] == 'PART')
			$this->procchanpart();

		if($this->ex[1] == 'KICK')
			$this->procchankick();

		if($this->ex[1] == 'QUIT')
			$this->procquit();

		if($this->ex[1] == 'NICK')
			$this->procnick();

		if($this->ex[0] == 'PING')
			$this->procping();

		if($this->ex[0] == 'PONG')
			$this->procpong();

		if($this->ex[1] == 'TOPIC')
			$this->proctopic();

		return 0;
	}

	//these are the S functions, that send data to the sock

	function snick($nick){
		$this->sendline('NICK '.trim($nick));
		$this->prevnick = $this->usernick;
		$this->usernick = trim($nick);
	}

	function sjoin($chan){
		$this->sendline('JOIN '.trim($chan));
		$this->chan = trim($chan);
		if(in_array(trim($chan),  $this->chans)){
			for($x = 0; $x < count($this->chans); $x++){
				if($this->chans[$x] == trim($chan))
					$this->chans[$x] = '';
			}
		}
		$this->chans[] = trim($chan);
	}

	function soper($name, $pass){
		$this->sendline('OPER '.trim($name).' '.trim($pass));
	}

	function skick($chan, $victim, $reason = "Expelled"){
		$this->sendline('KICK '.trim($chan).' '.trim($victim).' :'.trim($target));
	}

	function smode($target, $mode){
		$this->sendline('MODE '.trim($target).' '.trim($mode));
	}

	function spart($chan, $reason = 'Parting'){
		if(empty($chan))
			$chan = $this->chan;
		$this->sendline('PART '.trim($chan).' :'.trim($reason));

		for($x = 0; $x < count($this->chans); $x++){
			if($this->chans[$x] == trim($chan))
				$this->chans[$x] = '';
		}

		$csl = 0;
		foreach($this->chans as $fc){
			if(!empty($fc))
				$csl++;
		}

		if($csl > 0){
			if($chan == $this->chan){
				for($x = count($this->chans)-1; $x >= 0; $x--){
					if(!empty($this->chans[$x])){
						$this->chan = $this->chans[$x];
						$x = -1;
					}
				}
			}

		} else {
			$this->chan = '';
			$this->chans = array();;
		}
	}

	function squit($reason){
		$reasont = trim($reason);
		if(empty($reason))
			$reason = 'Leaving';
		$this->sendline('QUIT :'.trim($reason));
	}

	function sprivmsg($target, $msg, $disp = true){
		$this->sendline('PRIVMSG '.trim($target).' :'.trim($msg));
		if($disp)
			$this->addout('msg '.$target.' '.$msg);
	}

	function snotice($target, $msg){
		$this->sendline('NOTICE '.trim($target).' :'.trim($msg));
		$this->addout('notice -'.$target.'- '.$msg);
	}

	function stopic($target, $new = ''){
		if(trim($new) != ''){
			$this->sendline('TOPIC '.$target.' :'.$new);
		} else {
			$this->sendline('TOPIC '.$target);
		}
	}

	function snames($target){
		$this->sendline('NAMES '.$target);
	}

	function say($msg){
		$this->sprivmsg($this->chan, trim($msg), false);
		$this->addout('<'.$this->usernick.'> '.$msg);
	}

	function sversion($target){
		if(empty($target))
			$target = $this->chan;
		$this->sprivmsg($target, chr(1).'VERSION'.chr(1), false);
		$this->addout('CTCP VERSION '.$target);
	}

	function set($var, $val){
		$this->sets[$var] = $val;
	}

	function saction($msg){
		$this->sprivmsg($this->chan, chr(1).'ACTION '.trim($msg).chr(1),false);
		$this->addout('*'.$this->usernick.'/'.$this->chan.' '.trim($msg).'*');
	}


	/*-----------------------------------
	BELOW FUNCTIONS FOR INTERNAL USE ONLY
	-----------------------------------*/
	function proctopic(){
		$this->addout($this->sender.' set topic for '.$this->ex[2].' to '.$this->msg);
	}

	function procpong(){
	}

	function procping(){
		$this->sendline('PONG '.$this->ex[1]);
	}

	function procnick(){
		$this->addout($this->sender.' is now known as '.substr($this->ex[2], 1));
	}

	function procquit(){
		$this->addout('Quit: '.$this->sender.' ['.$this->msg.']');
	}

	function procchankick(){
		$this->addout($this->ex[3].' was kicked off '.$this->ex[2].' by '.$this->sender.': '.$this->msg);
	}

	function procchanpart(){
		$this->addout($this->sender.' has left channel '.$this->ex[2].': '.$this->msg);
	}

	function procchanjoin(){
		$this->addout($this->sender. ' has joined '.substr($this->ex[2], 1));
	}

	function procchanmode(){
		$this->addout('chanmode '.$this->ex[2].' '.$this->ex[3].' '.$this->ex[4].' by '.$this->sender);
	}

	function procusermode(){
		$this->addout('usermode '.$this->ex[2].' '.$this->msg.' by '.$this->sender);
	}

	function procservermsg(){
		//we can't use $this->msg here, it can't handle :Darkwired 254 rain- 2 :channels formed
		$ar = $this->ex;
		$ar[0] = '';
		$ar[1] = '';
		$ar[2] = '';
		$mg = implode(' ', $ar);
		$mg = trim($mg);
		if($this->autonick){
			$this->autonick++;

			if($this->autonick > 5)
				$this->autonick = false;

			if($this->ex[1] == 437 || $this->ex[1] == 433)
				$this->snick($this->usernick.'_');

		} else {
			if($this->ex[1] == 437 || $this->ex[1] == 433)
				$this->snick($this->prevnick);
		}



		if(substr($mg, 0, 1) == ':')
			$mg = substr($mg, 1);

		$this->addout('--'.$this->sender.'-'.$this->ex[1].' '.$mg);
	}

	function procuserprivmsg(){
		if($this->msg == chr(1).'VERSION'.chr(1)){
			$this->addout($this->sender.' requested version from '.$this->ex[2]);
			$this->sendline('NOTICE '.$this->sender.' :'.chr(1).'VERSION ircc'.chr(1));
		} else {
			$this->addout('('.$this->sender.') '.$this->msg);
		}
	}

	function procchanprivmsg(){
		if($this->msg == chr(1).'VERSION'.chr(1)){
			$this->addout($this->sender.' requested version from '.$this->ex[2]);
			$this->sendline('NOTICE '.$this->sender.' :'.chr(1).'VERSION ircc'.chr(1));
		} elseif(preg_match('/^'.chr(1).'ACTION(.*?)'.chr(1).'$/i', $this->msg)){
			$this->addout('*'.$this->sender.'/'.$this->ex[2].' '.substr($this->msg, 8, strlen($this->msg)-9).'*');
		} else {
			if($this->chan == $this->ex[2]){
				$this->addout('<'.$this->sender.'> '.$this->msg);
			} else {
				$this->addout('<'.$this->ex[2].'/'.$this->sender.'> '.$this->msg);
			}
		}
	}

	function procusernotice(){
		if(preg_match('/^'.chr(1).'VERSION(.*?)'.chr(1).'$/i', $this->msg)){
			$this->addout('CTCP VERSION reply from '.$this->sender.': '.substr($this->msg, 9, strlen($this->msg)-10));
		} else {
			$this->addout('-'.$this->sender.'- '.$this->msg);
		}
	}

	function procchannotice(){
		$this->addout('-'.$this->ex[2].'/'.$this->sender.'- '.$this->msg);
	}

	function procservernotice(){
		$this->addout('NOTICE '.$this->msg);
	}

	function addout($addtoout){		// adds a string to the output
		if($this->sets['timestamp'])
			$this->output .= date("[H:i] ").$addtoout."\n";
		else
			$this->output .= $addtoout."\n";
	}


	function getout(){
		$out = $this->output;
		$this->output = '';
		return $out;
	}

	function sendline($data){					// sends a line to our irc
		if(!@fwrite($this->sp, $data."\n")){
			$this->addout('Warning: error writing ['.$data.']');
		}
	}
}


?>
