<?php
/*
Plugin Name: Pretty Link
Plugin URI: http://blairwilliams.com/pretty-link
Description: Shrink, track and share any URL on the Internet from your WordPress website!
Version: 1.3.34
Author: Blair Williams
Author URI: http://blairwilliams.com
Copyright: 2009, Blair Williams

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

require_once('prli-config.php');
require_once(PRLI_MODELS_PATH . '/models.inc.php');
require_once('prli-api.php'); // load api methods
require_once('prli-xmlrpc.php'); // load xml-rpc api methods


$prli_inc_utils = new PrliUtils();

// Provide Back End Hooks to the Pro version of Pretty Link
if($prli_inc_utils->pro_is_installed())
  require_once(PRLI_PATH.'/pro/pretty-link-pro.php');

function prli_menu()
{
  add_menu_page('Pretty Link', 'Pretty Link', 8, PRLI_PATH.'/prli-links.php','',PRLI_URL.'/images/pretty-link-small.png'); 
  add_submenu_page(PRLI_PATH.'/prli-links.php', 'Pretty Link | Add New Link', 'Add New Link', 8, PRLI_PATH.'/prli-add-link.php');
  add_submenu_page(PRLI_PATH.'/prli-links.php', 'Pretty Link | Groups', 'Groups', 8, PRLI_PATH.'/prli-groups.php');
  add_submenu_page(PRLI_PATH.'/prli-links.php', 'Pretty Link | Hits', 'Hits', 8, PRLI_PATH.'/prli-clicks.php');

  add_options_page('Pretty Link Settings', 'Pretty Link', 8, PRLI_PATH.'/prli-options.php');

  add_action('admin_head-pretty-link/prli-clicks.php', 'prli_reports_admin_header');
  add_action('admin_head-pretty-link/prli-links.php', 'prli_links_admin_header');
  add_action('admin_head-pretty-link/prli-add-link.php', 'prli_links_admin_header');
  add_action('admin_head-pretty-link/prli-groups.php', 'prli_groups_admin_header');
  add_action('admin_head-pretty-link/prli-options.php', 'prli_options_admin_header');
}

add_action('admin_menu', 'prli_menu');

/* Add header to prli-options page */
function prli_options_admin_header()
{
  require_once 'classes/views/prli-options/head.php';
}

/* Add header to prli-clicks page */
function prli_reports_admin_header()
{
  // Don't show this sheesh if we're displaying the vuid or ip grouping
  if(!isset($_GET['ip']) and !isset($_GET['vuid']))
  {
    global $prli_siteurl, $prli_click, $prli_utils;

    $params = $prli_click->get_params_array();
    $first_click = $prli_utils->getFirstClickDate();

    // Adjust for the first click
    if(isset($first_click))
    {
      $min_date = (int)((time()-$first_click)/60/60/24);

      if($min_date < 30)
        $start_timestamp = $prli_utils->get_start_date($params,$min_date);
      else
        $start_timestamp = $prli_utils->get_start_date($params,30);

      $end_timestamp = $prli_utils->get_end_date($params);
    }
    else
    {
      $min_date = 0;
      $start_timestamp = time();
      $end_timestamp = time();
    }

    $link_id = $params['l'];
    $type = $params['type'];
    $group = $params['group'];

    require_once 'classes/views/prli-clicks/head.php';
  }
}

/* Add header to the prli-links page */
function prli_links_admin_header()
{
  global $prli_siteurl;
  require_once 'classes/views/prli-links/head.php';
}

/* Add header to the prli-links page */
function prli_groups_admin_header()
{
  global $prli_siteurl;
  require_once 'classes/views/prli-groups/head.php';
}

