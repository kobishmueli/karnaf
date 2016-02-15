<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2016 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################
/* KTools v1.5 */

require_once("defines.php");
define("KARNAF_VERSION", "5.0.14");
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
set_magic_quotes_runtime(0);
if(!isset($override_magicquotes) && get_magic_quotes_gpc() == 1) die("Error: Incorrect magic_quotes_gpc setting!");
if(function_exists("date_default_timezone_set")) date_default_timezone_set("UTC");

/* safe_die - same as die() but also adds the footer and closes the database connection */
if(!function_exists("safe_die")) {
  function safe_die($reason="", $boxtitle="Application Error") {
    global $connection,$contentpage_hdr,$nohotspots,$nick,$skinpath,$menus2,$myurl;
    if($reason != "none") {
      echo "<font color=\"Black\"><table width=\"300\">";
      echo "<tr><td bgcolor=\"red\" align=\"center\">";
      echo "<b>".$boxtitle."</b></td></tr>";
      echo "<tr><td bgcolor=\"#EEEEEE\" align=\"left\">";
      echo str_replace("<","&lt;",$reason);
      echo "</td></tr></table></font>";
    }
    if(isset($contentpage_hdr)) {
       if(file_exists("contentpage_ftr.php")) require_once("contentpage_ftr.php");
       else if(file_exists("../contentpage_ftr.php")) require_once("../contentpage_ftr.php");
       else if(file_exists("../../contentpage_ftr.php")) require_once("../../contentpage_ftr.php");
    }
    else {
      if(isset($connection)) mysql_close($connection);
    }
    die();
  }
}

