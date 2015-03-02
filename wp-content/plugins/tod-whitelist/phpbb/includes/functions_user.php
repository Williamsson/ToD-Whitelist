<?php
/**
*
* @package phpBB3
* @version $Id$
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
// if (!defined('IN_PHPBB'))
// {
// 	exit;
// }

/**
* Obtain user_ids from usernames or vice versa. Returns false on
* success else the error string
*
* @param array &$user_id_ary The user ids to check or empty if usernames used
* @param array &$username_ary The usernames to check or empty if user ids used
* @param mixed $user_type Array of user types to check, false if not restricting by user type


/**
* Adds an user
*
* @param mixed $user_row An array containing the following keys (and the appropriate values): username, group_id (the group to place the user in), user_email and the user_type(usually 0). Additional entries not overridden by defaults will be forwarded.
* @param string $cp_data custom profile fields, see custom_profile::build_insert_sql_array
* @return the new user's ID.
*/
function user_add($user_row, $cp_data = false)
{
	global $db, $user, $auth, $config, $phpbb_root_path, $phpEx;

	if (empty($user_row['username']) || !isset($user_row['group_id']) || !isset($user_row['user_email']) || !isset($user_row['user_type']))
	{
		return false;
	}

	$username_clean = sanitize_text_field($user_row['username']);

	if (empty($username_clean))
	{
		return false;
	}

	$sql_ary = array(
		'username'			=> $user_row['username'],
		'username_clean'	=> strtolower($username_clean),
		'user_password'		=> (isset($user_row['user_password'])) ? $user_row['user_password'] : '',
		'user_pass_convert'	=> 0,
		'user_email'		=> strtolower($user_row['user_email']),
		'group_id'			=> $user_row['group_id'],
		'user_type'			=> $user_row['user_type'],
	);

	// These are the additional vars able to be specified
	$additional_vars = array(
		'user_permissions'	=> '',
		'user_timezone'		=> $config['board_timezone'],
		'user_dateformat'	=> 'D M d, Y g:i a',
		'user_lang'			=> $config['default_lang'],
		'user_style'		=> (int) $config['default_style'],
		'user_actkey'		=> '',
		'user_ip'			=> '',
		'user_regdate'		=> time(),
		'user_passchg'		=> time(),
		'user_options'		=> 230271,
		// We do not set the new flag here - registration scripts need to specify it
		'user_new'			=> 0,

		'user_inactive_reason'	=> 0,
		'user_inactive_time'	=> 0,
		'user_lastmark'			=> time(),
		'user_lastvisit'		=> 0,
		'user_lastpost_time'	=> 0,
		'user_lastpage'			=> '',
		'user_posts'			=> 0,
		'user_dst'				=> (int) $config['board_dst'],
		'user_colour'			=> '',
		'user_occ'				=> '',
		'user_interests'		=> '',
		'user_avatar'			=> '',
		'user_avatar_type'		=> 0,
		'user_avatar_width'		=> 0,
		'user_avatar_height'	=> 0,
		'user_new_privmsg'		=> 0,
		'user_unread_privmsg'	=> 0,
		'user_last_privmsg'		=> 0,
		'user_message_rules'	=> 0,
		'user_full_folder'		=> PRIVMSGS_NO_BOX,
		'user_emailtime'		=> 0,

		'user_notify'			=> 0,
		'user_notify_pm'		=> 1,
		'user_notify_type'		=> NOTIFY_EMAIL,
		'user_allow_pm'			=> 1,
		'user_allow_viewonline'	=> 1,
		'user_allow_viewemail'	=> 1,
		'user_allow_massemail'	=> 1,

		'user_sig'					=> '',
		'user_sig_bbcode_uid'		=> '',
		'user_sig_bbcode_bitfield'	=> '',

		'user_form_salt'			=> unique_id(),
	);

	// Now fill the sql array with not required variables
	foreach ($additional_vars as $key => $default_value)
	{
		$sql_ary[$key] = (isset($user_row[$key])) ? $user_row[$key] : $default_value;
	}

	// Any additional variables in $user_row not covered above?
	$remaining_vars = array_diff(array_keys($user_row), array_keys($sql_ary));

	// Now fill our sql array with the remaining vars
	if (sizeof($remaining_vars))
	{
		foreach ($remaining_vars as $key)
		{
			$sql_ary[$key] = $user_row[$key];
		}
	}

	$sql = 'INSERT INTO themcforum_users ' . $db->sql_build_array('INSERT', $sql_ary);
	$a = $db->sql_query($sql);
	
	$user_id = $db->sql_nextid();

	// Insert Custom Profile Fields
	if ($cp_data !== false && sizeof($cp_data))
	{
		$cp_data['user_id'] = (int) $user_id;

		if (!class_exists('custom_profile'))
		{
			include_once($phpbb_root_path . 'includes/functions_profile_fields.' . $phpEx);
		}

		$sql = 'INSERT INTO ' . PROFILE_FIELDS_DATA_TABLE . ' ' .
			$db->sql_build_array('INSERT', custom_profile::build_insert_sql_array($cp_data));
		$db->sql_query($sql);
	}

	// Place into appropriate group...
	$sql = 'INSERT INTO themcforum_user_group ' . $db->sql_build_array('INSERT', array(
		'user_id'		=> (int) $user_id,
		'group_id'		=> (int) $user_row['group_id'],
		'user_pending'	=> 0)
	);
	$db->sql_query($sql);

	// Now make it the users default group...
	group_set_user_default($user_row['group_id'], array($user_id), false);

	// Add to newly registered users group if user_new is 1
	if ($config['new_member_post_limit'] && $sql_ary['user_new'])
	{
		$sql = "SELECT group_id
			FROM themcforum_groups
			WHERE group_name = 'NEWLY_REGISTERED'
				AND group_type = " . GROUP_SPECIAL;
		$result = $db->sql_query($sql);
		$add_group_id = (int) $db->sql_fetchfield('group_id');
		$db->sql_freeresult($result);

		if ($add_group_id)
		{
			// Because these actions only fill the log unneccessarily we skip the add_log() entry with a little hack. :/
			$GLOBALS['skip_add_log'] = true;

			// Add user to "newly registered users" group and set to default group if admin specified so.
			if ($config['new_member_group_default'])
			{
				group_user_add($add_group_id, $user_id, false, false, true);
				$user_row['group_id'] = $add_group_id;
			}
			else
			{
				group_user_add($add_group_id, $user_id);
			}

			unset($GLOBALS['skip_add_log']);
		}
	}

	// set the newest user and adjust the user count if the user is a normal user and no activation mail is sent
	if ($user_row['user_type'] == USER_NORMAL || $user_row['user_type'] == USER_FOUNDER)
	{
		set_config('newest_user_id', $user_id, true);
		set_config('newest_username', $user_row['username'], true);
		set_config_count('num_users', 1, true);

		$sql = 'SELECT group_colour
			FROM themcforum_groups
			WHERE group_id = ' . (int) $user_row['group_id'];
		$result = $db->sql_query_limit($sql, 1);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		set_config('newest_user_colour', $row['group_colour'], true);
	}

	return $user_id;
}