/********* ADD REDIRECTS FOR STANDARD MODE ***********/
function prli_redirect()
{
  global $prli_blogurl, $wpdb, $prli_link;
 
  // Resolve WP installs in sub-directories
  preg_match('#^http://.*?(/.*)$#', $prli_blogurl, $subdir);

  $match_str = '#^'.$subdir[1].'/(.*?)([\?/].*?)?$#';
 
  if(preg_match($match_str, $_SERVER['REQUEST_URI'], $match_val))
  {
    // match short slugs (most common)
    prli_link_redirect_from_slug($match_val[1],$match_val[2]);

    // Match nested slugs (pretty link sub-directory nesting)
    $possible_links = $wpdb->get_col("SELECT slug FROM " . $prli_link->table_name() . " WHERE slug like '".$match_val[1]."/%'",0);
    foreach($possible_links as $possible_link)
    {
      // Try to match the full link against the URI
      if( preg_match('#^'.$subdir[1].'/('.$possible_link.')([\?/].*?)?$#', $_SERVER['REQUEST_URI'], $match_val) )
        prli_link_redirect_from_slug($possible_link,$match_val[2]);
    }
  }
}

// For use with the prli_redirect function
function prli_link_redirect_from_slug($slug,$param_str)
{
  global $prli_link, $prli_utils;

  $link = $prli_link->getOneFromSlug($slug);
  
  if(isset($link->slug) and !empty($link->slug))
  {
    $custom_get = $_GET;
  
    if(isset($link->param_forwarding) and $link->param_forwarding == 'custom')
      $custom_get = $prli_utils->decode_custom_param_str($link->param_struct, $param_str);
  
    $prli_utils->track_link($link->slug,$custom_get); 
    exit;
  }
}

add_action('init', 'prli_redirect'); //Redirect

/********* EXPORT PRETTY LINK API VIA XML-RPC ***********/
function prli_export_api($api_methods)
{
  $api_methods['prli.create_pretty_link'] = 'prli_xmlrpc_create_pretty_link';
  $api_methods['prli.get_all_groups']     = 'prli_xmlrpc_get_all_groups';
  $api_methods['prli.get_all_links']      = 'prli_xmlrpc_get_all_links';
  $api_methods['prli.get_link']           = 'prli_xmlrpc_get_link';
  $api_methods['prli.api_version']        = 'prli_xmlrpc_api_version';

  return $api_methods;
}

add_filter('xmlrpc_methods', 'prli_export_api');

/********* INSTALL PLUGIN ***********/
$prli_db_version = "0.2.9";

