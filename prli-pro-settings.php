<?php
require_once 'prli-config.php';
require_once(PRLI_MODELS_PATH . '/models.inc.php');

$errors = array();

// variables for the field and option names 
$prlipro_username = 'prlipro_username';
$prlipro_password = 'prlipro_password';
$hidden_field_name = 'prli_update_options';

$prli_domain = "pretty-link";

// Read in existing option value from database
$prlipro_username_val = get_option( $prlipro_username );
$prlipro_password_val = get_option( $prlipro_password );

if($_GET['action'] == 'force-pro-reinstall')
{
  $prli_utils->download_and_install_pro($prlipro_username_val, $prlipro_password_val, true);
  ?>
  
  <div class="updated"><p><strong><?php _e('Pretty Link Pro Successfully Reinstalled.', $prli_domain ); ?></strong></p></div>
  <?php
}
if($_GET['action'] == 'pro-uninstall')
{
  $prli_utils->uninstall_pro();
  ?>
  
  <div class="updated"><p><strong><?php _e('Pretty Link Pro Successfully Uninstalled.', $prli_domain ); ?></strong></p></div>
  <?php
}
else
{
  // See if the user has posted us some information
  // If they did, this hidden field will be set to 'Y'
  if( $_POST[ $hidden_field_name ] == 'Y' ) 
  {
    // Validate This
    // This is where the remote username / password will be validated
    //if( !empty($_POST[$prettybar_image_url]) and !preg_match('/^http.?:\/\/.*\..*$/', $_POST[$prettybar_image_url] ) )
    //  $errors[] = "Logo Image URL must be a correctly formatted URL";
  
    // Read their posted value
    $prlipro_username_val = stripslashes($_POST[ $prlipro_username ]);
    $prlipro_password_val = stripslashes($_POST[ $prlipro_password ]);

    $user_type = $prli_utils->get_pro_user_type($prlipro_username_val, $prlipro_password_val);
    if(empty($user_type))
      $errors[] = "Your user account couldn't be validated...";
    else
    {
      // Test to make sure this sheesh is writeable
      $handle = fopen(PRLI_PATH . '/098j1248iomv.txt', 'w');
      if(!$handle)
      {
        // still update credentials
        update_option( $prlipro_username, $prlipro_username_val );
        update_option( $prlipro_password, $prlipro_password_val );

        $errors[] = "Your account was validated but " . PRLI_PATH . " is not writeable<br/>Talk to your webhost about increasing your write permissions or install using the <a href=\"http://prettylinkpro.com/user-manual/pretty-link-pro-manual-installation/\">Manual Install</a> Process";
      }
      else
      {
        fclose($handle);
        unlink(PRLI_PATH . '/098j1248iomv.txt');
      }
    }


    if( count($errors) > 0 )
    {
      require(PRLI_VIEWS_PATH.'/shared/errors.php');
    }
    else
    {
      // TODO: Download & Install Pretty Link Pro if Account is valid and software isn't currently installed
      // Save the posted value in the database
      update_option( $prlipro_username, $prlipro_username_val );
      update_option( $prlipro_password, $prlipro_password_val );
  
      // Put an options updated message on the screen
      $message = $prli_utils->download_and_install_pro($prlipro_username_val, $prlipro_password_val);

      $message = (($message == 'SUCCESS')?'Pretty Link Pro has been installed click here to get started: <a href="?page=pretty-link/pro/prlipro-options.php">Pretty Link Pro Options</a>':$message);
  ?>
  
  <div class="updated"><p><strong><?php _e($message, $prli_domain ); ?></strong></p></div>
  <?php
    }
  }
}

require_once 'classes/views/prli-options/pro-settings.php';