/**
* Remove User
*/
function user_delete($mode, $user_id, $post_username = false)
{
	global $cache, $config, $db, $user, $auth;
	global $phpbb_root_path, $phpEx;

	$sql = 'SELECT *
		FROM ' . USERS_TABLE . '
		WHERE user_id = ' . $user_id;
	$result = $db->sql_query($sql);
	$user_row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	if (!$user_row)
	{
		return false;
	}

	// Before we begin, we will remove the reports the user issued.
	$sql = 'SELECT r.post_id, p.topic_id
		FROM ' . REPORTS_TABLE . ' r, ' . POSTS_TABLE . ' p
		WHERE r.user_id = ' . $user_id . '
			AND p.post_id = r.post_id';
	$result = $db->sql_query($sql);

	$report_posts = $report_topics = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$report_posts[] = $row['post_id'];
		$report_topics[] = $row['topic_id'];
	}
	$db->sql_freeresult($result);

	if (sizeof($report_posts))
	{
		$report_posts = array_unique($report_posts);
		$report_topics = array_unique($report_topics);

		// Get a list of topics that still contain reported posts
		$sql = 'SELECT DISTINCT topic_id
			FROM ' . POSTS_TABLE . '
			WHERE ' . $db->sql_in_set('topic_id', $report_topics) . '
				AND post_reported = 1
				AND ' . $db->sql_in_set('post_id', $report_posts, true);
		$result = $db->sql_query($sql);

		$keep_report_topics = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$keep_report_topics[] = $row['topic_id'];
		}
		$db->sql_freeresult($result);

		if (sizeof($keep_report_topics))
		{
			$report_topics = array_diff($report_topics, $keep_report_topics);
		}
		unset($keep_report_topics);

		// Now set the flags back
		$sql = 'UPDATE ' . POSTS_TABLE . '
			SET post_reported = 0
			WHERE ' . $db->sql_in_set('post_id', $report_posts);
		$db->sql_query($sql);

		if (sizeof($report_topics))
		{
			$sql = 'UPDATE ' . TOPICS_TABLE . '
				SET topic_reported = 0
				WHERE ' . $db->sql_in_set('topic_id', $report_topics);
			$db->sql_query($sql);
		}
	}

	// Remove reports
	$db->sql_query('DELETE FROM ' . REPORTS_TABLE . ' WHERE user_id = ' . $user_id);

	if ($user_row['user_avatar'] && $user_row['user_avatar_type'] == AVATAR_UPLOAD)
	{
		avatar_delete('user', $user_row);
	}

	switch ($mode)
	{
		case 'retain':

			$db->sql_transaction('begin');

			if ($post_username === false)
			{
				$post_username = $user->lang['GUEST'];
			}

			// If the user is inactive and newly registered we assume no posts from this user being there...
			if ($user_row['user_type'] == USER_INACTIVE && $user_row['user_inactive_reason'] == INACTIVE_REGISTER && !$user_row['user_posts'])
			{
			}
			else
			{
				$sql = 'UPDATE ' . FORUMS_TABLE . '
					SET forum_last_poster_id = ' . ANONYMOUS . ", forum_last_poster_name = '" . $db->sql_escape($post_username) . "', forum_last_poster_colour = ''
					WHERE forum_last_poster_id = $user_id";
				$db->sql_query($sql);

				$sql = 'UPDATE ' . POSTS_TABLE . '
					SET poster_id = ' . ANONYMOUS . ", post_username = '" . $db->sql_escape($post_username) . "'
					WHERE poster_id = $user_id";
				$db->sql_query($sql);

				$sql = 'UPDATE ' . POSTS_TABLE . '
					SET post_edit_user = ' . ANONYMOUS . "
					WHERE post_edit_user = $user_id";
				$db->sql_query($sql);

				$sql = 'UPDATE ' . TOPICS_TABLE . '
					SET topic_poster = ' . ANONYMOUS . ", topic_first_poster_name = '" . $db->sql_escape($post_username) . "', topic_first_poster_colour = ''
					WHERE topic_poster = $user_id";
				$db->sql_query($sql);

				$sql = 'UPDATE ' . TOPICS_TABLE . '
					SET topic_last_poster_id = ' . ANONYMOUS . ", topic_last_poster_name = '" . $db->sql_escape($post_username) . "', topic_last_poster_colour = ''
					WHERE topic_last_poster_id = $user_id";
				$db->sql_query($sql);

				$sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
					SET poster_id = ' . ANONYMOUS . "
					WHERE poster_id = $user_id";
				$db->sql_query($sql);

				// Since we change every post by this author, we need to count this amount towards the anonymous user

				// Update the post count for the anonymous user
				if ($user_row['user_posts'])
				{
					$sql = 'UPDATE ' . USERS_TABLE . '
						SET user_posts = user_posts + ' . $user_row['user_posts'] . '
						WHERE user_id = ' . ANONYMOUS;
					$db->sql_query($sql);
				}
			}

			$db->sql_transaction('commit');

		break;

		case 'remove':

			if (!function_exists('delete_posts'))
			{
				include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
			}

			// Delete posts, attachments, etc.
			delete_posts('poster_id', $user_id);

		break;
	}

	$db->sql_transaction('begin');

	$table_ary = array(USERS_TABLE, USER_GROUP_TABLE, TOPICS_WATCH_TABLE, FORUMS_WATCH_TABLE, ACL_USERS_TABLE, TOPICS_TRACK_TABLE, TOPICS_POSTED_TABLE, FORUMS_TRACK_TABLE, PROFILE_FIELDS_DATA_TABLE, MODERATOR_CACHE_TABLE, DRAFTS_TABLE, BOOKMARKS_TABLE, SESSIONS_KEYS_TABLE, PRIVMSGS_FOLDER_TABLE, PRIVMSGS_RULES_TABLE);

	foreach ($table_ary as $table)
	{
		$sql = "DELETE FROM $table
			WHERE user_id = $user_id";
		$db->sql_query($sql);
	}

	$cache->destroy('sql', MODERATOR_CACHE_TABLE);

	// Delete user log entries about this user
	$sql = 'DELETE FROM ' . LOG_TABLE . '
		WHERE reportee_id = ' . $user_id;
	$db->sql_query($sql);

	// Change user_id to anonymous for this users triggered events
	$sql = 'UPDATE ' . LOG_TABLE . '
		SET user_id = ' . ANONYMOUS . '
		WHERE user_id = ' . $user_id;
	$db->sql_query($sql);

	// Delete the user_id from the zebra table
	$sql = 'DELETE FROM ' . ZEBRA_TABLE . '
		WHERE user_id = ' . $user_id . '
			OR zebra_id = ' . $user_id;
	$db->sql_query($sql);

	// Delete the user_id from the banlist
	$sql = 'DELETE FROM ' . BANLIST_TABLE . '
		WHERE ban_userid = ' . $user_id;
	$db->sql_query($sql);

	// Delete the user_id from the session table
	$sql = 'DELETE FROM ' . SESSIONS_TABLE . '
		WHERE session_user_id = ' . $user_id;
	$db->sql_query($sql);

	// Clean the private messages tables from the user
	if (!function_exists('phpbb_delete_user_pms'))
	{
		include($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
	}
	phpbb_delete_user_pms($user_id);

	$db->sql_transaction('commit');

	// Reset newest user info if appropriate
	if ($config['newest_user_id'] == $user_id)
	{
		update_last_username();
	}

	// Decrement number of users if this user is active
	if ($user_row['user_type'] != USER_INACTIVE && $user_row['user_type'] != USER_IGNORE)
	{
		set_config_count('num_users', -1, true);
	}

	return false;
}

