<?php
/***************************************************************************
 *                           admin_permissions.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id$
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

if (!empty($setmodules))
{
	$filename = basename(__FILE__);
	$module['Forums']['PERMISSIONS'] = ($auth->acl_get('a_auth')) ? $filename . $SID . '&amp;mode=forums' : '';
	$module['Forums']['MODERATORS'] = ($auth->acl_get('a_authmods')) ? $filename . $SID . '&amp;mode=moderators' : '';
	$module['Forums']['SUPER_MODERATORS'] = ($auth->acl_get('a_authmods')) ? $filename . $SID . '&amp;mode=supermoderators' : '';
	$module['General']['ADMINISTRATORS'] = ($auth->acl_get('a_authadmins')) ? $filename . $SID . '&amp;mode=administrators' : '';

	return;
}

define('IN_PHPBB', 1);

// Include files
$phpbb_root_path = '../';
require($phpbb_root_path . 'extension.inc');
require('pagestart.' . $phpEx);
require($phpbb_root_path . 'includes/functions_admin.'.$phpEx);

// Define some vars
if (isset($_REQUEST['f']))
{
	$forum_id = intval($_REQUEST['f']);
	$forum_sql = " WHERE forum_id = $forum_id";
}
else
{
	$forum_id = 0;
	$forum_sql = '';
}

$mode = (isset($_REQUEST['mode'])) ? $_REQUEST['mode'] : '';

// Start program proper
switch ($mode)
{
	case 'forums':
		$l_title = $user->lang['PERMISSIONS'];
		$l_title_explain = $user->lang['PERMISSIONS_EXPLAIN'];
		$which_acl = 'a_auth';
		$type_sql = 'f';
		break;

	case 'moderators':
		$l_title = $user->lang['MODERATORS'];
		$l_title_explain = $user->lang['MODERATORS_EXPLAIN'];
		$which_acl = 'a_authmods';
		$type_sql = 'm';
		break;

	case 'supermoderators':
		$l_title = $user->lang['SUPER_MODERATORS'];
		$l_title_explain = $user->lang['SUPER_MODERATORS_EXPLAIN'];
		$which_acl = 'a_authmods';
		$type_sql = 'm';
		break;

	case 'administrators':
		$l_title = $user->lang['ADMINISTRATORS'];
		$l_title_explain = $user->lang['ADMINISTRATORS_EXPLAIN'];
		$which_acl = 'a_authadmins';
		$type_sql = 'a';
		break;
}

// Permission check
if (!$auth->acl_get($which_acl))
{
	trigger_error($user->lang['NO_ADMIN']);
}

// Call update or delete, both can take multiple user/group
// ids. Additionally inheritance is handled (by the auth API)
if (isset($_POST['update']))
{
	$auth_admin = new auth_admin();

	// Admin wants subforums to inherit permissions ... so handle this
	if (!empty($_POST['inherit']))
	{
		array_push($_POST['inherit'], $forum_id);
		$forum_id = $_POST['inherit'];
	}

	foreach ($_POST['entries'] as $id)
	{
		$auth_admin->acl_set($_POST['type'], $forum_id, $id, $_POST['option']);
	}

	cache_moderators();

	trigger_error('Permissions updated successfully');
}
else if (isset($_POST['delete']))
{
	$auth_admin = new auth_admin();

	$option_ids = false;
	if (!empty($_POST['option']))
	{
		$sql = "SELECT auth_option_id
			FROM " . ACL_OPTIONS_TABLE . "
			WHERE auth_value LIKE '" . $_POST['option'] . "_%'";
		$result = $db->sql_query($sql);

		if ($row = $db->sql_fetchrow($result))
		{
			$option_ids = array();
			do
			{
				$option_ids[] = $row['auth_option_id'];
			}
			while($row = $db->sql_fetchrow($result));
		}
		$db->sql_freeresult($result);
	}

	foreach ($_POST['entries'] as $id)
	{
		$auth_admin->acl_delete($_POST['type'], $forum_id, $id, $option_ids);
	}

	cache_moderators();

	trigger_error('Permissions updated successfully');
}
else if (isset($_POST['presetsave']))
{
	$holding_ary = array();
	foreach ($_POST['option'] as $acl_option => $allow_deny)
	{
		switch ($allow_deny)
		{
			case ACL_ALLOW:
				$holding_ary['allow'][] = $acl_option;
				break;
			case ACL_DENY:
				$holding_ary['deny'][] = $acl_option;
				break;
			case ACL_INHERIT:
				$holding_ary['inherit'][] = $acl_option;
				break;
		}
	}

	$sql = array(
		'preset_user_id' => $user->data['user_id'],
		'preset_type' => $type_sql,
		'preset_data' => $db->sql_escape(serialize($holding_ary))
	);

	if (!empty($_POST['presetname']))
	{
		$sql['preset_name'] = $db->sql_escape($_POST['presetname']);
	}
	
	if (!empty($_POST['presetname']) || $_POST['presetoption'] != -1)
	{
		$sql = ($_POST['presetoption'] == -1) ? 'INSERT INTO ' . ACL_PRESETS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql) : 'UPDATE ' . ACL_PRESETS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql) . ' WHERE preset_id =' . $_POST['presetoption'];
		$db->sql_query($sql);
	}
}
else if (isset($_POST['presetdel']))
{
	if (!empty($_POST['presetoption']))
	{
		$sql = "DELETE FROM " . ACL_PRESETS_TABLE . " 
			WHERE preset_id = " . intval($_POST['presetoption']);
		$db->sql_query($sql);
	}
}

// Get required information, either all forums if no id was
// specified or just the requsted if it was
if (!empty($forum_id) || $mode == 'administrators' || $mode == 'supermoderators')
{
	// Clear some vars, grab some info if relevant ...
	$s_hidden_fields = '';

	if (!empty($forum_id))
	{
		$sql = "SELECT forum_name
			FROM " . FORUMS_TABLE . "
			WHERE forum_id = $forum_id";
		$result = $db->sql_query($sql);

		$forum_info = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		$l_title .= ' : <i>' . $forum_info['forum_name'] . '</i>';
	}

	// Generate header
	page_header($l_title);

?>

<h1><?php echo $l_title; ?></h1>

<?php

	switch ($mode)
	{
		case 'forums':
			$forum_sql = "AND a.forum_id = $forum_id";
			break;

		case 'moderators':
			$forum_sql = "AND a.forum_id = $forum_id";
			break;

		case 'supermoderators':
			$forum_sql = 'AND a.forum_id = 0';
			break;

		case 'administrators':
			$forum_sql = 'AND a.forum_id = 0';
			break;
	}

	$sql = "SELECT group_id, group_name
		FROM " . GROUPS_TABLE . "
		ORDER BY group_type DESC, group_name";
	$result = $db->sql_query($sql);

	$group_list = '';
	while ($row = $db->sql_fetchrow($result))
	{
		$group_list .= '<option value="' . $row['group_id'] . '">' . ((!empty($user->lang['G_' . $row['group_name']])) ? '* ' . $user->lang['G_' . $row['group_name']] : $row['group_name']) . '</option>';
	}
	$db->sql_freeresult($result);

	if (empty($_POST['advanced']) || empty($_POST['entries']))
	{

?>

<p><?php echo $l_title_explain; ?></p>

<table width="100%" cellspacing="0" cellpadding="0" border="0">
	<tr>
		<td align="center"><h1><?php echo $user->lang['Users']; ?></h1></td>
		<td align="center"><h1><?php echo $user->lang['Groups']; ?></h1></td>
	</tr>
	<tr>

		<td><form method="post" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table width="90%" class="bg" cellspacing="1" cellpadding="4" border="0" align="center">
<?php

		$sql = "SELECT DISTINCT u.user_id, u.username
			FROM " . USERS_TABLE . " u, " . ACL_USERS_TABLE . " a, " . ACL_OPTIONS_TABLE . " o
			WHERE o.auth_value LIKE '" . $type_sql . "_%'
				AND a.auth_option_id = o.auth_option_id
				$forum_sql
				AND u.user_id = a.user_id
			ORDER BY u.username, u.user_regdate ASC";
		$result = $db->sql_query($sql);

		$users = '';
		while ($row = $db->sql_fetchrow($result))
		{
			$users .= '<option value="' . $row['user_id'] . '">' . $row['username'] . '</option>';
		}
		$db->sql_freeresult($result);

?>
			<tr>
				<th><?php echo $user->lang['Manage_users']; ?></th>
			</tr>
			<tr>
				<td class="row1" align="center"><select style="width:280px" name="entries[]" multiple="multiple" size="5"><?php echo $users; ?></select></td>
			</tr>
			<tr>
				<td class="cat" align="center"><input class="liteoption" type="submit" name="delete" value="<?php echo $user->lang['Remove_selected']; ?>" /> &nbsp; <input class="liteoption" type="submit" name="advanced" value="<?php echo $user->lang['Advanced']; ?>" /><input type="hidden" name="type" value="user" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /><input type="hidden" name="option" value="<?php echo $type_sql; ?>" /></td>
			</tr>
		</table></form></td>

		<td align="center"><form method="post" name="admingroups" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table width="90%" class="bg" cellspacing="1" cellpadding="4" border="0" align="center">
<?php

		$sql = "SELECT DISTINCT g.group_id, g.group_name
			FROM " . GROUPS_TABLE . " g, " . ACL_GROUPS_TABLE . " a, " . ACL_OPTIONS_TABLE . " o
			WHERE o.auth_value LIKE '" . $type_sql . "_%'
				$forum_sql
				AND a.auth_option_id = o.auth_option_id
				AND g.group_id = a.group_id
			ORDER BY g.group_type DESC, g.group_name ASC";
		$result = $db->sql_query($sql);

		$groups = '';
		while ($row = $db->sql_fetchrow($result))
		{
			$groups .= '<option value="' . $row['group_id'] . '">' . ((!empty($user->lang['G_' . $row['group_name']])) ? '* ' . $user->lang['G_' . $row['group_name']] : $row['group_name']) . '</option>';
		}
		$db->sql_freeresult($result);

?>
		<tr>
			<th><?php echo $user->lang['Manage_groups']; ?></th>
		</tr>
		<tr>
			<td class="row1" align="center"><select style="width:280px" name="entries[]" multiple="multiple" size="5"><?php echo $groups; ?></select></td>
		</tr>
		<tr>
			<td class="cat" align="center"><input class="liteoption" type="submit" name="delete" value="<?php echo $user->lang['Remove_selected']; ?>" /> &nbsp; <input class="liteoption" type="submit" name="advanced" value="<?php echo $user->lang['Advanced']; ?>" /><input type="hidden" name="type" value="group" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /><input type="hidden" name="option" value="<?php echo $type_sql; ?>" /></td>
		</tr>
	</table></form></td>

	</tr>
	<tr>

		<td><form method="post" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table class="bg" width="90%" cellspacing="1" cellpadding="4" border="0" align="center">
			<tr>
				<th><?php echo $user->lang['Add_users']; ?></th>
			</tr>
			<tr>
				<td class="row1" align="center"><textarea cols="40" rows="4" name="entries"></textarea></td>
			</tr>
			<tr>
				<td class="cat" align="center"> <input type="submit" name="add" value="<?php echo $user->lang['SUBMIT']; ?>" class="mainoption" />&nbsp; <input type="reset" value="<?php echo $user->lang['RESET']; ?>" class="liteoption" />&nbsp; <input type="submit" name="usersubmit" value="<?php echo $user->lang['Find_username']; ?>" class="liteoption" onclick="window.open('<?php echo "../memberlist.$phpEx$SID"; ?>&amp;mode=searchuser&amp;form=2&amp;field=entries', '_phpbbsearch', 'HEIGHT=500,resizable=yes,scrollbars=yes,WIDTH=740');return false;" /><input type="hidden" name="type" value="user" /><input type="hidden" name="advanced" value="1" /><input type="hidden" name="new" value="1" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /></td>
			</tr>
		</table></form></td>

		<td><form method="post" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table width="90%" class="bg" cellspacing="1" cellpadding="4" border="0" align="center">
			<tr>
				<th><?php echo $user->lang['Add_groups']; ?></th>
			</tr>
			<tr>
				<td class="row1" align="center"><select name="entries[]" multiple="multiple" size="4"><?php echo $group_list; ?></select></td>
			</tr>
			<tr>
				<td class="cat" align="center"> <input type="submit" name="add" value="<?php echo $user->lang['SUBMIT']; ?>" class="mainoption" />&nbsp; <input type="reset" value="<?php echo $user->lang['RESET']; ?>" class="liteoption" /><input type="hidden" name="type" value="group" /><input type="hidden" name="advanced" value="1" /><input type="hidden" name="new" value="1" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /></td>
			</tr>
		</table></form></td>

	</tr>
</table>

<?php

	}
	else
	{

		// Founder only operations ... these operations can
		// only be altered by someone with founder status
		$founder_sql = (!$userdata['user_founder']) ? ' AND founder_only <> 1' : '';

		$sql = "SELECT auth_option_id, auth_value
			FROM " . ACL_OPTIONS_TABLE . "
			WHERE auth_value LIKE '" . $type_sql . "_%'
				AND auth_value <> '" . $type_sql . "_'
				$founder_sql";
		$result = $db->sql_query($sql);

		$auth_options = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$auth_options[] = $row;
		}
		$db->sql_freeresult($result);

		if ($_POST['type'] == 'user' && !empty($_POST['new']))
		{
			$_POST['entries'] = explode("\n", $_POST['entries']);
		}

		$where_sql = '';
		foreach ($_POST['entries'] as $value)
		{
			$where_sql .= (($where_sql != '') ? ', ' : '') . (($_POST['type'] == 'user' && !empty($_POST['new'])) ? '\'' . $value . '\'' : intval($value));
		}

		switch ($_POST['type'])
		{
			case 'group':
				$l_type = 'Group';

				$sql = (empty($_POST['new'])) ? "SELECT g.group_id AS id, g.group_name AS name, o.auth_value, a.auth_allow_deny FROM " . GROUPS_TABLE . " g, " . ACL_GROUPS_TABLE . " a, " . ACL_OPTIONS_TABLE . " o WHERE o.auth_value LIKE '" . $type_sql . "_%' AND a.auth_option_id = o.auth_option_id $forum_sql AND g.group_id = a.group_id AND g.group_id IN ($where_sql) ORDER BY g.group_name ASC" : "SELECT group_id AS id, group_name AS name FROM " . GROUPS_TABLE . " WHERE group_id IN ($where_sql) ORDER BY group_name ASC";
				break;

			case 'user':
				$l_type = 'User';

				$sql = (empty($_POST['new'])) ? "SELECT u.user_id AS id, u.username AS name, u.user_founder, o.auth_value, a.auth_allow_deny FROM " . USERS_TABLE . " u, " . ACL_USERS_TABLE . " a, " . ACL_OPTIONS_TABLE . " o WHERE o.auth_value LIKE '" . $type_sql . "_%' AND a.auth_option_id = o.auth_option_id $forum_sql AND u.user_id = a.user_id AND u.user_id IN ($where_sql) ORDER BY u.username, u.user_regdate ASC" : "SELECT user_id AS id, username AS name, user_founder FROM " . USERS_TABLE . " WHERE username IN ($where_sql) ORDER BY username, user_regdate ASC";
				break;
		}

		$result = $db->sql_query($sql);

		$ug = '';;
		$ug_hidden = '';
		$auth = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$ug_test = (!empty($user->lang[$row['name']])) ? $user->lang[$row['name']] : $row['name'];
			$ug .= (!strstr($ug, $ug_test)) ? $ug_test . "\n" : '';

			$ug_test = '<input type="hidden" name="entries[]" value="' . $row['id'] . '" />';
			$ug_hidden .= (!strstr($ug_hidden, $ug_test)) ? $ug_test : '';

			$auth[$row['auth_value']] = (isset($auth_group[$row['auth_value']])) ?  min($auth_group[$row['auth_value']], $row['auth_allow_deny']) : $row['auth_allow_deny'];
		}
		$db->sql_freeresult($result);

		// Look for presets
		$sql = "SELECT preset_id, preset_name, preset_data  
			FROM " . ACL_PRESETS_TABLE . " 
			WHERE preset_type = '$type_sql' 
			ORDER BY preset_id ASC";
		$result = $db->sql_query($sql);

		$preset_options = $preset_js = $preset_update_options = '';
		$holding = array();
		if ($row = $db->sql_fetchrow($result))
		{
			do
			{
				$preset_update_options .= '<option value="' . $row['preset_id'] . '">' . $row['preset_name'] . '</option>';
				$preset_options .= '<option style="color:red" value="preset_' . $row['preset_id'] . '">* ' . $row['preset_name'] . '</option>';

				$preset_data = unserialize($row['preset_data']);
				
				foreach ($preset_data as $preset_type => $preset_type_ary)
				{
					$holding[$preset_type] = '';
					foreach ($preset_type_ary as $preset_option)
					{
						$holding[$preset_type] .= "$preset_option, ";
					}
				}

				$preset_js .= "\tpresets['preset_" . $row['preset_id'] . "'] = new Array();" . "\n";
				$preset_js .= "\tpresets['preset_" . $row['preset_id'] . "'] = new preset_obj('" . $holding['allow'] . "', '" . $holding['deny'] . "', '" . $holding['inherit'] . "');\n";
			}
			while ($row = $db->sql_fetchrow($result));
		}
		unset($holding);

?>

<script language="Javascript" type="text/javascript">
<!--

	var presets = new Array();
<?php

	echo $preset_js;

?>

	function preset_obj(allow, deny, inherit)
	{
		this.allow = allow;
		this.deny = deny;
		this.inherit = inherit;
	}

	function use_preset(option)
	{
		if (option)
		{
			document.acl.set.selectedIndex = 0;
			var expr = new RegExp(/\d+/);
			for (i = 0; i < document.acl.length; i++)
			{
				var elem = document.acl.elements[i];
				if (elem.name.indexOf('option') == 0)
				{
					switch (option)
					{
						case 'all_allow':
							if (elem.value == <?php echo ACL_ALLOW; ?>)
								elem.checked = true;
							break;
						case 'all_deny':
							if (elem.value == <?php echo ACL_DENY; ?>)
								elem.checked = true;
							break;
						case 'all_inherit':
							if (elem.value == <?php echo ACL_INHERIT; ?>)
								elem.checked = true;
							break;
						default:
						    option_name = elem.name.substr(7, elem.name.length - 8);

							if (presets[option].allow.indexOf(option_name + ',') != -1 && elem.value == <?php echo ACL_ALLOW; ?>)
								elem.checked = true;
							else if (presets[option].deny.indexOf(option_name + ',') != -1 && elem.value == <?php echo ACL_DENY; ?>)
								elem.checked = true;
							else if (presets[option].inherit.indexOf(option_name + ',') != -1 && elem.value == <?php echo ACL_INHERIT; ?>)
								elem.checked = true;
							break;
					}
				}
			}
		}
	}

	function marklist(match, status)
	{
		for (i = 0; i < document.acl.length; i++)
		{
			if (document.acl.elements[i].name.indexOf(match) == 0)
				document.acl.elements[i].checked = status;
		}
	}
//-->
</script>

<p><?php echo $user->lang['ACL_EXPLAIN']; ?></p>

<form method="post" name="acl" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table cellspacing="2" cellpadding="0" border="0" align="center">
	<tr>
		<td align="right">Quick settings: <select name="set" onchange="use_preset(this.options[this.selectedIndex].value);"><option><?php echo '-- ' . $user->lang['Select'] . ' --'; ?></option><option value="all_allow"><?php echo $user->lang['All_Allow']; ?></option><option value="all_deny"><?php echo $user->lang['All_Deny']; ?></option><option value="all_inherit"><?php echo $user->lang['All_Inherit']; ?></option><?php echo ($preset_options) ? '<option>--' . $user->lang['PRESETS'] . '--</option>' . $preset_options : ''; ?></select></td>
	</tr>
	<tr>
		<td><table class="bg" width="100%" cellspacing="1" cellpadding="4" border="0" align="center">
	<tr>
		<th>&nbsp;<?php echo $user->lang['Option']; ?>&nbsp;</th>
		<th>&nbsp;<?php echo $user->lang['Allow']; ?>&nbsp;</th>
		<th>&nbsp;<?php echo $user->lang['Deny']; ?>&nbsp;</th>
		<th>&nbsp;<?php echo $user->lang['Inherit']; ?>&nbsp;</th>
	</tr>
<?php

		for($i = 0; $i < sizeof($auth_options); $i++)
		{
			$row_class = ($row_class == 'row1') ? 'row2' : 'row1';

			$l_can_cell = (!empty($user->lang['acl_' . $auth_options[$i]['auth_value']])) ? $user->lang['acl_' . $auth_options[$i]['auth_value']] : ucfirst(preg_replace('#.*?_#', '', $auth_options[$i]['auth_value']));

			if (!empty($_POST['presetsave']) || !empty($_POST['presetdel']))
			{
				$allow_type = ($_POST['option'][$auth_options[$i]['auth_value']] == ACL_ALLOW) ? ' checked="checked"' : '';
				$deny_type = ($_POST['option'][$auth_options[$i]['auth_value']] == ACL_DENY) ? ' checked="checked"' : '';
				$inherit_type = ($_POST['option'][$auth_options[$i]['auth_value']] == ACL_INHERIT) ? ' checked="checked"' : '';
			}
			else
			{
				$allow_type = ($auth[$auth_options[$i]['auth_value']] == ACL_ALLOW) ? ' checked="checked"' : '';
				$deny_type = ($auth[$auth_options[$i]['auth_value']] == ACL_DENY) ? ' checked="checked"' : '';
				$inherit_type = ($auth[$auth_options[$i]['auth_value']] == ACL_INHERIT) ? ' checked="checked"' : '';
			}

?>
	<tr>
		<td class="<?php echo $row_class; ?>"><?php echo $l_can_cell; ?></td>
		<td class="<?php echo $row_class; ?>" align="center"><input type="radio" name="option[<?php echo $auth_options[$i]['auth_value']; ?>]" value="<?php echo ACL_ALLOW; ?>"<?php echo $allow_type; ?> /></td>
		<td class="<?php echo $row_class; ?>" align="center"><input type="radio" name="option[<?php echo $auth_options[$i]['auth_value']; ?>]" value="<?php echo ACL_DENY; ?>"<?php echo $deny_type; ?> /></td>
		<td class="<?php echo $row_class; ?>" align="center"><input type="radio" name="option[<?php echo $auth_options[$i]['auth_value']; ?>]" value="<?php echo ACL_INHERIT; ?>"<?php echo $inherit_type; ?> /></td>
	</tr>
<?php

		}

		if ($type_sql == 'f' || $type_sql == 'm')
		{
			$children = get_forum_branch($forum_id, 'children', 'descending', false);

			if (!empty($children))
			{
?>
	<tr>
		<th colspan="4"><?php echo $user->lang['Inheritance']; ?></th>
	</tr>
	<tr>
		<td class="row1" colspan="4"><table width="100%" cellspacing="1" cellpadding="0" border="0">
			<tr>
				<td colspan="4" height="16"><span class="gensmall"><?php echo $user->lang['Inheritance_explain']; ?></span></td>
			</tr>
<?php
				foreach ($children as $row)
				{

?>
			<tr>
				<td><input type="checkbox" name="inherit[]" value="<?php echo $row['forum_id']; ?>" /> <?php echo $row['forum_name']; ?></td>
			</tr>
<?php

				}

?>
			<tr>
				<td height="16" align="center"><a class="gensmall" href="javascript:marklist('inherit', true);"><?php echo $user->lang['Mark_all']; ?></a> :: <a href="javascript:marklist('inherit', false);" class="gensmall"><?php echo $user->lang['Unmark_all']; ?></a></td>
			</tr>
		</table></td>
	</tr>
<?php

			}
		}

?>
	<tr>
		<td class="cat" colspan="4" align="center"><input class="mainoption" type="submit" name="update" value="<?php echo $user->lang['Update']; ?>" />&nbsp;&nbsp;<input class="liteoption" type="submit" name="cancel" value="<?php echo $user->lang['CANCEL']; ?>" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /><input type="hidden" name="type" value="<?php echo $_POST['type']; ?>" /><?php echo $ug_hidden; ?></td>
	</tr>
</table>

<br clear="all" />

<table class="bg" width="100%" cellspacing="1" cellpadding="4" border="0" align="center">
	<tr>
		<th colspan="4"><?php echo $user->lang['PRESETS']; ?></th>
	</tr>
	<tr>
		<td class="row1" colspan="4"><table width="100%" cellspacing="1" cellpadding="0" border="0">
			<tr>
				<td colspan="2" height="16"><span class="gensmall"><?php echo $user->lang['PRESETS_EXPLAIN']; ?></span></td>
			</tr>
			<tr>
				<td nowrap="nowrap"><?php echo $user->lang['SELECT_PRESET']; ?>: </td>
				<td><select name="presetoption"><option value="-1"><?php echo '-- ' . $user->lang['Select'] . ' --'; ?></option><?php 

		echo $preset_update_options;
	
?></select></td>
			</tr>
			<tr>
				<td nowrap="nowrap"><?php echo $user->lang['PRESET_NAME']; ?>: </td>
				<td><input type="text" name="presetname" maxlength="25" /> </td>
			</tr>
		</table></td>
	</tr>
	<tr>
		<td class="cat" colspan="4" align="center"><input class="liteoption" type="submit" name="presetsave" value="<?php echo $user->lang['SAVE']; ?>" /> &nbsp;<input class="liteoption" type="submit" name="presetdel" value="<?php echo $user->lang['DELETE']; ?>" /><input type="hidden" name="advanced" value="true" /></td>
	</tr>
</table></td>
	</tr>
</table></form>

<?php

	}

}
else
{

	$select_list = make_forum_select(false, false, false);

	page_header($l_title);

?>

<h1><?php echo $l_title; ?></h1>

<p><?php echo $l_title_explain ?></p>

<form method="post" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table class="bg" cellspacing="1" cellpadding="4" border="0" align="center">
	<tr>
		<th align="center"><?php echo $user->lang['Select_a_Forum']; ?></th>
	</tr>
	<tr>
		<td class="row1" align="center">&nbsp;<select name="f"><?php echo $select_list; ?></select> &nbsp;<input type="submit" value="<?php echo $user->lang['Look_up_Forum']; ?>" class="mainoption" />&nbsp;</td>
	</tr>
</table></form>

<?php

}

page_footer();

?>