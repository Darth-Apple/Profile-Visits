Latest Visitors on Profiles is a simple plugin for MyBB 1.8 that adds a profile visits counter and a latest visits log to all profiles on the forum. Additionally, this plugin supports usergroup based permissions, visit caching, jquery modal boxes, and other related enhancements.  

MyBB version: 1.8.1 or newer
Plugin version: 1.0
Author: Darth-Apple
License: GNU GPL, version 3. 

Key features
	- (Optional) Profile visits counter
	- Profile counter caching (prevents users from rapidly reloading a profile to bump their visit count)
	- Usergroup based permissions
	- Ability to reset the counter and to purge visits
	- Jquery popup featuring the latest profile visits
	- Honors invisible user permissions (e.g. only usergroups allowed to view invisible users will see invisible users in the visitor logs. )
	- Ability to configure which usergroups are logged
	- Ability to configure whether visits to users' own profiles are counted
	
Installation: 
	- Upload the contents of the /Upload folder to your MyBB root folder.
	- Install and activate "Latest Visitors on Profiles" via the ACP. 
	- Note that this plugin requires at least MyBB 1.8.1 in order to be compatible. If you are running 1.8.0, please upgrade to at least 1.8.1 before installing this plugin. 
	
Configuration & Notes: 
	- Once this plugin is installed an activated, you may configure this plugin via ACP -> Configuration -> "Latest Visitors on Profile"
	- Note that if the guest usergroup is enabled for profile visits logging, only the counter will be incremented. Guest users will never display explicitly in the latest visits log. 
	- This plugin generates additional queries due to the loading of templates, cache checking, visitor logging, etc. The usage of queries can be reduced by disabling the visits counter on user profiles. 
	- Visits are automatically deleted after a specified interval, which is set to 30 days by default. This is to prevent the database from growing massive from old visitor logs. You can set this expiration interval in the plugin settings. (ACP -> Configuration -> "Latest Visitors on Profile")
	- The "load more" functionality will automatically load twice the number of results that are loaded by default on the popup. If, for example, five results are configured to display by default, the "load more" functionality will load an additional 10 results. 
	
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