function group_set_user_default($group_id, $user_id_ary, $group_attributes = false, $update_listing = false)
{
	global $cache, $db;

	if (empty($user_id_ary))
	{
		return;
	}

	$attribute_ary = array(
			'group_colour'			=> 'string',
			'group_rank'			=> 'int',
			'group_avatar'			=> 'string',
			'group_avatar_type'		=> 'int',
			'group_avatar_width'	=> 'int',
			'group_avatar_height'	=> 'int',
	);

	$sql_ary = array(
			'group_id'		=> $group_id
	);

	// Were group attributes passed to the function? If not we need to obtain them
	if ($group_attributes === false)
	{
		$sql = "SELECT ' . implode(', ', array_keys($attribute_ary)) . '
			FROM themcforum_groups
			WHERE group_id = $group_id";
		$result = $db->sql_query($sql);
		$group_attributes = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
	}

	foreach ($attribute_ary as $attribute => $type)
	{
		if (isset($group_attributes[$attribute]))
		{
			// If we are about to set an avatar or rank, we will not overwrite with empty, unless we are not actually changing the default group
			if ((strpos($attribute, 'group_avatar') === 0 || strpos($attribute, 'group_rank') === 0) && !$group_attributes[$attribute])
			{
				continue;
			}

			settype($group_attributes[$attribute], $type);
			$sql_ary[str_replace('group_', 'user_', $attribute)] = $group_attributes[$attribute];
		}
	}

	// Before we update the user attributes, we will make a list of those having now the group avatar assigned
	if (isset($sql_ary['user_avatar']))
	{
		// Ok, get the original avatar data from users having an uploaded one (we need to remove these from the filesystem)
		$sql = 'SELECT user_id, group_id, user_avatar
			FROM themcforum_users
			WHERE ' . $db->sql_in_set('user_id', $user_id_ary) . '
				AND user_avatar_type = ' . AVATAR_UPLOAD;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			avatar_delete('user', $row);
		}
		$db->sql_freeresult($result);
	}
	else
	{
		unset($sql_ary['user_avatar_type']);
		unset($sql_ary['user_avatar_height']);
		unset($sql_ary['user_avatar_width']);
	}

	$sql = 'UPDATE themcforum_users SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
		WHERE ' . $db->sql_in_set('user_id', $user_id_ary);
	$db->sql_query($sql);

	if (isset($sql_ary['user_colour']))
	{
		// Update any cached colour information for these users
		$sql = 'UPDATE ' . FORUMS_TABLE . " SET forum_last_poster_colour = '" . $db->sql_escape($sql_ary['user_colour']) . "'
			WHERE " . $db->sql_in_set('forum_last_poster_id', $user_id_ary);
		$db->sql_query($sql);

		$sql = 'UPDATE ' . TOPICS_TABLE . " SET topic_first_poster_colour = '" . $db->sql_escape($sql_ary['user_colour']) . "'
			WHERE " . $db->sql_in_set('topic_poster', $user_id_ary);
		$db->sql_query($sql);

		$sql = 'UPDATE ' . TOPICS_TABLE . " SET topic_last_poster_colour = '" . $db->sql_escape($sql_ary['user_colour']) . "'
			WHERE " . $db->sql_in_set('topic_last_poster_id', $user_id_ary);
		$db->sql_query($sql);

		global $config;

		if (in_array($config['newest_user_id'], $user_id_ary))
		{
			set_config('newest_user_colour', $sql_ary['user_colour'], true);
		}
	}

	if ($update_listing)
	{
		group_update_listings($group_id);
	}

}

