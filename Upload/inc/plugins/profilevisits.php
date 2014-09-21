<?php

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

	if(!defined("IN_MYBB")) {
	    die("Hacking Attempt.");
	}	
	
	if (isset($mybb->settings['profilevisits_enabled']) && $mybb->settings['profilevisits_enabled'] == 1) {
		$plugins->add_hook("member_profile_end", "profilevisits_parse");
		$plugins->add_hook('misc_start', 'profilevisits_popup');
		$plugins->add_hook('misc_start', 'profilevisits_moderation');	
		$plugins->add_hook('task_hourlycleanup', 'profilevisits_cleanup'); // To keep things as simple as possible, this plugin hooks into the hourly cleanup task to purge the profile visits cache. 
	}
	function profilevisits_info() {
		global $lang;
		$lang->load("profilevisits");
		return array (
			'name'			=> $lang->profile_visits,
			'description'	=> $lang->profile_visits_desc,
			'website'		=> 'http://community.mybb.com',
			'author'		=> 'Darth Apple',
			'authorsite'	=> 'http://www.makestation.net',
			'version'		=> '1.0',
			"compatibility"	=> "18*"
		);
	}
	function profilevisits_install () {
		global $lang, $db;
		$lang->load("profilevisits");
		
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `profilevisits` BIGINT DEFAULT '0';");
		
		if(!$db->table_exists($prefix.'profilevisits_log')) {
			$db->query("CREATE TABLE ".TABLE_PREFIX."profilevisits_log (
				VID BIGINT unsigned NOT NULL auto_increment,
				uid int unsigned NOT NULL, 
				profileID int unsigned NOT NULL,  
				hidden boolean, 
				date int(10) NOT NULL,
	  			PRIMARY KEY (VID)
				) 
				".$db->build_create_table_collation().";"
			);
		}	
			
		if(!$db->table_exists($prefix.'profilevisits_cache')) {
			$db->query("CREATE TABLE ".TABLE_PREFIX."profilevisits_cache (
				VID BIGINT unsigned NOT NULL auto_increment,
				uid int unsigned NOT NULL, 
				profileID int unsigned NOT NULL,  
				IP varbinary(16), 
				date int(10) NOT NULL,
	  			PRIMARY KEY (VID)
				) 
				".$db->build_create_table_collation().";"
			); 
			
			
		}
		
		$templates = array();
		
		$templates['member_profilevisits'] = '
		<tr>
			<td class="{$bg_color}"><strong>{$lang->profilevisits}</strong></td>
			<td class="{$bg_color}">{$visit_count} ({$view_latest})</td>
		</tr>'; 
		
		$templates['profilevisits_popup'] = '
<div class="modal">
	<div style="overflow-y: auto; max-height: 400px;">
		<table cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" style="border-spacing: 0px; padding: 2px; -webkit-border-radius: 7px; -moz-border-radius: 7px; border-radius: 7px;">
		<tr>
			<td class="thead" colspan="2">
				<div><strong>{$lang->profilevisits_of} {$profile_username}</strong></div>
			</td>
		</tr>
		<tr>
			<td class="tcat" colspan="2">
				<div><strong>{$lang->profilevisits_thead}</strong></div>
			</td>
		</tr>		
		
		{$visits}
{$moderation}
		</table>
	</div>
</div>';

		$templates['profilevisits_user'] = '
