<?php
/*
Plugin Name: BMRE Domain Monitor
Plugin URI: http://ras-pi.co/bmre_domain_monitor
Description: Keeps track on your domains expiration and warns you before it is about to expire
Version: 0.9.1
Author: David V. Wallin
Author URI: http://ras-pi.co
License: GPLv3
*/
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>. 
*/

include('lib/whois.main.php');
$plugin_path = '/tmp/';
$host = get_bloginfo('url');
$replacement_array_host = array("https", "http", ":", "/", "www");
$host = str_replace($replacement_array_host, '', $host);
$whois = new Whois();
global $bmre_domain_monitor_db_version;
$bmre_domain_monitor_db_version = "1.0";

function bmre_domain_monitor_install() {
   global $wpdb;
   global $bmre_domain_monitor_db_version;

   $table_name = $wpdb->prefix . "bmre_domain_monitor";
   $second_table_name = $wpdb->prefix . "bmre_domain_monitor_domain_checker";
   $settings_table_name = $wpdb->prefix . "bmre_domain_monitor_settings";
      
   $sql = "CREATE TABLE $table_name (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  domain varchar(128) NOT NULL,
  expires date NOT NULL,
  last_check date NOT NULL,
  notified int(1) NOT NULL DEFAULT '0',
  UNIQUE KEY id (id)
    );";
      
   $second_sql = "CREATE TABLE $second_table_name (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  domain varchar(128) NOT NULL,
  last_check date NOT NULL,
  UNIQUE KEY id (id)
    );";
    
   $settings_sql = "CREATE TABLE $settings_table_name (
      id int(11) unsigned NOT NULL AUTO_INCREMENT,
       bmre_key varchar(256) NOT NULL,
       bmre_value varchar(256) NOT NULL,
       UNIQUE KEY id (id)
      );";

   $set_time_limit_default_value_sql = "INSERT INTO $settings_table_name (
    bmre_key, bmre_value   
   ) VALUES (
    'set_time_limit', '60'
   );";

   require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   dbDelta($sql);
   dbDelta($second_sql);
   dbDelta($settings_sql);
   $wpdb->query($set_time_limit_default_value_sql); 
   add_option("bmre_domain_monitor_db_version", $bmre_domain_monitor_db_version);
}

register_activation_hook(__FILE__,'bmre_domain_monitor_install');

function bmre_domain_monitor()
{

}

add_action( 'admin_menu', 'bmre_domain_monitor_menu' );

function bmre_domain_monitor_menu() {
	$plugin_page = add_options_page( 	'BMRE Domain Monitor', 
										'BMRE Domain Monitor', 
										'manage_options', 
										'bmre_domain_monitor', 
										'bmre_domain_monitor_options' );
	add_action( 'admin_footer-'. $plugin_page, 'bmre_domain_monitor_admin_footer' );
}

function _make_safe($input=null)
{
	$replacement_array = array("*", "'", "\\", "\"", "{", "}", "(", ")", "[", "]", "=", "|", ":", "/", "https", "http", "www.");
	return str_replace($replacement_array, '', $input);
}