function validate_phpbb_username($username, $allowed_username = false)
{
	global $config, $db, $user, $cache;

	$clean_username = sanitize_text_field($username);
	$allowed_username = ($allowed_username === false) ? $user->data['username_clean'] : sanitize_text_field($allowed_username);
	
	if ($allowed_username == $clean_username)
	{
		return false;
	}

	// ... fast checks first.
	if (strpos($username, '&quot;') !== false || strpos($username, '"') !== false || empty($clean_username))
	{
		return 'INVALID_CHARS';
	}
	
	$mbstring = $pcre = false;

	// generic UTF-8 character types supported?
	if ((version_compare(PHP_VERSION, '5.1.0', '>=') || (version_compare(PHP_VERSION, '5.0.0-dev', '<=') && version_compare(PHP_VERSION, '4.4.0', '>='))) && @preg_match('/\p{L}/u', 'a') !== false)
	{
		$pcre = true;
	}
	else if (function_exists('mb_ereg_match'))
	{
		mb_regex_encoding('UTF-8');
		$mbstring = true;
	}

	switch ($config['allow_name_chars'])
	{
		case 'USERNAME_CHARS_ANY':
			$pcre = true;
			$regex = '.+';
			break;

		case 'USERNAME_ALPHA_ONLY':
			$pcre = true;
			$regex = '[A-Za-z0-9]+';
			break;

		case 'USERNAME_ALPHA_SPACERS':
			$pcre = true;
			$regex = '[A-Za-z0-9-[\]_+ ]+';
			break;

		case 'USERNAME_LETTER_NUM':
			if ($pcre)
			{
				$regex = '[\p{Lu}\p{Ll}\p{N}]+';
			}
			else if ($mbstring)
			{
				$regex = '[[:upper:][:lower:][:digit:]]+';
			}
			else
			{
				$pcre = true;
				$regex = '[a-zA-Z0-9]+';
			}
			break;

		case 'USERNAME_LETTER_NUM_SPACERS':
			if ($pcre)
			{
				$regex = '[-\]_+ [\p{Lu}\p{Ll}\p{N}]+';
			}
			else if ($mbstring)
			{
				$regex = '[-\]_+ \[[:upper:][:lower:][:digit:]]+';
			}
			else
			{
				$pcre = true;
				$regex = '[-\]_+ [a-zA-Z0-9]+';
			}
			break;

		case 'USERNAME_ASCII':
		default:
			$pcre = true;
			$regex = '[\x01-\x7F]+';
			break;
	}

	if ($pcre)
	{
		if (!preg_match('#^' . $regex . '$#u', $username))
		{
			return 'INVALID_CHARS';
		}
	}
	else if ($mbstring)
	{
		mb_ereg_search_init($username, '^' . $regex . '$');
		if (!mb_ereg_search())
		{
			return 'INVALID_CHARS';
		}
	}

	$sql = 'SELECT username
		FROM ' . USERS_TABLE . "
		WHERE username_clean = '" . $db->sql_escape($clean_username) . "'";
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	if ($row)
	{
		return 'USERNAME_TAKEN';
	}

	$sql = 'SELECT group_name
		FROM ' . GROUPS_TABLE . "
		WHERE LOWER(group_name) = '" . $db->sql_escape(strtolower($username)) . "'";
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	if ($row)
	{
		return 'USERNAME_TAKEN';
	}


	return false;
}