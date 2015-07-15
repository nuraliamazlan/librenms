<?php

$ds = @ldap_connect($config['auth_ldap_server'],$config['auth_ldap_port']);

if ($config['auth_ldap_starttls'] && ($config['auth_ldap_starttls'] == 'optional' || $config['auth_ldap_starttls'] == 'require'))
{
  $tls = ldap_start_tls($ds);
  if ($config['auth_ldap_starttls'] == 'require' && $tls == FALSE)
  {
   echo("<h2>Fatal error: LDAP TLS required but not successfully negotiated:" . ldap_error($ds) . "</h2>");
   exit;
  }
}

function authenticate($username,$password)
{
  global $config, $ds;
  
  if ($username && $ds)
  {
    if ($config['auth_ldap_version'])
    {
      ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $config['auth_ldap_version']);
    }
    if (ldap_bind($ds, $config['auth_ldap_prefix'] . $username . $config['auth_ldap_suffix'], $password))
    {
      if (!$config['auth_ldap_group'])
      {
        return 1;
      }
      else
      {
        $ldap_groups = get_group_list();
        foreach($ldap_groups as $ldap_group) {
          $ldap_comparison = ldap_compare($ds,
                                          $ldap_group, 
                                          $config['auth_ldap_groupmemberattr'],
                                          get_membername($username));
          if($ldap_comparison === true) {
            return 1;
          }
        }
      }
    }
    else
    {
      echo(ldap_error($ds));
    }
  }
  else
  {
    // FIXME return a warning that LDAP couldn't connect?
  }

  return 0;
}

function reauthenticate($sess_id,$token)
{
  return 0;
}

function passwordscanchange($username = "")
{
  return 0;
}

function changepassword($username,$newpassword)
{
  # Not supported (for now)
}

function auth_usermanagement()
{
  return 0;
}

function adduser($username, $password, $level, $email = "", $realname = "", $can_modify_passwd = '1')
{
  # Not supported
  return 0;
}

function user_exists($username)
{
  global $config, $ds;

  $filter = "(" . $config['auth_ldap_prefix'] . $username . ")";
  $search = ldap_search($ds, trim($config['auth_ldap_suffix'],','), $filter);
  $entries = ldap_get_entries($ds, $search);
  if ($entries['count'])
  {
    return 1;
  }

  return 0;
}

function get_userlevel($username)
{
  global $config, $ds;

  $userlevel = 0;

  # Find all defined groups $username is in
  $filter = "(&(|(cn=" . join(")(cn=", array_keys($config['auth_ldap_groups'])) . "))(". $config['auth_ldap_groupmemberattr']. "=" . get_membername($username) . "))";
  $search = ldap_search($ds, $config['auth_ldap_groupbase'], $filter);
  $entries = ldap_get_entries($ds, $search);

  # Loop the list and find the highest level
  foreach ($entries as $entry)
  {
    $groupname = $entry['cn'][0];
    if ($config['auth_ldap_groups'][$groupname]['level'] > $userlevel)
    {
      $userlevel = $config['auth_ldap_groups'][$groupname]['level'];
    }
  }

  return $userlevel;
}

function get_userid($username)
{
  global $config, $ds;

  $filter = "(" . $config['auth_ldap_prefix'] . $username . ")";
  $search = ldap_search($ds, trim($config['auth_ldap_suffix'],','), $filter);
  $entries = ldap_get_entries($ds, $search);

  if ($entries['count'])
  {
    return $entries[0]['uidnumber'][0];
  }

  return -1;
}

function deluser($username)
{
  # Not supported
  return 0;
}

function get_userlist()
{
  global $config, $ds;
  $userlist = array();

  $filter = '(' . $config['auth_ldap_prefix'] . '*)';

  $search = ldap_search($ds, trim($config['auth_ldap_suffix'],','), $filter);
  $entries = ldap_get_entries($ds, $search);

  if ($entries['count'])
  {
    foreach ($entries as $entry)
    {
      $username = $entry['uid'][0];
      $realname = $entry['cn'][0];
      $user_id  = $entry['uidnumber'][0];
      $email    = $entry[$config['auth_ldap_emailattr']][0];
      $ldap_groups = get_group_list();
      foreach($ldap_groups as $ldap_group) {
        $ldap_comparison = ldap_compare($ds,
                                        $ldap_group, 
                                        $config['auth_ldap_groupmemberattr'],
                                        get_membername($username));
        if (!isset($config['auth_ldap_group']) || $ldap_comparison === true) {
          $userlist[] = array('username' => $username, 'realname' => $realname, 'user_id' => $user_id, 'email' => $email);
        }
      }
    }
  }

  return $userlist;
}

function can_update_users()
{
  # not supported so return 0
  return 0;
}

function get_user($user_id)
{
  # not supported
  return 0;
}

function update_user($user_id,$realname,$level,$can_modify_passwd,$email)
{
  # not supported
  return 0;
}

function get_membername ($username)
{
  global $config;
  if ($config['auth_ldap_groupmembertype'] == "fulldn") 
  {
    $membername = $config['auth_ldap_prefix'] . $username . $config['auth_ldap_suffix'];
  }
  else
  {
    $membername = $username;
  }
  return $membername;
}

function get_group_list() {
  global $config;

  $ldap_groups = array();
  $default_group = 'cn=groupname,ou=groups,dc=example,dc=com';
  if(isset($config['auth_ldap_group'])) {
    if($config['auth_ldap_group'] !== $default_group) {
      $ldap_groups[] = $config['auth_ldap_group'];
    }
  }
  foreach($config['auth_ldap_groups'] as $key => $value) {
    $dn = "cn=$key," . $config['auth_ldap_groupbase'];
    $ldap_groups[] = $dn;
  }
  return $ldap_groups;
}

?>
