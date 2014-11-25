<?php

 /*     
	This file is part of Profile Visits

    Profile Visits is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Profile Visits is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Profile Visits.  If not, see <http://www.gnu.org/licenses/>.
    
    MyBB version: 1.8.1 or newer
	Plugin version: 1.0 beta 4
	Author: Darth-Apple
	License: GNU GPL, version 3
     */
    
	if(!defined("IN_MYBB")) {
	    die("Hacking Attempt.");
	}	
	
	if (isset($mybb->settings['profilevisits_enabled']) && $mybb->settings['profilevisits_enabled'] == 1) {
		$plugins->add_hook("member_profile_end", "profilevisits_parse");
		$plugins->add_hook('misc_start', 'profilevisits_popup');
		$plugins->add_hook('misc_start', 'profilevisits_moderation');	
		$plugins->add_hook('task_hourlycleanup', 'profilevisits_cleanup_cache'); // clean cache
		$plugins->add_hook('task_dailycleanup_end', 'profilevisits_cleanup_visits'); // clear expired visits
		$plugins->add_hook('xmlhttp', 'profilevisits_ajax');
		$plugins->add_hook("build_friendly_wol_location_end", "profilevisits_online");
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
		<td class="{$bg_color}"><strong>{$visit_count}</strong> [{$view_latest}]</td>
</tr>'; 
		
		$templates['profilevisits_popup'] = '
<div class="modal">
	<div style="overflow-y: auto; max-height: 500px;">
		<table cellspacing="{$theme[\'borderwidth\']}" cellpadding="3" class="tborder" style="border-spacing: 0px; padding: 2px; -webkit-border-radius: 7px; -moz-border-radius: 7px; border-radius: 7px;">
			<tr>
				<td class="thead" colspan="2">
					<div><strong>{$lang->profilevisits_of} {$profile_username}</strong></div>
				</td>
			</tr>
			<tr>
				<td class="tcat" colspan="2">
					<div style="display: inline; float: left;"><span style="font-weight: bold;" title="({$interval})">{$lang->profilevisits_thead}</span></div><div style="display: inline; " class="float_right">{$loadmore}</div>
				</td>
			</tr>		
			{$visits}
			{$moderation}	
		</table>
	</div>
</div>';

		$templates['profilevisits_user'] = '
<tr id="visit_{$i}" class="profileVisit">
	<td class="{$altbg}" width="1%" style="vertical-align: middle;">
		<div class="float_left"><img src="{$visitor[\'avatar\'][\'image\']}" alt="" height="44px" width="44px" style="margin-top: 3px; padding: 3px; border: 1px solid #ddd; background: #fff;" /></div>
	</td>
	<td class="{$altbg}">
		{$profile_link}
		<div>
			<span class="smalltext"><strong>{$lang->profilevisits_lastactive}</strong> {$active}<br /></span>
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
		); 
		
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
			'name' => 'profilevisits_enable_count',
			'title' => $db->escape_string($lang->profilevisits_enable_count),
			'description' => $db->escape_string($lang->profilevisits_enable_count_desc),
			'optionscode' => 'yesno',
			'value' => '1',
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
			'name' => 'profilevisits_numresults',
			'title' => $db->escape_string($lang->profilevisits_numresults),
			'description' => $db->escape_string($lang->profilevisits_numresults_desc),
			'optionscode' => 'numeric',
			'value' => '5',
			'disporder' => 4,
			'isdefault' => 0,
			'gid' => $group['gid']
		);			
	
		$settings[] = array(
			'name' => 'profilevisits_groups',
			'title' => $db->escape_string($lang->profilevisits_groups),
			'description' => $db->escape_string($lang->profilevisits_groups_desc),
			'optionscode' => 'groupselect',
			'value' => '-1',
			'disporder' => 5,
			'isdefault' => 0,
			'gid' => $group['gid']
		);
		
		$settings[] = array(
			'name' => 'profilevisits_modgroups',
			'title' => $db->escape_string($lang->profilevisits_modgroups),
			'description' => $db->escape_string($lang->profilevisits_modgroups_desc),
			'optionscode' => 'groupselect',
			'value' => '3,4',
			'disporder' => 6,
			'isdefault' => 0,
			'gid' => $group['gid']
		);						
		
		$settings[] = array(
			'name' => 'profilevisits_cachetime',
			'title' => $db->escape_string($lang->profilevisits_cachetime),
			'description' => $db->escape_string($lang->profilevisits_cachetime_desc),
			'optionscode' => 'numeric',
			'value' => '15',
			'disporder' => 7,
			'isdefault' => 0,
			'gid' => $group['gid']
		);	
		
		$settings[] = array(
			'name' => 'profilevisits_expire',
			'title' => $db->escape_string($lang->profilevisits_expire),
			'description' => $db->escape_string($lang->profilevisits_expire_desc),
			'optionscode' => 'numeric',
			'value' => '30',
			'disporder' => 8,
			'isdefault' => 0,
			'gid' => $group['gid']
		);				
		
		// insert the settings
		foreach($settings as $array => $setting) {
			$db->insert_query("settings", $setting); // lots of queries
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
		// parses profile visits and generates logs on user profiles. 
		global $templates, $db, $mybb, $lang, $profilevisits, $bg_color, $session, $memprofile;
		$lang->load("profilevisits");
		
		$profile_increment = 0; 
		$visit_count = null;
		$view_latest = null;
	
		if ($bg_color == "trow2") $bg_color = "trow1";
		
		if ($mybb->input['uid']) {
			$profileID = (int) $mybb->input['uid'];
		}
		
		else {
			$profileID = (int) $mybb->user['uid']; // user is viewing own profile. No need to adjust visits count. 
		}
		
		if ((profilevisits_permissions($mybb->settings['profilevisits_groups'], $mybb->user['usergroup'], $mybb->user['additionalgroups'])) && (profilevisits_log_own($profileID))) {
			if ($mybb->settings['profilevisits_enable_count'] == 1) {
				$query = $db->query("
					SELECT uid, VID, date
					FROM ".TABLE_PREFIX."profilevisits_cache
					WHERE profileID = ".(int) $profileID." AND (uid = ".(int) $mybb->user['uid'].") ORDER BY VID DESC LIMIT 1;
				"); 
			
				while($data = $db->fetch_array($query)) {		
					$result['date'] = $data['date'];
				} 
			
				if (((time() - $result['date']) > ($mybb->settings['profilevisits_cachetime'] * 60)) || (!isset($result['date']))) {
					$db->query("UPDATE ".TABLE_PREFIX."users SET profilevisits = profilevisits + 1 WHERE uid=".(int) $profileID); // increment counter
					$profile_increment = 1; // The current view count is loaded from $memprofile, which is parsed before this function is called. This allows Profile Visits to record the actual view count including the current visit. 
					
					$array = array(
						"date" => intval(time()),
						"profileID" => (int) $profileID,
						"IP" => $db->escape_binary($session->packedip),
						"uid" => (int) $mybb->user['uid']
					);
								
					$db->insert_query("profilevisits_cache", $array); // insert new result into cache
				}
			}
			
			$query = $db->query("
				SELECT uid, VID, date
				FROM ".TABLE_PREFIX."profilevisits_log
				WHERE profileID = ".(int) $profileID." ORDER BY VID DESC LIMIT 1;
			"); 
	
			while($data = $db->fetch_array($query)) {
				$result['uid'] = $data['uid']; 
				$result['date'] = $data['date'];
				$vid = $data['VID']; 
			} 			
		
			if ((($result['uid'] != $mybb->user['uid'])) && ($mybb->user['uid'] != 0)) {
				$array = array(
					"date" => intval(time()),
					"profileID" => (int)$profileID,
					"uid" => (int) $mybb->user['uid']
				);
					
				$db->insert_query("profilevisits_log", $array);
			}
			else if ($mybb->user['uid'] != 0) {
				$array = array(
					"date" => intval(time())
				);
			
				$db->update_query("profilevisits_log", $array, "VID = ". (int) $vid);						
			}			
		}
		
		if ($mybb->settings['profilevisits_enable_count'] == 1) {
			$visit_count = my_number_format(intval($memprofile['profilevisits'] + $profile_increment));
		}

		$view_latest = '<a href="#" onclick="MyBB.popupWindow(\'misc.php?action=profilevisits&uid='.(int) $profileID.'\', null, true); return false;">'.$lang->profilevisits_viewlatest.'</a>';
		
		
		eval("\$profilevisits = \"".$templates->get("member_profilevisits")."\";"); 
	} 


	function profilevisits_popup () {
		// generates the popup to display the latest results on profiles. 
		global $lang, $templates, $db, $mybb, $session, $theme;
			
		if ($mybb->input['action'] == "profilevisits") {
			
			$lang->load("profilevisits");
			define("NO_ONLINE", 1);		
			$altrow = new profilevisits_altrow();		
			
			if ((empty($mybb->input['uid'])) || ($mybb->request_method != "get")) {
				error($lang->profilevisits_invalid_request); // invalid request, generate error page. 
			}
			
			$visits = null;		
			$profileID = (int) $mybb->input['uid'];		
			
			$expire = my_number_format((int) $mybb->settings['profilevisits_expire']);
			$interval = $lang->profilevisits_interval_last.$expire.$lang->profilevisits_interval_days;	
			
			$rowsperpage = profilevisits_get_popup_rows(); 
			if(empty($expire)) $expire = 30;	
			
			else {
				$query = $db->query("
					SELECT username
					FROM ".TABLE_PREFIX."users
					WHERE uid = ". (int) $profileID);

				while($data = $db->fetch_array($query)) {
					$profile_username = $data['username']; // fetch username of profile
				} 
				
				$invisible_condition = null;
				if ($mybb->usergroup['canviewwolinvis'] == 0) {
					$invisible_condition = "AND u.invisible = 0 "; 
				}
				
				$query = $db->query("
					SELECT p.uid, p.date AS visit_date, u.uid, u.avatar, u.avatardimensions, u.username, u.invisible, u.usergroup, u.displaygroup
					FROM ".TABLE_PREFIX."profilevisits_log p
					LEFT JOIN " . TABLE_PREFIX . "users u ON p.uid = u.uid
					WHERE (p.profileID =". (int) $profileID.") ".$invisible_condition." ORDER BY VID DESC LIMIT ".(int) $rowsperpage
				);
			
				$i = 0;
				
				while($data = $db->fetch_array($query)) {
					$i++;
					
					$invisible_mark = '';
					if($data['invisible'] == 1) $invisible_mark = "*";
					
					$username = format_name($data['username'], $data['usergroup'], $data['displaygroup']);
					$profile_link = build_profile_link($username, intval($data['uid']), '_blank', 'if(window.opener) { window.opener.location = this.href; return false; }');
					$profile_link .= $invisible_mark;
					
					$visitor['avatar'] = format_avatar(htmlspecialchars_uni($data['avatar']), $data['avatardimensions'], '44x44');
					$active = profilevisits_get_last_active ($data['visit_date']);
					
					$altbg = $altrow->alternate();
					eval("\$visits .= \"".$templates->get("profilevisits_user")."\";");			
				} 
				
				if ($i == 0) {
					$altbg = $altrow->alternate();
					$visits = "<tr><td colspan='2' class='{$altbg}'>{$lang->profilevisits_nothing}</td></tr>";
				}
				
				$moderation = null;	
					
				if (profilevisits_permissions($mybb->settings['profilevisits_modgroups'], $mybb->user['usergroup'], $mybb->user['additionalgroups'])) {
					$moderation = profilevisits_modlinks ($profileID);
				}
								
				$query = $db->query("
					SELECT COUNT(p.VID) AS visits
					FROM ".TABLE_PREFIX."profilevisits_log p
					LEFT JOIN " . TABLE_PREFIX . "users u ON p.uid = u.uid
					WHERE (p.profileID = ". (int) $profileID.") ".$invisible_condition
				);
							
				$numvisits = $db->fetch_field($query, "visits"); // fetch total results. 
				
				$loadmore = null;
				
				if ($numvisits > $mybb->settings['profilevisits_numresults']) {
					$viewing = profilevisits_get_ajax_rows (true);
										
					if($viewing > $numvisits) {
						$viewing = $numvisits; 
					}
					
					$lastrow = $altrow->row;
					$onclick = "$.get('xmlhttp.php?action=profilevisits_ajax&lastid={$i}&uid={$profileID}&trow={$lastrow}', function(data){ $('tr.profileVisit:last').after(data); }); document.getElementById('button_showmore').innerHTML = '[{$lang->profilevisits_viewing} {$viewing}]';";
					$loadmore = "<span id='button_showmore'>[<a href='javascript:;' onclick=\"{$onclick}\">{$lang->profilevisits_load_more}</a>]</span>"; 
				}
			
				eval("\$profilevisits = \"".$templates->get("profilevisits_popup", 1, 0)."\";");
				echo $profilevisits;
			}	
			exit;				
		}
	}
	

	function profilevisits_ajax () {
		// loads additional results via ajax
		global $db, $mybb, $templates, $lang;
		
		$profileID = (int) $mybb->input['uid'];
		$offset = (int) $mybb->input['lastid'];	
		
		if ((empty($mybb->input['lastid'])) || (empty($mybb->input['uid'])) || ($mybb->input['action'] != "profilevisits_ajax")) {
			return; // invalid request
		}
		
		else {
			$lang->load("profilevisits");
			define("NO_ONLINE", 1);		
			$altrow = new profilevisits_altrow($mybb->input['trow']); // start the trow alternation taking the previous result loaded into account. 	
			
			$visits = null;
			$invisible_condition = null;		

			if ($mybb->usergroup['canviewwolinvis'] == 0) {
				$invisible_condition = " AND u.invisible = 0 "; // don't fetch invisible results if user does not have permission to view invisible users
			}
				
			$rows = (int) profilevisits_get_ajax_rows (false);
				
			$query = $db->query("
				SELECT p.uid, p.date AS visit_date, u.uid, u.avatar, u.avatardimensions, u.username, u.invisible, u.usergroup, u.displaygroup
				FROM ".TABLE_PREFIX."profilevisits_log p
				LEFT JOIN " . TABLE_PREFIX . "users u ON p.uid = u.uid
				WHERE (p.profileID = ". (int) $profileID.") {$invisible_condition} ORDER BY p.VID DESC LIMIT {$offset}, {$rows}"
			);
			
			$i = $offset;
			
			while($data = $db->fetch_array($query)) {
				$i++;
				$invisible_mark = '';
				if($data['invisible'] == 1) $invisible_mark = "*";
				
				$username = format_name($data['username'], $data['usergroup'], $data['displaygroup']);
				$profile_link = build_profile_link($username, intval($mybb->input['uid']), '_blank', 'if(window.opener) { window.opener.location = this.href; return false; }');
				$profile_link .= $invisible_mark;
			
				$visitor['avatar'] = format_avatar(htmlspecialchars_uni($data['avatar']), $data['avatardimensions'], '44x44');
				$active = profilevisits_get_last_active ($data['visit_date']);
				
				$altbg = $altrow->alternate();
				eval("\$visits .= \"".$templates->get("profilevisits_user")."\";");	
			} 	
			
			echo $visits;
			
			if ($i == $offset) {
				$altbg = $altrow->alternate();
				echo "<tr><td colspan='2' class='{$altbg}'><div style='text-align: center;'><em>{$lang->profilevisits_no_results}</em></div></td></tr>"; // no results to display. 
				return;
			}		
		}
	}	
	
	
	function profilevisits_permissions ($allowed, $usergroup, $additionalgroups) {
		// verifies user permissions. Usergroups should be passed in the string format with a comma between each group ID (e.g. "2,3,4").
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
		// verifies permissions, generates an error on fail. 
		if (profilevisits_permissions($allowed, $usergroup, $additionalgroups)) {
			return true; 
		}
		else {
			error_no_permission();
		}
	}
	

	function profilevisits_modlinks ($user) {
		// generates moderator control links
		global $mybb, $lang;
		$lang->load("profilevisits");
		$uid = (int) $user;
		$onclick = "onclick=' return confirm(\"".$lang->profilevisits_confirm."\");'";
		
		$moderation = '<tr><td class="trow2" colspan="2"><strong>'.$lang->profilevisits_moderation.'</strong>';
		$moderation .= "[<a href='misc.php?action=profilevisits_clearcounter&uid={$uid}&postkey={$mybb->post_code}' {$onclick}>{$lang->profilevisits_clearcounter}</a>] ";
		$moderation .= " [<a href='misc.php?action=profilevisits_clearvisits&uid={$uid}&postkey={$mybb->post_code}' {$onclick}>{$lang->profilevisits_clearvisits}</a>]";
		$moderation .= "</td></tr>";
		
		return $moderation; 
	}
	

	function profilevisits_moderation () {
		// processes moderator actions
		global $mybb, $db, $lang;
		$lang->load("profilevisits");
		
		if (((($mybb->input['action'] == "profilevisits_clearcounter")) || ($mybb->input['action'] == "profilevisits_clearvisits")) && ($mybb->request_method == "get")) {
			verify_post_check($mybb->input['postkey']);
			profilevisits_verify_permissions($mybb->settings['profilevisits_modgroups'], $mybb->user['usergroup'], $mybb->user['additionalgroups']);
			
			if ($mybb->input['action'] == "profilevisits_clearcounter") {
				$uid = (int) $mybb->input['uid'];
				
				if (empty($uid)) {
					error($lang->profilevisits_no_uid); // no UID defined
				}
				else {
					$array = array(
						"profilevisits" => 0
					);
					$db->update_query("users", $array, "uid = ". (int)$uid); // reset counter
					$db->delete_query("profilevisits_cache", "profileID = ".(int)$uid);
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
		// will return true if users are allowed to be logged on their own profiles. 
		global $mybb;
		if ((intval($profileID) == $mybb->user['uid']) && ($mybb->settings['profilevisits_log_own'] == 0)) {
			return false;
		}
		return true;
	}
	

	function profilevisits_get_ajax_rows ($total = false) {
		// returns the number of rows to generate on an ajax request. 
		global $mybb;
		
		$rows = (((int) $mybb->settings['profilevisits_numresults']) * 2);
		if ($rows < 2) $rows = 10; // prevents SQL errors if users put non-numeric values in for this setting
		
		if ($total == false) {
			return $rows; // return results loaded by ajax
		}
		else {
			return ($mybb->settings['profilevisits_numresults'] + $rows); // return total results displayed
		}
	}


	function profilevisits_get_popup_rows () {
		// Returns the number of rows to generate on a popup request. 
		global $mybb;
		$rows = (int) $mybb->settings['profilevisits_numresults'];
		if (empty($rows)) {
			return 5;
		}
		return $rows;
	}	
	

	function profilevisits_get_last_active ($lastactive) {
		// formats the time for the last active date on user profiles. 
		global $lang;
		
		if($lastactive) {
			return my_date('relative', $lastactive);
		}
					
		else {
			$lang->load("profilevisits");
			return $lang->profilevisits_never;
		}
	}


	function profilevisits_online(&$plugin_array) {
		// required to remove the "unknown location" from the online users list. 
		global $lang; 
		$lang->load("profilevisits");
		if (my_strpos($plugin_array['user_activity']['location'], 'misc.php?action=profilevisits')) {
			$plugin_array['location_name'] = $lang->viewing_profile_visits;
		}
	} 
	

	function profilevisits_cleanup_cache ($args) {
		// deletes expired cache results. 
		global $db, $mybb;		
		$cache_cutoff = (time() - ((int) $mybb->settings['profilevisits_cachetime'] * 60)); 
		$db->delete_query("profilevisits_cache", "date < ".(int) $cache_cutoff.""); // delete old cached results
	}
	
	
	function profilevisits_cleanup_visits ($args) {
		// Deletes expired visits from the database to manage database space. 
		global $db, $mybb;		

		$expire = (int) $mybb->settings['profilevisits_expire'];
		if(empty($expire)) $expire = 30; // default to 30 days
		
		$expire = (time() - ($expire * 60 * 60 * 24)); // convert to seconds
		
		$db->delete_query("profilevisits_log", "date < ".(int) $expire.""); 
	}
	
	
	// this class essentially replicates the functionality of MyBB's alt_trow() function, but allows the alternation to start at any point. This is useful in resolving alt_trow issues when more results are loaded via ajax. 
	class profilevisits_altrow {
		public $row;
		
		function __construct ($prev="trow2") {
			if ($prev !== "trow1" && $prev !== "trow2") {
				$prev = "trow2"; // reset to default if invalid value is passed. 
			}
			$this->row = $prev;
		}
		
		function alternate() {
			if ($this->row == "trow2") {
				$this->row = "trow1";				
				return "trow1";
			}
			else {
				$this->row = "trow2";
				return "trow2";
			}
		}
	}