function prli_install() {
  global $wpdb, $prli_db_version;

  require_once(dirname(__FILE__) . "/classes/models/PrliUtils.php");
  $prli_utils = new PrliUtils();

  $groups_table = $wpdb->prefix . "prli_groups";
  $clicks_table = $wpdb->prefix . "prli_clicks";
  $pretty_links_table = $wpdb->prefix . "prli_links";

  $prli_db_version = 'prli_db_version';
  $prli_current_db_version = get_option( $prli_db_version );

  if( empty($prli_current_db_version) or ($prli_current_db_version != $prli_new_db_version))
  {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    /* Create/Upgrade Clicks (Hits) Table */
    $sql = "CREATE TABLE " . $clicks_table . " (
              id int(11) NOT NULL auto_increment,
              ip varchar(255) default NULL,
              browser varchar(255) default NULL,
              btype varchar(255) default NULL,
              bversion varchar(255) default NULL,
              os varchar(255) default NULL,
              referer varchar(255) default NULL,
              host varchar(255) default NULL,
              uri varchar(255) default NULL,
              first_click tinyint default 0,
              created_at datetime NOT NULL,
              link_id int(11) default NULL,
              vuid varchar(25) default NULL,
              PRIMARY KEY  (id),
              KEY link_id (link_id),
              KEY vuid (vuid)".
              // We won't worry about this constraint for now.
              //CONSTRAINT ".$clicks_table."_ibfk_1 FOREIGN KEY (link_id) REFERENCES $pretty_links_table (id)
            ");";
    
    dbDelta($sql);
    
    /* Create/Upgrade Pretty Links Table */
    $sql = "CREATE TABLE " . $pretty_links_table . " (
              id int(11) NOT NULL auto_increment,
              name varchar(255) default NULL,
              description text default NULL,
              url varchar(255) default NULL,
              slug varchar(255) default NULL,
              track_as_img tinyint(1) default 0,
              nofollow tinyint(1) default 0,
              track_me tinyint(1) default 1,
              use_prettybar tinyint(1) default 0,
              use_ultra_cloak tinyint(1) default 0,
              param_forwarding varchar(255) default NULL,
              param_struct varchar(255) default NULL,
              redirect_type varchar(255) default '307',
              gorder int(11) default 0,
              created_at datetime NOT NULL,
              group_id int(11) default NULL,
              PRIMARY KEY  (id),
              KEY group_id (group_id),
              KEY slug (slug)
            );";
    
    dbDelta($sql);

    /* Create/Upgrade Groups Table */
    $sql = "CREATE TABLE " . $groups_table . " (
              id int(11) NOT NULL auto_increment,
              name varchar(255) default NULL,
              description text default NULL,
              created_at datetime NOT NULL,
              PRIMARY KEY  (id)
            );";
    
    dbDelta($sql);

    // Pretty Link Pro Tables
    if($prli_utils->pro_is_installed())
    {
      // Pretty Link Pro Tables
      $tweets_table = $wpdb->prefix . "prli_tweets";
      $keywords_table = $wpdb->prefix . "prli_keywords";

      /* Create/Upgrade Tweets Table */
      $sql = "CREATE TABLE {$tweets_table} (
                id int(11) NOT NULL auto_increment,
                twid varchar(255) NOT NULL, 
                tw_text varchar(255) default NULL,
                tw_to_user_id varchar(255) default NULL,
                tw_from_user varchar(255) default NULL,
                tw_from_user_id varchar(255) NOT NULL,
                tw_iso_language_code varchar(255) default NULL,
                tw_source varchar(255) default NULL,
                tw_profile_image_url varchar(255) default NULL,
                tw_created_at varchar(255) NOT NULL,
                created_at datetime NOT NULL,
                link_id int(11) default NULL,
                PRIMARY KEY  (id),
                KEY link_id (link_id),
                KEY twid (twid)
              );";
    
      dbDelta($sql);

      /* Create/Upgrade Keywords Table */
      $sql = "CREATE TABLE {$keywords_table} (
                id int(11) NOT NULL auto_increment,
                text varchar(255) default NULL,
                match_case tinyint default 0,
                link_id int(11) default NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY link_id (link_id)
              );";
    
      dbDelta($sql);
    }
  }

  $browsecap_updated = get_option('prli_browsecap_updated');

  // This migration should only run once
  if(empty($browsecap_updated) or !$browsecap_updated)
  {
    /********** UPDATE BROWSER CAPABILITIES **************/
    // Update all click data to include btype (browser type), bversion (browser version), & os)
    $click_query = "SELECT * FROM " . $wpdb->prefix . "prli_clicks WHERE browser IS NOT NULL AND os IS NULL AND btype IS NULL AND bversion IS NULL";
    $results = $wpdb->get_results($click_query);
    foreach($results as $click)
    {
      $click_browser = $prli_utils->php_get_browser($click->browser);
      $update = "UPDATE " . $wpdb->prefix . "prli_clicks SET btype='".$click_browser['browser']."',bversion='".$click_browser['version']."',os='".$click_browser['platform']."' WHERE id=".$click->id;
      $wpdb->query( $update );
    }
    
    /********** UPDATE HOST INFO **************/
    $click_query = "SELECT * FROM " . $wpdb->prefix . "prli_clicks WHERE host IS NULL";
    $results = $wpdb->get_results($click_query);
    
    foreach($results as $click)
    {
      $click_host = gethostbyaddr($click->ip);
      $update = "UPDATE " . $wpdb->prefix . "prli_clicks SET host='$click_host' WHERE id=".$click->id;
      $wpdb->query( $update );
    }

    add_option('prli_browsecap_updated',true);
  }

  // UPDATE LINK NAMES
  $link_names_updated = get_option('prli_link_names_updated');
  if(empty($link_names_updated) or !$link_names_updated)
  {
    // Update all links -- copy the slug into the name field
    $link_query = "SELECT * FROM " . $wpdb->prefix . "prli_links";
    $results = $wpdb->get_results($link_query);
    foreach($results as $link)
    {
      $link_name = (empty($link->name)?$link->slug:$link->name);
      $update = "UPDATE " . $wpdb->prefix . "prli_links SET name='".$link_name."' WHERE id=".$link->id;
      $wpdb->query( $update );
    }

    add_option('prli_link_names_updated',true);
  }

  // MIGRATE PARAMETER FORWARDING introduced in 1.3.1
  $param_forwarding_updated = get_option('prli_param_forwarding_updated');
  if(empty($param_forwarding_updated) or !$param_forwarding_updated)
  {
    // Update all links -- copy the slug into the name field
    $link_query = "SELECT * FROM " . $wpdb->prefix . "prli_links";
    $results = $wpdb->get_results($link_query);
    foreach($results as $link)
    {
      if(!empty($link->forward_params) and $link->forward_params == 1)
        $update = "UPDATE " . $wpdb->prefix . "prli_links SET param_forwarding='on' WHERE id=".$link->id;
      else
        $update = "UPDATE " . $wpdb->prefix . "prli_links SET param_forwarding='off' WHERE id=".$link->id;

      $wpdb->query( $update );
    }

    add_option('prli_param_forwarding_updated',true);
  }

  // Flush the apache rules if rewrite is on
  if( get_option( 'prli_rewrite_mode' ) == 'on' )
  {
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
    update_option('prli_rewrite_mode','off');
  }

  // Set PrettyBar Defaults
  $prettybar_show_title  = 'prli_prettybar_show_title';
  $prettybar_show_description  = 'prli_prettybar_show_description';
  $prettybar_show_share_links  = 'prli_prettybar_show_share_links';
  $prettybar_show_target_url_link  = 'prli_prettybar_show_target_url_link';

  if(!get_option($prettybar_show_title))
    add_option('prli_prettybar_show_title',1);
  if(!get_option($prettybar_show_description))
    add_option('prli_prettybar_show_description',1);
  if(!get_option($prettybar_show_share_links))
    add_option('prli_prettybar_show_share_links',1);
  if(!get_option($prettybar_show_target_url_link))
    add_option('prli_prettybar_show_target_url_link',1);

  // Set Link Defaults
  $link_show_prettybar = 'prli_link_show_prettybar';
  $link_ultra_cloak = 'prli_link_ultra_cloak';
  $link_track_me = 'prli_link_track_me';
  $link_track_as_pixel = 'prli_link_track_as_pixel';
  $link_nofollow = 'prli_link_nofollow';
  $link_redirect_type = 'prli_link_redirect_type';

  if(!get_option($link_show_prettybar))
    add_option('prli_link_show_prettybar',0);
  if(!get_option($link_ultra_cloak))
    add_option('prli_link_ultra_cloak',0);
  if(!get_option($link_track_me))
    add_option('prli_link_track_me',1);
  if(!get_option($link_track_as_pixel))
    add_option('prli_link_track_as_pixel',0);
  if(!get_option($link_nofollow))
    add_option('prli_link_nofollow',0);
  if(!get_option($link_redirect_type))
    update_option('prli_link_redirect_type','307');
  if(!get_option('prli_prettybar_title_limit'))
    update_option('prli_prettybar_title_limit', '30');
  if(!get_option('prli_prettybar_desc_limit'))
    update_option('prli_prettybar_desc_limit', '40');
  if(!get_option('prli_prettybar_link_limit'))
    update_option('prli_prettybar_link_limit', '40');

  if(empty($prli_current_db_version) or !$prli_current_db_version)
    add_option($prli_db_version,$prli_new_db_version);
  else
    update_option($prli_db_version,$prli_new_db_version);

  // Pro Options
  if($prli_utils->pro_is_installed())
  {
    if(!get_option('prli_posts_auto'))
      add_option('prli_posts_auto', '0');
    if(!get_option('prli_pages_auto'))
      add_option('prli_pages_auto', '0');
    if(!get_option('prli_posts_group'))
      add_option('prli_posts_group', '');
    if(!get_option('prli_pages_group'))
      add_option('prli_pages_group', '');

    if(!get_option('prli_twitter_handle'))
      add_option('prli_twitter_handle', 'prettylink');
    if(!get_option('prli_twitter_password'))
      add_option('prli_twitter_password', '');
    if(!get_option('prli_twitter_auto_post'))
      add_option('prli_twitter_auto_post', '0');
    if(!get_option('prli_twitter_posts_button'))
      add_option('prli_twitter_posts_button', '0');
    if(!get_option('prli_twitter_pages_button'))
      add_option('prli_twitter_pages_button', '0');
  }
}

// Ensure this gets called on first install
register_activation_hook(__FILE__,'prli_install');

?>