function bmre_domain_monitor_check()
{
	global $wpdb, $whois;
	$results = $wpdb->get_results("select * from " . $wpdb->prefix . "bmre_domain_monitor where last_check != '".date("Y-m-d")."'");
    $set_time_limit = $wpdb->get_results("select bmre_value from " . $wpdb->prefix . "bmre_domain_settings where bmre_key = 'set_time_limit' limit 1");
	if ( $results != 0 && $results != false && $results != null )
	{
			$domain_array = array();
			foreach ( $results as $item )
			{
				$domain_array[$item->id] = $item->domain;
			}
			foreach ( $domain_array as $domain_item_key => $domain_item_value )
			{
			   $result = "";
			   $result = $whois->Lookup($domain_item_value);
			   if ( array_key_exists('expires', $result['regrinfo']['domain']) )
			   {
			       $expires = $result['regrinfo']['domain']['expires'];
			   }else{
			   		foreach ( $result['rawdata'] as $rawkey => $raw_value )
			   		{
			   			if ( preg_match('/expires/i', $raw_value) )
			   			{
			   				$expires = str_replace('expires: ', '', $raw_value);
			   			}
			   		}
			   		if ( !isset($expires) )
			   		{
				       if ($item->expires > 0) $expires = $item->expires;	
				       else $expires = "Unknown";
			   		}
			   }
			   $set_time_limit = date("Y-m-d", strtotime( '+'.$set_time_limit.' days' ) );
			   if ( strtotime($expires) < strtotime($set_time_limit) )
			   {
			   		$notified_check = $wpdb->get_results(
			   				"select notified from " . $wpdb->prefix . "bmre_domain_monitor where id = ".$domain_item_key." limit 1"
			   			);
			   		if ( $notified_check[0]->notified < 2 )
			   		{
			   			wp_mail( 	get_bloginfo('admin_email'), 
					   				"Domain about to expire", 
					   				"The domain " . $domain_item_value . " is about to expire. The expiration-date is " . $expires . "." 
					   			);
			   			$wpdb->update($wpdb->prefix . "bmre_domain_monitor", array("notified"=>$notified_check[0]->notified + 1), array("id"=>$domain_item_key));
			   		}
		   		}else{
			   		$notified_check = $wpdb->get_results(
			   				"select notified from " . $wpdb->prefix . "bmre_domain_monitor where id = ".$domain_item_key." limit 1"
			   			);
			   		if ( $notified_check[0]->notified != 0 )
			   		{
			   			wp_mail( 	get_bloginfo('admin_email'), 
					   				"Domain about to expire", 
					   				"The domain " . $domain_item_value . " is about to expire. The expiration-date is " . $expires . "." 
					   			);
			   			$wpdb->update($wpdb->prefix . "bmre_domain_monitor", array("notified"=>0), array("id"=>$domain_item_key));
			   		}
			   }
				$wpdb->update($wpdb->prefix . "bmre_domain_monitor", array("expires"=>$expires, "last_check"=>date("Y-m-d")), array("id"=>$domain_item_key));
			}
	}
}
add_action( 'admin_head', 'bmre_domain_monitor_check' );

function bmre_domain_monitor_find_similar_domains($domainarray=null)
{
	if (	!is_array($domainarray) ||
			count($domainarray) == 0 ||
			$domainarray == null ||
			$domainarray == false )
	{
		return false;
	}
	global $wpdb, $whois, $plugin_path;
	$returnation_array = array();
	$domain_endings_to_check = array('se', 'com', 'org', 'net', 'info', 'de', 'pl', 'dk', 'co.uk', 'nu');
	foreach ( $domainarray as $array_item )
	{
		$exploded_domain = explode('.', $array_item->domain);
		$domain_endings_to_check[] = ltrim(str_replace($exploded_domain[0], '', $array_item->domain), '.');
		$domain_endings_to_check = array_unique($domain_endings_to_check);
		$set_time_limit = date("Y-m-d", strtotime( '-20 days' ) );
		foreach ( $domain_endings_to_check as $endint_check_item )
		{
	   		$domain_check = $wpdb->get_results(
	   				"select * from " . $wpdb->prefix . "bmre_domain_monitor_domain_checker 
	   				where domain = '".$exploded_domain[0] . "." . $endint_check_item."' limit 1"
	   			);
	   		if ( strtotime($domain_check[0]->last_check) < strtotime($set_time_limit) )
	   		{
				$domaininfo = $whois->Lookup($exploded_domain[0] . "." . $endint_check_item);
				foreach ( $domaininfo['rawdata'] as $rawrow_key => $rawrow_value )
				{
					if (	preg_match("/No match/i", $rawrow_value) ||
							preg_match("/not found/i", $rawrow_value) ||
							preg_match("/No information available/i", $rawrow_value) ||
							preg_match("/Status: free/i", $rawrow_value) ||
							preg_match("/No entries found/i", $rawrow_value) ||
							preg_match("/Status:	AVAILABLE/i", $rawrow_value) )
					{
						if (	$domain_check != false &&
								$domain_check != 0 &&
								$domain_check != null &&
								strtotime($domain_check[0]->last_check) < strtotime($set_time_limit) )
						{
							$wpdb->update(
								$wpdb->prefix . "bmre_domain_monitor_domain_checker", 
								array("last_check"=>date("Y-m-d")), 
								array("id"=>$domain_check[0]->id));
						}elseif (	$domain_check == false ||
									$domain_check == 0 ||
									$domain_check == null )
							{
							$wpdb->insert(	
								$wpdb->prefix . "bmre_domain_monitor_domain_checker", 
								array(	"domain"=>$exploded_domain[0] . "." . $endint_check_item, 
										"last_check"=>date("Y-m-d"))
							);
						}
					}
				}
	   		}
		}
	}
	return $returnation_array;
}

