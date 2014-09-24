NOTICE: THIS PLUGIN IS A BETA RELEASE, AND IS NOT GUARANTEED TO WORK PROPERLY ON YOUR FORUM. Profile Visits will be released officially on the MyBB mod site once it is finished. In the meantime, you can help test what's already done via this repository. 

Latest Visitors on Profiles is a simple plugin for MyBB 1.8 that adds a profile visits counter and a latest visits log to all profiles on the forum. 

MyBB version: 1.8.X
Plugin version: 1.0 beta 3
Author: Darth-Apple
License: GNU GPL, version 3. 

Key features
	- (Optional) Profile visits counter
	- Profile counter caching (prevents users from rapidly reloading a profile to bump their visit count)
	- Usergroup based permissions
	- Ability to reset the counter and to purge visits
	- Jquery popup featuring the latest profile visits
	- Ability to hide invisible users from the profile visits log
	- Ability to configure which usergroups are logged
	- Ability to configure whether visits to users' own profiles are counted. 
	
Installation: 
	- Upload the contents of the /Upload folder to your MyBB root folder.
	- Install and activate "Latest Visitors on Profiles" via the ACP. 
	
Configuration & Notes: 
	- Once this plugin is installed an activated, you may configure this plugin via ACP -> Configuration -> "Latest Visitors on Profile"
	- Note that if the guest usergroup is enabled for profile visits logging, only the counter will be incremented. Guest users will never display explicitly in the latest visits log. 
	- Visits are automatically deleted after a specified interval, which is set to 30 days by default. This is to prevent the database from growing massive from old visitor logs. You can set this expiration interval in the plugin settings. (ACP -> Configuration -> "Latest Visitors on Profile")

License and Copyright: 

 /*     This file is part of Profile Visits

    Profile Visits is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Profile Visits is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Profile Visits.  If not, see <http://www.gnu.org/licenses/>. */