/* get_session_ip - get the user's (real) IP */
if(!function_exists("get_session_ip")) {
  function get_session_ip() {
    if(!isset($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['REMOTE_ADDR'];
    if($_SERVER['REMOTE_ADDR'] == "127.0.0.1") return $_SERVER['HTTP_X_REAL_IP'];

    return $_SERVER['REMOTE_ADDR'];
  }
}

/* Hack for PHP4, yay :( -Kobi. */
if(!function_exists("str_ireplace")) {
  function str_ireplace($x,$y,$z) {
    return str_replace($x,$y,$z);
  }
}

/* fix_html - remove < & > tags */
if(!function_exists("fix_html")) {
  function fix_html($text) {
    $text = str_replace("<","&lt;",$text);
    $text = str_replace(">","&gt;",$text);

    return $text;
  }
}

/* add_log - add logging event into the ws_logs table */
if(!function_exists("add_log")) {
  function add_log($logtype, $action) {
    global $nick;

    squery("INSERT INTO ws_logs(date,action,user,logtype,ip) VALUES(%d,'%s','%s','%s','%s')",
           time(), $action, $nick, $logtype, get_session_ip());

    return 1;
  }
}

/* isodd - check if a number is an odd number or not */
if(!function_exists("isodd")) {
  function isodd($i) {
    if($i % 2) return 1;
    return 0;
  }
}

/* send_mail - send a mail to a user (actually adds it to the mailing queue for sending later) */
if(!function_exists("send_mail")) {
  function send_mail($to,$subject,$body) {
    if(empty($to)) return 0;
    if(!strstr($to,'@')) return 0;
    squery("INSERT INTO mail_queue(mail_to,mail_subject,mail_body) VALUES('%s','%s','%s')", $to, $subject, $body);
    return 1;
  }
}

/* showyesno - return a textual Yes/No response to 1/0 */
if(!function_exists("showyesno")) {
  function showyesno($val) {
    if($val == 1) return "Yes";
    else return "No";
  }
}

/* RandomNumber - generate a random number */
if(!function_exists("RandomNumber")) {
  function RandomNumber($length=32) {
    $randstr='';
    srand((double)microtime()*100-553);
    //our array add all letters and numbers if you wish
    $chars = array ('0','1','2','3','4','5','6','7','9');
    for($rand = 0; $rand <= $length; $rand++) {
      $random = rand(0, count($chars) -1);
      $randstr .= $chars[$random];
    }

    return $randstr;
  }
}

/* is_backup_running - check if a backup process is running */
if(!function_exists("is_backup_running")) {
  function is_backup_running() {
    return file_exists("/tmp/doing-backup");
  }
}

/* IsKarnafAdminSession - check if the user has Karnaf Admin access */
function IsKarnafAdminSession() {
  global $a_groups,$a_operlev;
  if(in_array(KARNAF_ADMINS_GROUP, $a_groups)) return 1;
  if($a_operlev==80) return 1;
  else return 0;
}

/* IsKarnafOperSession - check if the user has Karnaf Operator access */
function IsKarnafOperSession() {
  global $a_groups,$a_operlev;
  if(in_array(KARNAF_ADMINS_GROUP, $a_groups) || in_array(KARNAF_OPERS_GROUP, $a_groups)) return 1;
  if($a_operlev==80) return 1;
  else return 0;
}

/* CheckOperSession - check if the user has Karnaf Operator access and exit otherwise */
if(!function_exists("CheckOperSession")) {
  function CheckOperSession($requiredacc = 0) {
    global $a_groups,$a_operlev;
    $res = 0;
    if(in_array(KARNAF_ADMINS_GROUP, $a_groups) || in_array(KARNAF_OPERS_GROUP, $a_groups)) $res = 1;
    if($res != 1) AccessDenied("This page is limited to Server Operators.");
    if($a_operlev < $requiredacc) AccessDenied("This page is limited to $requiredacc.");

    return $res;
  }
}

/* IsGroupMember - check if the user is a member of a group */
if(!function_exists("IsGroupMember")) {
  function IsGroupMember($group_name) {
    global $a_groups;

    if(in_array($group_name, $a_groups)) return 1;
    else return 0;
  }
}

/* show_title - nice way to show the page title */
if(!function_exists("show_title")) {
  function show_title($title) {
    echo "<h5>$title</h5>\n";
  }
}

if(!function_exists("squery")) {

/* sql_connect - connect to the mysql server */
if(!function_exists("sql_connect")) {
function sql_connect() {
  global $connection;

  if(!isset($connection)) {
    error_reporting(0);
    $connection = mysql_connect(DB_HOST,DB_USER,DB_PASS) or die("Error: Can't connect to mysql!");
    mysql_select_db(DB_DB) or die("Error: Can't select mysql database!");
    error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
  }
}
}

/* squery - helper function for real_squery() */
function squery() {
    $argv = func_get_args();
    if(is_numeric($argv[0]) && (int)$argv[0]==0) {
      array_shift($argv);
    }
    return real_squery($argv);
}

/* squery_args - helper function for real_squery() */
function squery_args($argv) {
    return real_squery($argv);
}

/* real_squery - function to make mysql queries more secure by validating the arguments and make sure they are escaped correctly
   Usage:
   squery("UPDATE table SET x=%d", $number);
   or:
   squery("UPDATE table SET x='%s'", $text);

  Valid options:
  %s - text
  %d - number
  %% - %
 */
function real_squery($argv) {
  global $connection;

  $argc = 1;
  $orgstr = $argv[0];
  $newstr = "";
  $len = strlen($orgstr);

  if($len > 40000) {
    // Better safe than sorry :)
    safe_die("SQUERY Error: Query is too long!");
  }

  for ($i = 0; $i < $len; $i++) {
    if($orgstr[$i]=="%") {
      $i++;
      if($orgstr[$i] == "s") {
        if($orgstr[$i-2]!="'" || ($orgstr[$i+1]!="'" && $orgstr[$i+1]!="%" && $orgstr[$i+2]!="%" && $orgstr[$i+3]!="'")) safe_die("SQUERY Error: String is not quoted correctly!");
        $i++;
        $newstr .= mysql_real_escape_string($argv[$argc]);
        $argc++;
      }
      elseif($orgstr[$i] == "d") {
        $i++;
        if(!is_numeric($argv[$argc])) safe_die("SQUERY Error: ".$argv[$argc]." is not a number!");
        $newstr .= $argv[$argc];
        $argc++;
      }
      if($i >= $len) break;
    }
    $newstr .= $orgstr[$i];
  }

  sql_connect();

  $query = mysql_query($newstr) or safe_die("SQL Error: ".mysql_error());

  return $query;
}

function sql_fetch_array(&$x) {
  if(is_array($x)) {
    if(sizeof($x) == 0) return FALSE;
    $fruit = array_shift($x);
    return $fruit;
  }

  return mysql_fetch_array($x);
}

function sql_free_result(&$x) {
  if(is_array($x)) {
    unset($x);
    return;
  }
  return mysql_free_result($x);
}

function sql_num_rows($x) {
  if(is_array($x)) return sizeof($x);

  return mysql_num_rows($x);
}

function sql_insert_id($x = "") { return mysql_insert_id(); }

function sqldiff_check($query, $var) {
  global $sqldiff_result,$sqldiff_query,$sqldiff_var;

  $sqldiff_query = $query;
  $sqldiff_var = $var;
  $query = squery($sqldiff_query, $sqldiff_var);
  $sqldiff_result = sql_fetch_array($query);
  sql_free_result($query);
}

function sqldiff_compare() {
  global $sqldiff_result,$sqldiff_query,$sqldiff_var;

  $query = squery($sqldiff_query, $sqldiff_var);
  $sqldiff_result2 = sql_fetch_array($query);
  sql_free_result($query);

  $res = "";

  foreach(array_keys($sqldiff_result) as $row) {
    if(is_numeric($row)) continue;
    if($sqldiff_result[$row] != $sqldiff_result2[$row]) $res .= $row.": ".$sqldiff_result[$row]." --> ".$sqldiff_result2[$row]."\n";
  }

  unset($sqldiff_query);
  unset($sqldiff_var);
  unset($sqldiff_result);

  return $res;
}

} # end of function_exists("squery")

if(!function_exists("check_auth")) {
function check_auth($xhost="") {
  global $nick,$a_user,$a_id,$a_operlev,$a_groups,$a_flags,$a_email,$a_timezone,$a_fullname,$error,$no_cache,$nologin;

  if(function_exists("check_access")) { return check_access(); }

  if($xhost == "") $xhost = $_SERVER['REMOTE_ADDR']; /* This is to let the login API "impersonate" IPs... */

  if(isset($_COOKIE[COOKIE_NICK])) $cnick = $_COOKIE[COOKIE_NICK];
  if(isset($_COOKIE[COOKIE_KEY])) $ckey = $_COOKIE[COOKIE_KEY];

  if(isset($_POST['user']) && isset($_POST['pass'])) {
    $cnick = $_POST['user'];
    $cpassword = $_POST['pass'];
    $ckey = "";
  }

  if(!isset($nologin) && isset($cnick) && !empty($cnick) && ($cnick != "Guest")) {
    $query = squery("SELECT id,user,pass,operlev,flags,email,regtime,lasthost,lasttime,fullname FROM users WHERE user='%s' OR email='%s'", $cnick, $cnick);
    $result = sql_fetch_array($query);
    if($result) {
      $cnick = $result['user'];
      if((isset($cpassword) && md5($cpassword)==$result['pass']) || (isset($ckey) && md5(COOKIE_HASH.$result['pass'])==$ckey)) {
        # If we got here, it's good... either valid password or valid cookie
        $nick = $result['user'];
        $a_user = $result['user'];
        $a_id = (int)$result['id'];
        $a_user = $result['user'];
        $a_flags = (int)$result['flags'];
        $a_timezone = 0;
        $a_operlev = (int)$result['operlev'];
        $a_groups = array("all-users");
        $a_email = $result['email'];
        $a_fullname = $result['fullname'];
        if(empty($a_email)) {
          # If the user is not fully-registered...
          $error = "The registration process for the username <b>".$nick."</b> is not completed yet.";
          $nick = "";
        }
        if(empty($result['pass'])) {
          # If the user doesn't have a password...
          $error = "The username <b>".$nick."</b> does not have a password set.";
          $nick = "";
        }
        if($ckey!="" && $result['lasthost'] != $xhost) {
          # If the user's IP got changed, force them to re-login..
          $nick = "";
          $error = "Your session has expired, please re-login.";
        }
        $query2 = squery("SELECT g.name,m.level FROM (group_members AS m INNER JOIN groups AS g ON g.id=m.group_id) WHERE m.user_id=%d", $result['id']);
        while($result2 = sql_fetch_array($query2)) {
          array_push($a_groups, $result2['name']);
          if((int)$result2['level'] >= 50) {
            array_push($a_groups, $result2['name']."-lead");
            array_push($a_groups, "all-leads");
          }
        }
        sql_free_result($query2);
        $ckey = md5(COOKIE_HASH.$result['pass']);
        if(isset($_POST['user']) && isset($_POST['pass']) && !isset($error)) {
          squery("UPDATE users SET lasthost='%s',lasttime=%d WHERE user='%s'", $xhost, time(), $nick);
          if(isset($_GET['id'])) add_log($_SERVER['PHP_SELF']."?id=".$_GET['id'], "LOGIN");
          else add_log($_SERVER['PHP_SELF'], "LOGIN");
        }
      }
      else {
        $error = "Incorrect password for ".$cnick;
      }
    }
    else $error = "The user is not registered.";
    sql_free_result($query);
  }

  if(isset($nick) && !isset($error)) {
    setcookie(COOKIE_NICK, $nick, time()+(3600*24*14), "/", MY_DOMAIN);
    setcookie(COOKIE_KEY, $ckey, time()+(3600*24*14), "/", MY_DOMAIN);
    return 1;
  }

  $nick = "Guest";
  $a_user = "Guest";
  $a_fullname = "";
  $a_id = 0;
  $a_timezone = 0;
  $a_flags = 0;
  $a_operlev = 0;
  $a_groups = array("all-guests");

  return 0;
}
} # end of function_exists("check_auth")

if(!function_exists("load_template")) {
  function load_template($file) {
    global $title,$theme,$a_user;

    if(!is_file($file)) $file = "../".$file;
    if(!is_file($file)) $file = "../".$file;
    if(!is_file($file)) safe_die("Can't find template file!", "Theme Error");
    $handle = fopen($file,"r");
    if(!$handle) safe_die("Can't open template!", "Theme Error");
    while(!feof($handle)) {
      $buffer = str_replace("%TITLE%",$title,fgets($handle, 4096));
      $buffer = str_replace("%MY_URL%",MY_URL,$buffer);
      $buffer = str_replace("%THEMEPATH%",MY_URL."/themes/".$theme,$buffer);
      $buffer = str_replace("%USER%",$a_user,$buffer);
      #For future use:
      #if($a_lang==1) $buffer = str_replace("%DIR%"," dir=\"rtl\"",$buffer);
      #else $buffer = str_replace("%DIR%","",$buffer);
      if(strstr($buffer, "%MENUS%")) $buffer = str_replace("%MENUS%",get_menus(),$buffer);
      /* TODO: Add more variables, and optimize the code... */
      echo $buffer;
    }
    fclose($handle);
  }
}

if(!function_exists("AccessDenied")) {
  function AccessDenied($reason="You don't have access to view the page you requested.") {
    global $nick;

    if(!empty($reason)) show_title($reason);
    if($nick == "Guest") {
?>
<b>Please login using your username and nick password:</b>
<form method="post">
<table>
<tr>
<td>Username:</td>
<td><input type="text" name="user"></td>
</tr>
<tr>
<td>Password:</td>
<td><input type="password" name="pass" maxlength="50"></td>
</tr>
<tr><td colspan="2"><input type="submit" value="Login"></td></tr>
</table>
</form>
<?
    }
    safe_die("none");

    return 1;
  }
}

if(!function_exists("datetounixtime")) {
  function datetounixtime($date) {
    global $a_timezone;
    list($day, $month, $year) = split('[/.-]', $date);
    return mktime(0, 0, 0, $month, $day, $year) - $a_timezone;
  }
}

if(!function_exists("showtime")) {
  function showtime($timevar) {
    global $a_timezone;
    return strftime("%d/%m/%Y %R", $timevar + $a_timezone);
  }
}

if(!function_exists("showdate")) {
  function showdate($timevar) {
    global $a_timezone;
    return strftime("%d/%m/%Y", $timevar + $a_timezone);
  }
}

if(!function_exists("do_duration")) {
  function do_duration($orgvar) {
    $y = 0;
    $d = 0;
    $h = 0;
    $m = 0;
    $res = "";
    while($orgvar >= 31536000) {
      $orgvar = $orgvar - 31536000;
      $y = $y++;
    }
    while($orgvar >= 86400) {
      $orgvar = $orgvar - 86400;
      $d++;
    }
    while($orgvar >= 3600) {
      $orgvar = $orgvar - 3600;
      $h++;
    }
    while($orgvar >= 60) {
      $orgvar = $orgvar - 60;
      $m++;
    }
    $s = $orgvar;
    if($y > 0) $res = $y."y";
    if($d > 0) $res = $res.$d."d";
    if($h > 0) $res = $res.$h."h";
    if($m > 0) $res = $res.$m."m";
    if($s > 0) $res = $res.$s."s";
    return $res;
  }
}

if(!function_exists("gethttplink")) {
  function gethttplink($x) {
    return "<a href=\"".$x[0]."\">".$x[0]."</a>";
  }
}

if(!function_exists("show_board_body")) {
  function show_board_body($body) {
    $body = str_replace("<","&lt;",$body);
    $body = str_replace(">","&gt;",$body);
    $body = str_replace("[b]","<b>",$body);
    $body = str_replace("[/b]","</b>",$body);
    $body = str_replace("[i]","<i>",$body);
    $body = str_replace("[/i]","</i>",$body);
    $body = str_replace("[u]","<u>",$body);
    $body = str_replace("[/u]","</u>",$body);
    $body = str_replace("[system]","<div class=\"system\">",$body);
    $body = str_replace("[/system]","</div>",$body);
    $body = str_replace("[table]\r\n","<table>",$body);
    $body = str_replace("[/table]\r\n","</table>",$body);
    $body = str_replace("[/table]","</table>",$body);
    $body = str_replace("[tr]\r\n","<tr>",$body);
    $body = str_replace("[/tr]\r\n","</tr>",$body);
    $body = str_replace("[td]\r\n","<td class=\"kb_td\">",$body);
    $body = str_replace("[td]","<td class=\"kb_td\">",$body);
    $body = str_replace("[td]\r\n","<td class=\"kb_td\">",$body);
    $body = str_replace("[/td]\r\n","</td>",$body);
    $body = str_replace("[th]\r\n","<th class=\"kb_th\">",$body);
    $body = str_replace("[th]","<th class=\"kb_th\">",$body);
    $body = str_replace("[th]","<th class=\"kb_th\">",$body);
    $body = str_replace("[/th]\r\n","</th>",$body);
    $body = str_ireplace("[ol]\r\n","<ol>",$body);
    $body = str_ireplace("[/ol]\r\n","</ol>",$body);
    $body = str_ireplace("[ol]","<ol>",$body);
    $body = str_ireplace("[/ol]","</ol>",$body);
    $body = str_ireplace("[li]","<li>",$body);
    $body = str_ireplace("[/li]","</li>",$body);
    $body = str_ireplace("[ul]","<ul>",$body);
    $body = str_ireplace("[/ul]","</ul>",$body);
    $body = str_ireplace("[pre]","<pre>",$body);
    $body = str_ireplace("[/pre]","</pre>",$body);
    $body = str_ireplace("[small]","<small>",$body);
    $body = str_ireplace("[/small]","</small>",$body);
    $body = str_replace("\n","<br>\n",$body);
    $body = preg_replace_callback(
        '|(http(s)?://[a-zA-Z0-9./_\:\-?=#~+]{1,100}(\?[a-zA-Z0-9./_\:\-=#~&+]{1,100})?)([\w/])|',
        "gethttplink",
        $body
    );
    echo $body;
  }
}

if(!function_exists("merge_array")) {
  function merge_array($arr) {
    $res = "";
    foreach($arr as $x) {
      if($res <> "") $res .= ",";
      if(is_array($x)) $res .= $x[0];
      else $res .= $x;
    }

    return $res;
  }
}

if(!function_exists("htmlspecialchars2")) {
  function htmlspecialchars2($text) {
    /* PHP 5.4.0+ defaults the charset to UTF-8 instead of ISO-8859-1... */
    if(!defined("ENT_COMPAT") || !defined("ENT_HTML401")) return htmlspecialchars($text);
    return htmlspecialchars($text,ENT_COMPAT | ENT_HTML401, "ISO-8859-1");
  }
}

/* coolsize - convert size in bytes to human-readable format */
if(!function_exists("coolsize")) {
  function coolsize($bytes) {
    $b = (int)$bytes;
    $s = array('B', 'KB', 'MB', 'GB', 'TB');
    if($b < 0) return "0".$s[0];
    $con = 1024;
    $e = (int)(log($b,$con));
    return number_format($b/pow($con,$e),2,'.','.').' '.$s[$e];
  }
}

/* make_menus - unused function (reserved for DALnet) */
if(!function_exists("make_menus")) { function make_menus($menu_id) { } }

/* send_memo - unused function (reserved for DALnet) */
if(!function_exists("send_memo")) { function send_memo($to,$body) { } }

/* API function to create new tickets */
function api_create_ticket($unick, $uname, $uemail, $title, $description, $uip, $rep_g, $cat3_id=71, $ext1="") {
  $randstr = RandomNumber(10);
  $email_upd = 1;
  $memo_upd = 1;
  $uphone = "";
  $rep_u = "";
  $is_real = 0;
  $is_private = 0;
  $upriority = 0;
  $priority = 0;
  squery("INSERT INTO karnaf_tickets(randcode,status,title,description,cat3_id,unick,ufullname,uemail,uphone,uip,upriority,priority,open_time,opened_by,rep_u,rep_g,is_real,is_private,email_upd,memo_upd) VALUES('%s',%d,'%s','%s','%d','%s','%s','%s','%s','%s',%d,%d,%d,'%s','%s','%s',%d,%d,%d,%d)",
         $randstr,1,$title,$description,$cat3_id,$unick,fix_html($uname),$uemail,$uphone,$uip,$upriority,$priority,time(),"(API)",$rep_u,
         $rep_g,$is_real,$is_private,$email_upd,$memo_upd);
  $id = sql_insert_id();
  if(!empty($ext1)) squery("UPDATE karnaf_tickets SET ext1='%s' WHERE id=%d", $ext1, $id);
  return $id;
}

function send_sms($sms_account, $sms_to, $sms_body) {
  $res = 0;
  $sms_to = trim($sms_to);
  $sms_body = trim($sms_body);
  if(empty($sms_to)) return 0;
  if(empty($sms_body)) return 0;
  $query = squery("SELECT type,account_id,account_token,from_number FROM karnaf_sms_accounts WHERE id=%d AND active=1", $sms_account);
  if($result = sql_fetch_array($query)) {
    if((int)$result['type'] != 0) safe_die("Unknown SMS account type!");
    $post_data = array(
                       "To" => $sms_to,
                       "From" => $result['from_number'],
                       "Body" => $sms_body,
                 );
    $post_string = http_build_query($post_data);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($ch, CURLOPT_USERPWD, $result['account_id'].":".$result['account_token']);
    curl_setopt($ch, CURLOPT_URL,"https://api.twilio.com/2010-04-01/Accounts/".$result['account_id']."/Messages.json");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    if(isset($result)) {
      $result_json = json_decode($result, true);
      if($result_json['status'] == "queued") $res = 1;
    }
    else if(curl_errno($ch)) $res = 0;
    curl_close($ch);
  }
  sql_free_result($query);

  return $res;
}

function endsWith($haystack, $needle) {
  return substr($haystack, -strlen($needle))===$needle;
}

if(!function_exists("custom_new_ticket_welcome")) { function custom_new_ticket_welcome() { } }
if(!function_exists("custom_new_ticket_thankyou")) { function custom_new_ticket_thankyou() { } }
if(!function_exists("custom_new_ticket_ext1_check")) { function custom_new_ticket_ext1_check($ext1) { } }
if(!defined("USER_FIELD")) define("USER_FIELD", "Username");
if(!function_exists("custom_view_row_info")) { function custom_view_row_info($row, $value, $isoper) { echo $value; } }
if(!function_exists("custom_view_more")) { function custom_view_more($result, $isoper) { } }

?>
