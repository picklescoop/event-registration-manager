<?php
if(!defined('ABSPATH'))exit;
/**
 * Super Forms Connect: independent local-only configuration and readiness UI.
 * Provider credentials and tokens remain on the WordPress installation.
 */
final class ERM_Connect{
 const OPTION='erm_connect_installation';
 static function init(){}
 static function config(){return (array)get_option(self::OPTION,[]);}
 static function id(){
  $c=self::config();
  if(!empty($c['installation_id']))return $c['installation_id'];
  $c['installation_id']=wp_generate_uuid4();
  update_option(self::OPTION,$c,false);
  return $c['installation_id'];
 }
 static function panel($type='meetings'){

  $items=$type==='payments'?['stripe'=>'Stripe','paypal'=>'PayPal Checkout','square'=>'Square']:['google'=>'Google Calendar / Meet','microsoft'=>'Microsoft 365 / Outlook.com / Teams','zoom'=>'Zoom','discord'=>'Discord','slack'=>'Slack'];
  echo '<section class="erm-connect-panel" style="max-width:980px;background:#fff;border:1px solid #dce1ea;border-radius:10px;padding:20px 24px;margin:20px 0"><h2>Super Forms Connect — '.($type==='payments'?'Payments':'Calendar & Meetings').'</h2>';
  echo '<p><strong>Independent installation:</strong> This WordPress website keeps its own forms, customer data, account settings and payment destinations. Installation ID: <code>'.esc_html(self::id()).'</code>.</p>';
  echo '<p><strong>Local-only architecture:</strong> Each website connects directly to its chosen providers and stores its own integration credentials and tokens in its WordPress environment. No Super Forms-operated central connection server or account database is used. Some providers require one-time OAuth app registration by the site administrator.</p>';
  echo '<table class="widefat striped"><thead><tr><th>Provider</th><th>Connection method</th><th>Status</th></tr></thead><tbody>';
  foreach($items as $key=>$label){
   $method=($key==='discord')?'Bot authorization':(($key==='slack')?'Workspace OAuth':'Account OAuth');
   echo '<tr><td>'.esc_html($label).'</td><td>'.esc_html($method).'</td><td><strong>Local provider setup required</strong></td></tr>';
  }
  echo '</tbody></table>';
  if($type==='payments')echo '<p><strong>Payments remain disabled for automated checkout.</strong> Each merchant must connect its own account. Never consider a registration paid based on a browser redirect or unverified callback. Existing manual payment links continue to work.</p>';
  echo '<p><strong>Privacy:</strong> Provider authorization and API requests should go directly between this WordPress installation and the selected provider. No installation identity, credentials, tokens, registration records or payment data are transmitted to a Super Forms central service.</p>';
  echo '<p>Existing Google and Microsoft OAuth settings can be configured on this website. Other provider integrations require further implementation and testing.</p>';