function bmre_domain_monitor_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo('<div style="float:right;padding-left: 60px;"><a href="http://ras-pi.co" target="_blank">
			<img src="http://dwall.in/wp-content/uploads/2012/06/dwall-reg.png" border="0" alt="ras-pi.co" />
		</a></div>');
	echo '<div class="wrap">';
		echo '<p>Please send any found bugs to bugs@dwall.in</p>';
	echo '</div>';
	echo '<div class="wrap">';
	echo('<h2>BMRE Domain Monitor</h2>');
    global $wpdb, $plugin_path, $host;
    $set_time_limit = $wpdb->get_results('select bmre_value from ' . $wpdb->prefix . 'bmre_domain_monitor_settings where bmre_key = "set_time_limit" limit 1');
    if ( $set_time_limit[0]->bmre_value == false || $set_time_limit[0]->bmre_value == 0 || $set_time_limit[0]->bmre_value == null )
    {
        echo('Something went wrong in the BMRE Domain Monitor installation and the set_time_limit wasn\'t set properly. Please re-install it.');exit;
    }
	echo('<p>This plugin will check all added domains every day and notify the sites admin-mail when a domain expires within '.$set_time_limit[0]->bmre_value.' days or less. It will notify two days in a row to be sure you get it.</p>');
	echo '<form id="update_set_time_limit" name="update_set_time_limit" action="" method="post">';
	echo '<label for="set_time_limit">Set time limit</label> ';
	echo '<input name="set_time_limit" id="set_time_limit" type="text" value="'.$set_time_limit[0]->bmre_value.'" />';
	echo '<input name="set_time_limit_submit" id="set_time_limit_submit" type="submit" value="Update!" />';
	echo '</form>';
	echo('<p>If you dont see a table below that is simply cause you haven\'t added a domain yet.</p>');
	$result = $wpdb->get_results("select * from " . $wpdb->prefix . "bmre_domain_monitor");
	echo '<form id="add_domain" name="add_domain" action="" method="post">';
	echo '<label for="domain_name">Domain Name</label> ';
	echo '<input name="domain_name" id="domain_name" type="text" />';
	echo '<input name="submit" id="submit" type="submit" value="Add!" />';
	echo '</form>';
	if ( $result != 0 && $result != false && $result != null )
	{
		echo('<table style="width:100%;border:1px solid #ebebeb;">');
			echo('<thead>');
			echo('<tr style="text-align: left;">
				<th>Domain</th>
				<th>Expires</th>
				<th>Last Checked</th>
				<th>Action</th>
			</tr>');
			echo('</thead>');
			echo('<tbody>');
				foreach ( $result as $item )
				{
					$cssextra = "background:#d9fcc9;";
					$extrainfo = "";
				   	$set_time_limit = date("Y-m-d", strtotime( '+'.$set_time_limit[0]->bmre_value.' days' ) );
   					if ( strtotime($item->expires) < strtotime($set_time_limit) )
   					{
   						$cssextra = "background:#ffd1d4;";
   						$extrainfo = "<strong>(Expires in ".$set_time_limit[0]->bmre_value." or less days)</strong>";
   					}
   					if ( $item->expires == "Unknown" )
   					{
   						$cssextra = "background:#ffd1d4;";
   						$extrainfo = "<strong>(Cannot monitor this domain-type)</strong>";
   					}
   					if ( $item->expires == "0000-00-00" ) 
   					{
   						$cssextra = "background:#ffd1d4;";
   						$extrainfo = "<strong>(Cannot decide expiration-date)</strong>";
   					}					
					echo('<tr style="'.$cssextra.'">
							<td style="width:50%;">'.$item->domain.' '.$extrainfo.'</td>
							<td>'.$item->expires.'</td>
							<td>'.$item->last_check.'</td>
							<td>
								<a style="'.$cssextra.'" href="options-general.php?page=bmre_domain_monitor&remove_domain='.$item->id.'&domain='.$item->domain.'">
									Remove
								</a>
							</td>
						</tr>');
				}		
			echo('</tbody>');
		echo('</table>');
	}
	echo '</div>';
	echo '<div class="wrap">';
	echo '<form id="multiple_import" name="multiple_import" action="" method="post">';
	echo '<h3>Import Multiple Domains</h3>';
	echo '<textarea name="multiple_domains" id="multiple_domains" style="width: 450px;height: 100px;">Format: domain1, domain2, domain3, domain4</textarea><br />';
	echo '<input name="multiple_submit" id="multiple_submit" type="submit" value="Import!" />';
	echo '</form>';
	echo '</div>';
	echo '<div class="wrap">';
		echo '<h3>Domain suggestions?</h3>';
		echo '<p>This will search, every 20 days, for domains similar to the ones you\'ve added above. The search will take a while (depending on how many domains you\'ve added). Please dont abort the loading of the website since it will result in non-complete results.</p>';
		echo '<p>Checking for similar domains of 12 added domains took me 72.4102671146 seconds. This is however only the first time you activate it and then once every 20 days.</p>';
		$similar_checkfiles = glob($plugin_path . "*.".$host.".showsimilar");
		if ( !is_array($similar_checkfiles) || count($similar_checkfiles) == 0 || $similar_checkfiles == 0 )
		{
			echo('<a href="options-general.php?page=bmre_domain_monitor&activate_similar_domain_names=1">I want to see suggestions on similar domains</a>');
		}else{
			echo('<a href="options-general.php?page=bmre_domain_monitor&activate_similar_domain_names=0">I DO NOT want to see suggestions on similar domains</a>');
			$similar_result = $wpdb->get_results("select * from " . $wpdb->prefix . "bmre_domain_monitor_domain_checker");
			if ( $similar_result != 0 && $similar_result != false && $similar_result != null )
			{
				echo('<h3>Available domains that are similar to yours</h3>');
				echo('<table style="width:100%;border:1px solid #ebebeb;">');
					echo('<thead>');
					echo('<tr style="text-align: left;">
						<th>Domain</th>
						<th>Last Checked</th>
					</tr>');
					echo('</thead>');
					echo('<tbody>');
						foreach ( $similar_result as $similar_item )
						{			
							echo('<tr>
									<td style="width:50%;">'.$similar_item->domain.'</td>
									<td>'.$similar_item->last_check.'</td>
								</tr>');
						}		
					echo('</tbody>');
				echo('</table>');
			}
		}
	echo '</div>';
}



function bmre_domain_monitor_admin_footer(){
    global $wpdb, $plugin_path, $host;
    if ( isset($_POST['set_time_limit_submit']) && $_POST['set_time_limit'] != null && $_POST['set_time_limit'] != '' && is_numeric($_POST['set_time_limit']) )
    {
        $set_time_limit = _make_safe(trim($_POST['set_time_limit']));
        $wpdb->query('UPDATE ' . $wpdb->prefix . 'bmre_domain_monitor_settings SET bmre_value="'.$set_time_limit.'" WHERE bmre_key="set_time_limit" LIMIT 1');
		echo('<meta http-equiv="REFRESH" content="0;url='.get_bloginfo('url').'/wp-admin/options-general.php?page=bmre_domain_monitor">');
    }
	if ( isset($_POST['submit']) && $_POST['domain_name'] != null && $_POST['domain_name'] != "" )
	{
		$domain_name = _make_safe($_POST['domain_name']);
		$check_for_domain_name = $wpdb->get_results("select * from " . $wpdb->prefix . "bmre_domain_monitor where domain = '".$domain_name."'");
		if ( $check_for_domain_name == 0 || $check_for_domain_name == null || $check_for_domain_name == false )
		{
			$wpdb->insert($wpdb->prefix . "bmre_domain_monitor", array("domain"=>$domain_name));
			echo('<meta http-equiv="REFRESH" content="0;url='.get_bloginfo('url').'/wp-admin/options-general.php?page=bmre_domain_monitor">');
		}
	}
	if ( isset($_POST['multiple_submit']) && $_POST['multiple_domains'] != null && $_POST['multiple_domains'] != "Format: domain1, domain2, domain3, domain4" )
	{
		$multiple_domain_names = explode(',', $_POST['multiple_domains']);
		$cleaned_domain_name = "";
		foreach ( $multiple_domain_names as $domain_key => $domain_value )
		{
			$cleaned_domain_name = _make_safe(trim($domain_value, ' '));
			$check_for_domain_name = $wpdb->get_results("select * from " . $wpdb->prefix . "bmre_domain_monitor where domain = '".$cleaned_domain_name."'");
			if ( $check_for_domain_name == 0 || $check_for_domain_name == null || $check_for_domain_name == false )
			{
				$wpdb->insert($wpdb->prefix . "bmre_domain_monitor", array("domain"=>$cleaned_domain_name));
			}
		}
		echo('<meta http-equiv="REFRESH" content="0;url='.get_bloginfo('url').'/wp-admin/options-general.php?page=bmre_domain_monitor">');
	}
	if ( isset($_GET['remove_domain']) && is_numeric($_GET['remove_domain']) )
	{
		$domain_id = _make_safe($_GET['remove_domain']);
		$domain = _make_safe($_GET['domain']);
		if ( !isset($_GET['confirm']) || $_GET['confirm'] != 1 )
		{
			echo('<p>You sure you wanna remove ' . $domain .'</p>');
			echo('<p>
					<a href="options-general.php?page=bmre_domain_monitor&remove_domain='.$domain_id.'&domain='.$domain.'&confirm=1">
						Yes
					</a> - 
					<strong>
						<a href="options-general.php?page=bmre_domain_monitor">
							No
						</a>
					</strong>
				</p>');
			exit;
		}
		$wpdb->query("	DELETE FROM " . $wpdb->prefix . "bmre_domain_monitor 
						WHERE id = ".$domain_id." 
						AND domain = '".$domain."'
						LIMIT 1");
		echo('<meta http-equiv="REFRESH" content="0;url='.get_bloginfo('url').'/wp-admin/options-general.php?page=bmre_domain_monitor">');
	}

	$checkfiles = glob($plugin_path . "*.".$host.".check");
	$showsimilar_files = glob($plugin_path . "*.".$host.".showsimilar");
	$twenty_days_back = date("Y-m-d", strtotime( '-20 days' ) );
	if (	!is_array($checkfiles) ||
			count($checkfiles) == 0 || 
			$checkfiles == 0 ||
			strtotime(str_replace('.'.$host.'.check', '', str_replace($plugin_path, '', $checkfiles[0]))) < strtotime($twenty_days_back) )
	{
		if ( 	is_array($showsimilar_files) &&
				count($showsimilar_files) != 0 &&
				$showsimilar_files != 0 )
		{
			bmre_domain_monitor_find_similar_domains($wpdb->get_results("select * from " . $wpdb->prefix . "bmre_domain_monitor"));
			$new_checkfile = $plugin_path.date("Y-m-d").".".$host.".check";
			$fh = fopen($new_checkfile, 'a+') or die("There was an error, accessing the requested file.");
			fwrite($fh, get_the_content());
			fclose($fh);
		}
		if ( is_array($checkfiles) )
		{
			foreach ( $checkfiles as $file )
			{
				unlink($file);
			}
		}
	}

	if ( isset($_GET['activate_similar_domain_names']) && is_numeric($_GET['activate_similar_domain_names']) && $_GET['activate_similar_domain_names'] == 1 )
	{
		$showsimilar_file = $plugin_path.date("Y-m-d").".".$host.".showsimilar";
		$fh = fopen($showsimilar_file, 'a+') or die("There was an error, accessing the requested file.");
		fwrite($fh, get_the_content());
		fclose($fh);
		echo('<meta http-equiv="REFRESH" content="0;url='.get_bloginfo('url').'/wp-admin/options-general.php?page=bmre_domain_monitor">');
	}elseif ( isset($_GET['activate_similar_domain_names']) && is_numeric($_GET['activate_similar_domain_names']) && $_GET['activate_similar_domain_names'] == 0 )
	{
		$showsimilar_files = glob($plugin_path . "*.".$host.".showsimilar");
		if ( is_array($showsimilar_files) )
		{
			foreach ( $showsimilar_files as $showsimilar_file )
			{
				unlink($showsimilar_file);
			}
		}
		echo('<meta http-equiv="REFRESH" content="0;url='.get_bloginfo('url').'/wp-admin/options-general.php?page=bmre_domain_monitor">');
	}
}