<tr>
	<td class="{$bonline_alt}" width="1%">
		<div class="buddy_avatar float_left"><img src="{$visitor[\'avatar\'][\'image\']}" alt="" {$visitor[\'avatar\'][\'width_height\']} style="margin-top: 3px;" /></div>
	</td>
	<td class="{$bonline_alt}">
		{$profile_link}
		<div class="buddy_action">
			<span class="smalltext">{$active}<br /></span>
		</div>
	</td>
</tr>
	';		
		
		foreach($templates as $title => $template_new){
			$template = array('title' => $db->escape_string($title), 'template' => $db->escape_string($template_new), 'sid' => '-1', 'dateline' => TIME_NOW, 'version' => '1800');
			$db->insert_query('templates', $template);
		}
		
		$setting_group = array (
			'name' => 'profilevisits', 
			'title' => $db->escape_string($lang->profile_visits),
			'description' => $db->escape_string($lang->profile_visits_desc),
			'disporder' => $rows+3,
			'isdefault' => 0
		); // yes, we're escaping data from language strings, just in case someone decides to put something suspicious into a language translation file. 
		
		$group['gid'] = $db->insert_query("settinggroups", $setting_group); // inserts new group for settings into the database. 
		
		$settings = array();
		
		$settings[] = array(
			'name' => 'profilevisits_enabled',
			'title' => $db->escape_string($lang->profilevisits_enable),
			'description' => $db->escape_string($lang->profilevisits_enable_desc),
			'optionscode' => 'yesno',
			'value' => '1',
			'disporder' => 1,
			'isdefault' => 0,
			'gid' => $group['gid']
		);
					
		$settings[] = array(
			'name' => 'profilevisits_honor_hidden_users',
			'title' => $db->escape_string($lang->profilevisits_honor_hidden_users),
			'description' => $db->escape_string($lang->profilevisits_honor_hidden_users_desc),
			'optionscode' => 'yesno',
			'value' => '0',
			'disporder' => 2,
			'isdefault' => 0,			
			'gid' => $group['gid']
		);		
		
		$settings[] = array(
			'name' => 'profilevisits_log_own',
			'title' => $db->escape_string($lang->profilevisits_log_own),
			'description' => $db->escape_string($lang->profilevisits_log_own_desc),
			'optionscode' => 'yesno',
			'value' => '0',
			'disporder' => 3,
			'isdefault' => 0,			
			'gid' => $group['gid']
		);				
	
		$settings[] = array(
			'name' => 'profilevisits_groups',
			'title' => $db->escape_string($lang->profilevisits_groups),
			'description' => $db->escape_string($lang->profilevisits_groups_desc),
			'optionscode' => 'groupselect',
			'value' => '-1',
			'disporder' => 4,
			'isdefault' => 0,
			'gid' => $group['gid']
		);
		
		$settings[] = array(
			'name' => 'profilevisits_modgroups',
			'title' => $db->escape_string($lang->profilevisits_modgroups),
			'description' => $db->escape_string($lang->profilevisits_modgroups_desc),
			'optionscode' => 'groupselect',
			'value' => '3,4',
			'disporder' => 5,
			'isdefault' => 0,
			'gid' => $group['gid']
		);		
		
		$settings[] = array(
			'name' => 'profilevisits_numresults',
			'title' => $db->escape_string($lang->profilevisits_numresults),
			'description' => $db->escape_string($lang->profilevisits_numresults_desc),
			'optionscode' => 'text',
			'value' => '6',
			'disporder' => 6,
			'isdefault' => 0,
			'gid' => $group['gid']
		);
		
		$settings[] = array(
			'name' => 'profilevisits_cachetime',
			'title' => $db->escape_string($lang->profilevisits_cachetime),
			'description' => $db->escape_string($lang->profilevisits_cachetime_desc),
			'optionscode' => 'text',
			'value' => '15',
			'disporder' => 7,
			'isdefault' => 0,
			'gid' => $group['gid']
		);		
		
		foreach($settings as $array => $setting) {
			$db->insert_query("settings", $setting);
		}
		rebuild_settings();
	}
	
	function profilevisits_uninstall () {
		global $db;
	
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP `profilevisits`;");
		
		if($db->table_exists('profilevisits_log')) {
			$db->drop_table('profilevisits_log');
		}	
		
		if($db->table_exists('profilevisits_cache')) {
			$db->drop_table('profilevisits_cache');
		}			
		
		$templates = array('member_profilevisits', 'profilevisits_popup', 'profilevisits_user'); // remove templates
		foreach($templates as $template) {
			$db->delete_query('templates', "title = '{$template}'");
		}
		
		$query = $db->simple_select('settinggroups', 'gid', 'name = "profilevisits"'); // remove settings
		$groupid = $db->fetch_field($query, 'gid');
		$db->delete_query('settings','gid = "'.$groupid.'"');
		$db->delete_query('settinggroups','gid = "'.$groupid.'"');
		rebuild_settings();	
	}
	
	function profilevisits_is_installed () {
		global $db;
		if($db->table_exists('profilevisits_log')) {
			return true;
		}
		return false;
	}	
	
	function profilevisits_activate () {
		require MYBB_ROOT.'inc/adminfunctions_templates.php';
		find_replace_templatesets('member_profile', '#{\$warning_level}#', '{$warning_level} <!-- ProfileVisits -->{$profilevisits}<!-- /ProfileVisits -->');
	}
	
	function profilevisits_deactivate () {
		require MYBB_ROOT.'inc/adminfunctions_templates.php';
		find_replace_templatesets('member_profile', '#\<!--\sProfileVisits\s--\>(.+)\<!--\s/ProfileVisits\s--\>#is', '', 0);
	
	}
	
	
	
	function profilevisits_parse () {
		global $templates, $db, $mybb, $lang, $profilevisits, $bg_color, $session, $memprofile;
		// parse profile visits
		
		if ($mybb->settings['profilevisits_enabled'] != 1) {
			return; // Profile Visits is not enabled. 
		}
		
		$lang->load("profilevisits");
		
		if ($bg_color == "trow2") $bg_color = "trow1";
		
		if ($mybb->input['uid']) {
			$profileID = (int) $mybb->input['uid'];
		}
		
		else {
			$profileID = (int) $mybb->user['uid']; // user is viewing own profile. No need to adjust visits count. 
		}
		
		if ($profileID != $mybb->user['uid']) {
			$parse_invisible = profilevisits_honor_invisible ();
		}	
		
		if ((profilevisits_permissions($mybb->settings['profilevisits_groups'], $mybb->user['usergroup'], $mybb->user['additionalgroups'])) && ($parse_invisible) && (profilevisits_log_own($profileID))) {
			$query = $db->query("
				SELECT uid, VID, date
				FROM ".TABLE_PREFIX."profilevisits_cache
				WHERE profileID = ".(int) $profileID." AND (uid = ".(int) $mybb->user['uid'].") ORDER BY VID DESC LIMIT 1;
			"); // Check the cache to ensure that a user cannot raise a user's statistics by rapidly reloading the page. 
			
			while($data = $db->fetch_array($query)) {		
				$result['date'] = $data['date'];
			} 
			
			if (((time() - $result['date']) > ($mybb->settings['profilevisits_cachetime'] * 60)) || (!isset($result['date']))) {
				$db->query("UPDATE ".TABLE_PREFIX."users SET profilevisits = profilevisits + 1 WHERE uid=".(int) $profileID); // increment counter
				$array = array(
					"date" => time(),
					"profileID" => (int)$profileID,
					"IP" => $db->escape_binary($session->packedip),
					"uid" => (int) $mybb->user['uid']
				);
							
				$db->insert_query("profilevisits_cache", $array); // insert new result into cache
			}
			
			$query = $db->query("
				SELECT uid, VID, date
				FROM ".TABLE_PREFIX."profilevisits_log
				WHERE profileID = ".(int) $profileID." ORDER BY VID DESC LIMIT 1;
			"); // ensures that the same user is not inserted multiple times in a row. 
	
			while($data = $db->fetch_array($query)) {
				$result['uid'] = $data['uid']; // fetch UID
				$result['date'] = $data['date'];
				$vid = $data['VID']; // visit ID
				$result['IP'] = $data['IP'];
			} 			
			
			if ((($result['uid'] != $mybb->user['uid'])) && ($mybb->user['uid'] != 0)) {
				$array = array(
					"date" => time(),
					"profileID" => (int)$profileID,
					// "IP" => $db->escape_binary($session->packedip),
					"uid" => (int) $mybb->user['uid']
				);
						
				$db->insert_query("profilevisits_log", $array);
			}
			else {
				$array = array(
					"date" => time(),
				);
				
				$db->update_query("profilevisits_log", $array, "VID = ". (int)$vid);						
			}		
				
		}
		
		$visit_count = my_number_format(intval($memprofile['profilevisits']));
		$view_latest = '<a href="#" onclick="MyBB.popupWindow(\'misc.php?action=profilevisits&pid='.(int) $profileID.'\', null, true); return false;">'.$lang->profilevisits_viewlatest.'</a>';
		
		eval("\$profilevisits = \"".$templates->get("member_profilevisits")."\";"); 
	} 

	function profilevisits_popup () {
		global $lang, $templates, $db, $mybb, $session;
		
		if ($mybb->input['action'] == "profilevisits") {
			$lang->load("profilevisits");
			// $lang->load("misc");
			if ((empty($mybb->input['pid'])) || ($mybb->request_method != "get")) {
				// 
			}
			else {
				$visits = null;

				$query = $db->query("
					SELECT username
					FROM ".TABLE_PREFIX."users
					WHERE uid = ". (int) $mybb->input['pid']);

				while($data = $db->fetch_array($query)) {
					$profile_username = $data['username']; // fetch username of profile
				} 
				
				$profileID = (int) $mybb->input['pid'];

				$query = $db->query("
					SELECT p.uid, p.date AS visit_date, u.uid, u.avatar, u.username, u.avatardimensions
					FROM ".TABLE_PREFIX."profilevisits_log p
					LEFT JOIN " . TABLE_PREFIX . "users u ON p.uid = u.uid
					WHERE (p.profileID =". (int) $profileID.") ORDER BY VID DESC LIMIT ".(int) $mybb->settings['profilevisits_numresults']
				);
					
				$i = 0;
				while($data = $db->fetch_array($query)) {
					$username = $data['username'];
					$date = $data['visit_date'];
					
					$profile_link = build_profile_link($username, intval($mybb->input['pid']), '_blank', 'if(window.opener) { window.opener.location = this.href; return false; }');
				
					if($date) {
						$active = $lang->profilevisits_active." ".my_date('relative', $date);
					}
					else {
						$active = $lang->profilevisits_active . $lang->profilevisits_never;
					}

					$visitor['avatar'] = format_avatar(htmlspecialchars_uni($data['avatar']), $data['avatardimensions'], '44x44');
					$bonline_alt = alt_trow();
					eval("\$visits .= \"".$templates->get("profilevisits_user")."\";");			
					$i++;
				} 
				
				if ($i == 0) {
					$bonline_alt = alt_trow();
					$visits = "<tr><td colspan='2' class='{$bonline_alt}'>{$lang->profilevisits_nothing}</td></tr>";
				}
				
				if (profilevisits_permissions($mybb->settings['profilevisits_modgroups'], $mybb->user['usergroup'], $mybb->user['additionalgroups'])) {
					$moderation = profilevisits_modlinks ($profileID);
				}
				else {
					$moderation = null;
				}
				
				eval("\$profilevisits = \"".$templates->get("profilevisits_popup", 1, 0)."\";");
				echo $profilevisits;
			}	
		exit;			
			
		}
	}
	
	
	function profilevisits_permissions ($allowed, $usergroup, $additionalgroups) {
		global $mybb; 
		if (empty($allowed)) {
			return false; // no need to check for permissions if no groups are allowed. 
		}
		if ($allowed == "-1") {
			return true; // no need to check for permissions if all groups are allowed. 
		}
		
		$allowed = explode(",", $allowed);
		$groups = array();
		$groups[0] = (int)$usergroup; 
		$add_groups = explode(",", $additionalgroups);
		$count = 1;
		foreach($add_groups as $new_group) {
			$groups[$count] = $new_group;
			$count++;
		}
		foreach ($allowed as $allowed_group) {
			if (in_array($allowed_group, $groups)) {
				return true;
			}
		}
		return false;
	}
	
	function profilevisits_verify_permissions ($allowed, $usergroup, $additionalgroups) {
		// verify permissions, generate error page on fail. 
		if (profilevisits_permissions($allowed, $usergroup, $additionalgroups)) {
			return; 
		}
		else {
			error_no_permission();
		}
	}
	

	function profilevisits_honor_invisible () {
		// this function checks whether a user can be logged based on the plugin's "honor_hidden" setting. If this setting is enabled, invisible users won't be logged. 
		global $mybb;
		if (!empty($mybb->user['uid'])) {
			if (($mybb->user['hidden'] == 1) && ($mybb->settings['profilevisits_honor_hidden'] == 1)) {
				return false; 
			}
			else {
				return true;
			}
		}
		return false; 
	}
	
	function profilevisits_modlinks ($user) {
		global $mybb, $lang;
		$lang->load("profilevisits");
		$uid = (int) $user;
		$onclick = "onclick=' return confirm(\"".$lang->profilevisits_confirm."\");'";
		$moderation = "<tr><td class='trow2' colspan='2'><strong>{$lang->profilevisits_moderation}</strong>";
		$moderation .= "[<a href='misc.php?action=profilevisits_clearcounter&uid={$uid}&postkey={$mybb->post_code}' {$onclick}>{$lang->profilevisits_clearcounter}</a>] ";
		$moderation .= " [<a href='misc.php?action=profilevisits_clearvisits&uid={$uid}&postkey={$mybb->post_code}' {$onclick}>{$lang->profilevisits_clearvisits}</a>]";
		$moderation .= "</td></tr>";
		return $moderation; 
	}
	
	function profilevisits_moderation () {
		global $mybb, $db, $lang;
		$lang->load("profilevisits");
		
		if (((($mybb->input['action'] == "profilevisits_clearcounter")) || ($mybb->input['action'] == "profilevisits_clearvisits")) && ($mybb->request_method == "get")) {
			verify_post_check($mybb->input['postkey']);
			profilevisits_verify_permissions($mybb->settings['profilevisits_modgroups'], $mybb->user['usergroup'], $mybb->user['additionalgroups']);
			
			if ($mybb->input['action'] == "profilevisits_clearcounter") {
				$uid = (int) $mybb->input['uid'];
				if (empty($uid)) {
					// no UID defined
					error($lang->profilevisits_no_uid);
				}
				else {
					// reset counter
					$array = array(
						"profilevisits" => 0,
					);
					$db->update_query("users", $array, "uid = ". (int)$uid);
					redirect("member.php?action=profile&uid=".(int) $uid, $lang->profilevisits_clear_success);		
				}
			}
			
			if ($mybb->input['action'] == "profilevisits_clearvisits") {
				$uid = (int) $mybb->input['uid'];
				if (empty($uid)) {
					// no UID defined
					error($lang->profilevisits_no_uid);
				}
				else {
					// delete profile visits
					$db->delete_query("profilevisits_log", "profileID = ".(int)$uid);
					redirect("member.php?action=profile&uid=".(int) $uid, $lang->profilevisits_delete_success);	
				}
			}
		}
	}

	function profilevisits_log_own ($profileID) {
		// returns true if profilevisits logs visits to own profiles
		global $mybb;
		if ((intval($profileID) == $mybb->user['uid']) && ($mybb->settings['profilevisits_log_own'] == 0)) {
			return false;
		}
		return true;
	}


	function profilevisits_cleanup ($args) {
		// delete old results cached into the profile visits log
		global $db, $mybb;		
		$cutoff = (time() - ((int) $mybb->settings['profilevisits_cachetime'] * 60)); 
		$db->delete_query("profilevisits_cache", "date < ".(int) $cutoff.""); // delete old cached results
	}
