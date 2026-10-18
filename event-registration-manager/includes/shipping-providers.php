<?php
if(!defined('ABSPATH'))exit;
/** Shipping integrations. Shippo API is operational; direct carrier integrations are not enabled until carrier-specific adapters are installed. */
final class ERM_Shipping_Providers {
 const OPTION='erm_shipping_providers';
 static function init(){
  add_action('admin_post_erm_shipping_save',[__CLASS__,'save']);
  add_action('admin_post_erm_shipping_quote',[__CLASS__,'quote_order']);
  add_action('admin_post_erm_shipping_label',[__CLASS__,'purchase_label']);
 }
 static function settings(){return wp_parse_args((array)get_option(self::OPTION,[]),[
  'shippo_token'=>'','shippo_enabled'=>0,'shippo_test'=>1,
  'sender_name'=>'','sender_company'=>'','sender_street'=>'','sender_city'=>'','sender_region'=>'','sender_zip'=>'','sender_country'=>'US','sender_phone'=>'','sender_email'=>'',
  'parcel_length'=>8,'parcel_width'=>6,'parcel_height'=>4,'parcel_weight'=>16,
  'usps_client_id'=>'','usps_client_secret'=>'','ups_client_id'=>'','ups_client_secret'=>'','ups_account'=>'','fedex_client_id'=>'','fedex_client_secret'=>'','fedex_account'=>''
 ]);}
 static function settings_page(){
  if(!current_user_can('manage_options'))wp_die('Access denied',403);
  $s=self::settings();echo '<div class="wrap"><h1>Shipping Providers</h1><p>Connect Shippo for live rates and label purchasing. Direct USPS, UPS and FedEx credentials may be stored separately, but direct carrier rating and label APIs are not active in this version. Never enter credentials in public form fields.</p>';
  if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Shipping settings saved.</p></div>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_shipping_save">';wp_nonce_field('erm_shipping_save');
  echo '<h2>Shippo integration</h2><p><label><input type="checkbox" name="ship[shippo_enabled]" value="1" '.checked(!empty($s['shippo_enabled']),true,false).'> Enable Shippo</label> &nbsp; <label><input type="checkbox" name="ship[shippo_test]" value="1" '.checked(!empty($s['shippo_test']),true,false).'> Test mode</label></p>';
  echo '<p><label>Shippo API token (leave blank to keep saved token)<br><input type="password" name="ship[shippo_token]" class="regular-text" autocomplete="new-password" placeholder="'.(!empty($s['shippo_token'])?'Token saved — leave blank to retain':'shippo_test_... or shippo_live_...').'"></label></p>';
  echo '<h2>Sender / return address</h2>';
  $fields=['sender_name'=>'Contact name','sender_company'=>'Company','sender_street'=>'Street address','sender_city'=>'City','sender_region'=>'State / province','sender_zip'=>'Postal code','sender_country'=>'Country code (US, CA, etc.)','sender_phone'=>'Phone','sender_email'=>'Email','parcel_length'=>'Default parcel length (inches)','parcel_width'=>'Default parcel width (inches)','parcel_height'=>'Default parcel height (inches)','parcel_weight'=>'Default parcel weight (ounces)'];
  foreach($fields as $key=>$label){$number=strpos($key,'parcel_')===0;echo '<p><label>'.esc_html($label).'<br><input name="ship['.esc_attr($key).']" type="'.($number?'number':'text').'" '.($number?'step="0.01" min="0.01" max="9999"':'maxlength="180"').' value="'.esc_attr($s[$key]).'" class="regular-text"></label></p>';}
  echo '<h2>Individual carrier account credentials</h2><p>These are saved for future direct integrations. <strong>They do not enable live direct-carrier quotes or labels in this release.</strong> For now, connect your existing carrier accounts inside Shippo to use their negotiated rates through Shippo.</p>';
  foreach(['usps_client_id'=>'USPS client ID','usps_client_secret'=>'USPS client secret','ups_client_id'=>'UPS client ID','ups_client_secret'=>'UPS client secret','ups_account'=>'UPS account number','fedex_client_id'=>'FedEx client ID','fedex_client_secret'=>'FedEx client secret','fedex_account'=>'FedEx account number'] as $key=>$label){$secret=strpos($key,'secret')!==false;echo '<p><label>'.esc_html($label).'<br><input type="'.($secret?'password':'text').'" autocomplete="off" name="ship['.esc_attr($key).']" class="regular-text" value="'.($secret?'':esc_attr($s[$key])).'" placeholder="'.($secret&&!empty($s[$key])?'Saved — leave blank to retain':'').'"></label></p>';}
  echo '<p><button class="button button-primary">Save Shipping Providers</button></p></form></div>';
 }
 static function save(){
  if(!current_user_can('manage_options'))wp_die('Access denied',403);check_admin_referer('erm_shipping_save');
  $raw=isset($_POST['ship'])&&is_array($_POST['ship'])?wp_unslash($_POST['ship']):[];$s=self::settings();
  foreach(['shippo_enabled','shippo_test'] as $k)$s[$k]=!empty($raw[$k])?1:0;
  foreach(['shippo_token','usps_client_secret','ups_client_secret','fedex_client_secret'] as $k)if(!empty($raw[$k]))$s[$k]=substr(sanitize_text_field($raw[$k]),0,255);
  foreach(['sender_name','sender_company','sender_street','sender_city','sender_region','sender_zip','sender_phone','sender_email','usps_client_id','ups_client_id','ups_account','fedex_client_id','fedex_account'] as $k)$s[$k]=substr(sanitize_text_field($raw[$k]??''),0,180);
  $s['sender_country']=strtoupper(substr(preg_replace('/[^A-Za-z]/','',(string)($raw['sender_country']??'US')),0,2));
  foreach(['parcel_length','parcel_width','parcel_height','parcel_weight'] as $k)$s[$k]=min(9999,max(0.01,(float)($raw[$k]??1)));
  // Credentials are not echoed to the page or embedded into the public checkout.
  update_option(self::OPTION,$s,false);
  wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-shipping&saved=1'));exit;
 }
 static function request($path,$method='POST',$payload=null){
  $s=self::settings();if(empty($s['shippo_enabled'])||empty($s['shippo_token']))return new WP_Error('shipping_disabled','Shippo is not configured.');
  if(!preg_match('/^shippo_(test|live)_[A-Za-z0-9]+$/',$s['shippo_token']))return new WP_Error('shipping_token','Invalid Shippo token format.');
  if(!empty($s['shippo_test'])&&strpos($s['shippo_token'],'shippo_test_')!==0)return new WP_Error('shipping_mode','Test mode requires a Shippo test token.');
  if(empty($s['shippo_test'])&&strpos($s['shippo_token'],'shippo_live_')!==0)return new WP_Error('shipping_mode','Live mode requires a Shippo live token.');
  $args=['method'=>$method,'timeout'=>25,'redirection'=>0,'headers'=>['Authorization'=>'ShippoToken '.$s['shippo_token'],'Content-Type'=>'application/json','SHIPPO-API-VERSION'=>'2018-02-08']];
  if($payload!==null)$args['body']=wp_json_encode($payload);
  $r=wp_remote_request('https://api.goshippo.com/'.ltrim($path,'/'),$args);
  if(is_wp_error($r))return $r;
  $code=wp_remote_retrieve_response_code($r);$body=json_decode(wp_remote_retrieve_body($r),true);
  if($code<200||$code>=300||!is_array($body))return new WP_Error('shipping_api','Shippo returned HTTP '.$code.'. '.sanitize_text_field(wp_json_encode($body['detail']??$body['messages']??'')));
  return $body;
 }
 static function sender($s){return ['name'=>$s['sender_name'],'company'=>$s['sender_company'],'street1'=>$s['sender_street'],'city'=>$s['sender_city'],'state'=>$s['sender_region'],'zip'=>$s['sender_zip'],'country'=>$s['sender_country'],'phone'=>$s['sender_phone'],'email'=>$s['sender_email']];}
 static function recipient($entry){
  $a=(array)get_post_meta($entry,'_erm_shipping_address',true);$data=(array)get_post_meta($entry,'_erm_data',true);
  return ['name'=>sanitize_text_field($data['name']??'Customer'),'street1'=>$a['street']??'','city'=>$a['city']??'','state'=>$a['region']??'','zip'=>$a['postcode']??'','country'=>strtoupper($a['country']??'US'),'email'=>sanitize_email($data['email']??'')];
 }
 static function shipment($entry){$s=self::settings();foreach(['sender_name','sender_street','sender_city','sender_region','sender_zip','sender_country'] as $k)if(empty($s[$k]))return new WP_Error('sender_missing','Complete the sender address in Shipping Providers.');
  $to=self::recipient($entry);foreach(['street1','city','state','zip','country'] as $k)if(empty($to[$k]))return new WP_Error('recipient_missing','Order is missing a complete shipping address.');
  return ['address_from'=>self::sender($s),'address_to'=>$to,'parcels'=>[['length'=>(string)$s['parcel_length'],'width'=>(string)$s['parcel_width'],'height'=>(string)$s['parcel_height'],'distance_unit'=>'in','weight'=>(string)$s['parcel_weight'],'mass_unit'=>'oz']],'async'=>false];
 }
 static function quote_order(){
  if(!current_user_can('manage_options'))wp_die('Access denied',403);$entry=absint($_POST['entry']??0);check_admin_referer('erm_shipping_quote_'.$entry);
  if(get_post_type($entry)!=='erm_entry'||get_post_meta($entry,'_erm_fulfillment',true)!=='shipping')wp_die('Invalid shipping order',400);
  $shipment=self::shipment($entry);$result=is_wp_error($shipment)?$shipment:self::request('shipments/','POST',$shipment);
  if(is_wp_error($result))update_post_meta($entry,'_erm_shipping_error',$result->get_error_message());
  else{delete_post_meta($entry,'_erm_shipping_error');$rates=[];foreach((array)($result['rates']??[]) as $rate){if(($rate['currency']??'')!=='USD'||empty($rate['object_id']))continue;$rates[]=['id'=>sanitize_text_field($rate['object_id']),'carrier'=>sanitize_text_field($rate['provider']??''),'service'=>sanitize_text_field($rate['servicelevel']['name']??''),'amount'=>round((float)($rate['amount']??0),2),'currency'=>'USD'];}usort($rates,function($a,$b){return $a['amount']<=>$b['amount'];});update_post_meta($entry,'_erm_shippo_rates',$rates);update_post_meta($entry,'_erm_shippo_quoted_at',time());}
  wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$entry));exit;
 }
 static function purchase_label(){
  if(!current_user_can('manage_options'))wp_die('Access denied',403);$entry=absint($_POST['entry']??0);check_admin_referer('erm_shipping_label_'.$entry);
  if(get_post_type($entry)!=='erm_entry'||get_post_meta($entry,'_erm_fulfillment',true)!=='shipping')wp_die('Invalid shipping order',400);
  if(get_post_meta($entry,'_erm_shippo_transaction',true))wp_die('A label transaction is already recorded for this order. Do not purchase a second label.',409);
  $rates=(array)get_post_meta($entry,'_erm_shippo_rates',true);$id=sanitize_text_field(wp_unslash($_POST['rate']??''));$chosen=null;foreach($rates as $rate)if(hash_equals((string)$rate['id'],$id)){$chosen=$rate;break;}
  if(!$chosen||time()-(int)get_post_meta($entry,'_erm_shippo_quoted_at',true)>3600)wp_die('Rate is unavailable or stale. Refresh rates before purchasing.',400);
  // One-click purchase is an irreversible paid action: requires authenticated admin + nonce + explicit confirmation checkbox.
  if(empty($_POST['confirm_purchase']))wp_die('Confirm the label purchase before proceeding.',400);
  $lock='erm_label_lock_'.$entry;if(get_transient($lock))wp_die('Label purchase is already in progress. Check Shippo before retrying.',409);set_transient($lock,1,120);
  $result=self::request('transactions/','POST',['rate'=>$id,'async'=>false,'label_file_type'=>'PDF_4x6','metadata'=>'FCRC order '.$entry]);
  if(is_wp_error($result)){update_post_meta($entry,'_erm_shipping_error',$result->get_error_message());delete_transient($lock);}
  else{
   // Persist transaction ID even if Shippo reports a pending/error state, to prevent accidental duplicate charges.
   if(!empty($result['object_id']))update_post_meta($entry,'_erm_shippo_transaction',sanitize_text_field($result['object_id']));
   update_post_meta($entry,'_erm_shippo_transaction_status',sanitize_text_field($result['status']??'UNKNOWN'));
   if(!empty($result['label_url'])&&strpos($result['label_url'],'https://')===0)update_post_meta($entry,'_erm_shippo_label_url',esc_url_raw($result['label_url']));
   if(!empty($result['tracking_number']))update_post_meta($entry,'_erm_shipping_tracking',sanitize_text_field($result['tracking_number']));
   if(!empty($result['tracking_url_provider']))update_post_meta($entry,'_erm_shipping_tracking_url',esc_url_raw($result['tracking_url_provider']));
   delete_post_meta($entry,'_erm_shipping_error');delete_transient($lock);
  }
  wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$entry));exit;
 }
 static function order_panel($entry){
  if(!current_user_can('manage_options')||get_post_type($entry)!=='erm_entry'||get_post_meta($entry,'_erm_fulfillment',true)!=='shipping')return;
  $a=(array)get_post_meta($entry,'_erm_shipping_address',true);$transaction=get_post_meta($entry,'_erm_shippo_transaction',true);
  echo '<div class="wrap"><div class="postbox" style="padding:16px;max-width:900px"><h2>Shipping and Labels</h2><p><strong>Destination:</strong> '.esc_html(implode(', ',array_filter($a))).'</p><p><strong>Checkout shipping charge:</strong> $'.esc_html(number_format((float)get_post_meta($entry,'_erm_shipping_cost',true),2)).' (manual shipping zone estimate)</p>';
  $error=get_post_meta($entry,'_erm_shipping_error',true);if($error)echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
  if($transaction){echo '<p><strong>Shippo transaction:</strong> '.esc_html($transaction).' — '.esc_html(get_post_meta($entry,'_erm_shippo_transaction_status',true)).'</p>'; $label=get_post_meta($entry,'_erm_shippo_label_url',true);if($label)echo '<p><a class="button button-primary" href="'.esc_url($label).'" target="_blank" rel="noopener noreferrer">Open / Print 4×6 Shipping Label</a></p>'; $track=get_post_meta($entry,'_erm_shipping_tracking',true);if($track)echo '<p><strong>Tracking:</strong> '.esc_html($track).'</p>';}
  else{
   echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_shipping_quote"><input type="hidden" name="entry" value="'.absint($entry).'">';wp_nonce_field('erm_shipping_quote_'.$entry);echo '<button class="button">Get Live Shippo Rates</button></form>';
   $rates=(array)get_post_meta($entry,'_erm_shippo_rates',true);if($rates){echo '<h3>Available Rates</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_shipping_label"><input type="hidden" name="entry" value="'.absint($entry).'">';wp_nonce_field('erm_shipping_label_'.$entry);echo '<p><label>Choose service <select name="rate">';foreach($rates as $r)echo '<option value="'.esc_attr($r['id']).'">'.esc_html($r['carrier'].' — '.$r['service'].' — $'.number_format($r['amount'],2)).'</option>';echo '</select></label></p><p><label><input type="checkbox" name="confirm_purchase" value="1" required> I authorize the purchase of this shipping label and the associated postage charge.</label></p><button class="button button-primary">Purchase Shipping Label</button></form>';}
  }echo '</div></div>';
 }
}
