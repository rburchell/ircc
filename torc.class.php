#!/usr/local/bin/php
<?php
/*
 * ircc - a handy, portable console irc client
 *
 * Copyright (C) 2008 Robin Burchell <w00t@inspircd.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 */

define('IRCC_VER', 'ircc-0.01');	/* Self explanatory. */
define('BUFFER_STATUS', 0);		/* The root buffer will always be 0, status. */
define('BUFFER_CURRENT', -1);		/* Magic constant to indicate the current buffer. */

require("client.class.php");		/* Main client class. Contains socket loop, instances of child classes, etc. */
require("buffer.class.php");		/* Buffer manipulation class. Used mostly inside display class. */
require("ncurse.class.php");		/* Display class. Used to manipulate interface stuff. */
require("irc.class.php");		/* IRC class. Interfaces with IRC. */
require("utils.class.php");		/* Utility functions used throughout the client. */


// Set up.
set_error_handler(array("Utils", "ErrorHandler"));
date_default_timezone_set("UTC"); // XXX guess the tz?
set_time_limit(0);
error_reporting(E_ALL);
ob_implicit_flush();

$oClient = new Client();
$oClient->Run();
?>
