<?
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################
/* This is a script to sync LDAP users and groups into Karnaf */

require("../ktools.php");

if(!isset($argv[1]) || $argv[1]!="force") {
  if(file_exists("/tmp/karnaf-fetch-users.lock")) {
    safe_die("Error: lock file exists!");
  }
}

$fp = fopen("/tmp/karnaf-fetch-users.lock", "w");
fwrite($fp, "locked");
fclose($fp);

/* Cache all existing users first... */
$cached_users = array();
$query = squery("SELECT user FROM users");
while($result = sql_fetch_array($query)) {
  $cached_users[] = strtolower($result['user']);
}
sql_free_result($query);

/* Cache all existing groups too... */
$cached_groups = array();
$query = squery("SELECT name FROM groups");
while($result = sql_fetch_array($query)) {
  $cached_groups[] = $result['name'];
}
sql_free_result($query);

/* Cache all group members... */
$cached_groupmembers = array();
$query = squery("SELECT u.user,g.name FROM (group_members AS gm INNER JOIN users AS u ON gm.user_id=u.id INNER JOIN groups AS g ON gm.group_id=g.id)");
while($result = sql_fetch_array($query)) {
  $cached_groupmembers[$result['name']][] = strtolower($result['user']);
}
sql_free_result($query);

/* Start to check our LDAP accounts... */
$query = squery("SELECT type,host,user,pass,ou,filter FROM karnaf_ldap_accounts WHERE active=1");
while($result = sql_fetch_array($query)) {
  echo "Checking ".$result['host']."...\n";
  $type = (int)$result['type'];
  # Types:
  # 1 = LDAP
  # 2 = LDAPS
  if($type == 0) $ldapserver = "ldap://".$result['host'];
  else if($type == 1) $ldapserver = "ldaps://".$result['host'];
  else {
    echo "Error: unknown ldap account type. Skipping...\n";
    continue;
  }
  $lastsync = time();
  $ldap = ldap_connect($ldapserver);
  ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
  ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
  if($bind = @ldap_bind($ldap, $result['user'], $result['pass'])) {
    $attr = array("company","mail","title","department","givenname","sn","telephonenumber","mobile","physicaldeliveryofficename","samaccountname","manager","distinguishedName","memberof");
    if($result = ldap_search($ldap, $result['ou'], $result['filter'], $attr)) {
      ldap_sort($ldap, $result, "sn");
      $entries = ldap_get_entries($ldap, $result);
      foreach($entries as $entry) {
        $found_user = $entry['samaccountname'][0];
        if(empty($found_user)) continue;
        if(isset($entry['mail'][0])) $found_email = $entry['mail'][0];
        else $found_email = "";
        if(isset($entry['givenname'][0])) $found_fname = $entry['givenname'][0];
        else $found_fname = "";
        if(isset($entry['sn'][0])) $found_lname = $entry['sn'][0];
        else $found_lname = "";
        $found_fullname = $found_fname." ".$found_lname;
        if(isset($entry['company'][0])) $found_department = $entry['company'][0];
        else $found_department = "";
        if(isset($entry['department'][0])) $found_team = $entry['department'][0];
        else $found_team = "";
        if(isset($entry['title'][0])) $found_title = $entry['title'][0];
        else $found_title = "";
        if(isset($entry['mobile'][0])) $found_mobile = $entry['mobile'][0];
        else $found_mobile = "";
        if(isset($entry['physicaldeliveryofficename'][0])) $found_room = $entry['physicaldeliveryofficename'][0];
        else $found_room = "";
        $found_groups = array();
        if(isset($entry['memberof'])) {
          foreach($entry['memberof'] as $grps) {
            if(substr($grps,0,3) == "CN=") {
              $pos = strpos($grps,",");
              if(!$pos) continue;
              $grp = substr($grps,3,$pos-3);
              $found_groups[] = $grp;
            }
          }
        }
        /* Add/update the user */
        if(in_array(strtolower($found_user),$cached_users)) {
          echo "Found user: ".$found_user."\n";
          squery("UPDATE users SET email='%s',fullname='%s',fname='%s',lname='%s',department='%s',team='%s',title='%s',phone='%s',room='%s',lastsync=%d WHERE user='%s'",
                 $found_email, $found_fullname, $found_fname, $found_lname, $found_department, $found_team,
                 $found_title, $found_mobile, $found_room,
                 $lastsync, $found_user);
        }
        else {
          echo "New user: ".$found_user."\n";
          squery("INSERT INTO users(user,pass,email,regtime,fullname,fname,lname,department,team,title,phone,room,lastsync) VALUES('%s','','%s',%d,'%s','%s','%s','%s','%s','%s','%s','%s',%d)",
                 $found_user, $found_email, time(), $found_fullname, $found_fname, $found_lname, $found_department,
                 $found_team, $found_title, $found_mobile, $found_room,
                 $lastsync);
        }
        /* Update the groups */
        foreach($found_groups as $group) {
          if(!in_array($group,$cached_groups)) continue; /* Only update existing groups... */
          if(!isset($cached_groupmembers[$group]) || !in_array(strtolower($found_user),$cached_groupmembers[$group])) {
            /* User is not on the cached group, add it to Karnaf... */
            squery("INSERT INTO group_members(group_id,user_id,added_by,added_time) VALUES((SELECT id FROM groups WHERE name='%s'),(SELECT id FROM users WHERE user='%s'),'System',%d)",
                   $group, $found_user, time());
          }
        }
        foreach($cached_groups as $group) {
          if(isset($cached_groupmembers[$group]) && in_array($found_user,$cached_groupmembers[$group]) && !in_array($group, $found_groups)) {
            /* User found on cached group but not on LDAP group, user was probably deleted so delete it from Karnaf */
            squery("DELETE FROM group_members WHERE group_id=(SELECT id FROM groups WHERE name='%s') AND user_id=(SELECT id FROM users WHERE user='%s')",
                   $group, $found_user);
          }
        }
      }
    }
  }
  ldap_unbind($ldap);
}
sql_free_result($query);
unlink("/tmp/karnaf-fetch-users.lock");
require_once("../contentpage_ftr.php");
?>
