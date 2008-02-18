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

		Function:
			- read from stdin and throw it in a file

---------------------------------------------------------*/

#include <ncurses.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>
#include <stdio.h>
#include <curses.h>


int main(){
	char ch;
	initscr();



	ch = getch();

	printf("hello %c torc!\n", ch);
}

