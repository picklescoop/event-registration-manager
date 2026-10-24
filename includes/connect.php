<?php
if(!defined('ABSPATH'))exit;
/**
 * Super Forms Connect: per-installation configuration and safe readiness UI.
 * No OAuth exchange or payment collection is enabled without a deployed broker.
 */
final class ERM_Connect{
 const OPTION='erm_connect_installation';
 static function init(){add_action('admin_post_erm_connect_settings',[__CLASS__,'save']);}
 static function config(){return (array)get_option(self::OPTION,[]);}
 static function id(){
  $c=self::config();
  if(!empty($c['installation_id']))return $c['installation_id'];
  $c['installation_id']=wp_generate_uuid4();
  update_option(self::OPTION,$c,false);
  return $c['installation_id'];
 }
 static function save(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);
  check_admin_referer('erm_connect_settings');
  $c=self::config();
  $c['external_service_opt_in']=!empty($_POST['external_service_opt_in']);
  update_option(self::OPTION,$c,false);
  wp_safe_redirect(add_query_arg('connect_saved','1',admin_url('edit.php?post_type=erm_event&page=erm-settings-hub')));exit;
 }
 static function panel($type='meetings'){
  $c=self::config();$opted=!empty($c['external_service_opt_in']);
  $items=$type==='payments'?['stripe'=>'Stripe','paypal'=>'PayPal Checkout','square'=>'Square']:['google'=>'Google Calendar / Meet','microsoft'=>'Microsoft 365 / Outlook.com / Teams','zoom'=>'Zoom','discord'=>'Discord','slack'=>'Slack'];
  echo '<section class="erm-connect-panel" style="max-width:980px;background:#fff;border:1px solid #dce1ea;border-radius:10px;padding:20px 24px;margin:20px 0"><h2>Super Forms Connect — '.($type==='payments'?'Payments':'Calendar & Meetings').'</h2>';
  echo '<p><strong>Independent installation:</strong> This WordPress website keeps its own forms, customer data, account settings and payment destinations. Installation ID: <code>'.esc_html(self::id()).'</code>.</p>';
  echo '<p><strong>Connection framework:</strong> Provider sign-in through a shared authorization broker is planned but <strong>not active</strong>. A hosted broker, registered provider applications, verified callbacks, and provider-specific testing are required before one-click connections can be offered.</p>';
  echo '<table class="widefat striped"><thead><tr><th>Provider</th><th>Connection method</th><th>Status</th></tr></thead><tbody>';
  foreach($items as $key=>$label){
   $method=($key==='discord')?'Bot authorization':(($key==='slack')?'Workspace OAuth':'Account OAuth');
   echo '<tr><td>'.esc_html($label).'</td><td>'.esc_html($method).'</td><td><strong>Broker setup required</strong></td></tr>';
  }
  echo '</tbody></table>';
  if($type==='payments')echo '<p><strong>Payments remain disabled for automated checkout.</strong> Each merchant must connect its own account. Never consider a registration paid based on a browser redirect or unverified callback. Existing manual payment links continue to work.</p>';
  echo '<p><strong>Privacy:</strong> Connecting an external service would transmit the website URL, installation identifier, and authorization metadata to the broker and selected provider. No registration or payment data is transmitted by this framework. Connections will be optional and must be disclosed before activation.</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_connect_settings">';
  wp_nonce_field('erm_connect_settings');
  echo '<label><input type="checkbox" name="external_service_opt_in" value="1" '.checked($opted,true,false).'> Allow this installation to use an external connection service when one is configured (does not connect accounts)</label>';
  echo '<p><button class="button">Save connection preference</button></p></form></section>';
 }
}
