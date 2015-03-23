<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("ktools.php");
check_auth();
function load_scripts() {
  global $themepath,$loaded_scripts;

  if(!isset($loaded_scripts)) {
    echo "<!-- Scripts Here -->\n";
    load_template($themepath."/scripts.html");
    $loaded_scripts = 1;
  }
}

$themes = array(
               "default",
              );
$theme = "default";
$themepath = "themes/default/";
if(isset($_COOKIE['theme'])) {
  foreach($themes as $x) {
    if($_COOKIE['theme'] == $x) {
      $theme = $x;
      $themepath = "themes/".$x."/";
    }
  }
}
if(isset($_GET['theme'])) {
  foreach($themes as $x) {
    if($_GET['theme'] == $x) {
      $theme = $x;
      $themepath = "themes/".$x."/";
      if(isset($_GET['settheme'])) {
        setcookie("theme", $theme, time()+(3600*24*14), "/", MY_DOMAIN);
      }
    }
  }
}
$page_width = "675";
if(!isset($title)) $title = "Karnaf";
if(isset($title_callback)) $title = $title_callback();
/* Get the current URL... */
$myurl = "http://".$_SERVER['SERVER_NAME'];
if(substr($_SERVER['PHP_SELF'],-10,10) == "/index.php") $myurl .= substr($_SERVER['PHP_SELF'],0,-9);
else $myurl .= $_SERVER['PHP_SELF'];
if(isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) $myurl .= "?".$_SERVER['QUERY_STRING'];
load_template($themepath."/header.html");
$contentpage_hdr = 1;
if(isset($error)) echo "<div class=\"status_err\">Error: ".$error."</div>\r\n";
echo "<!-- Content Here -->\n";
?>
