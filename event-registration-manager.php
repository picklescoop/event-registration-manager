<?php
/**
 * Plugin Name: Form, Cart and Registration Creator
 * Description: Reusable event forms with configurable fields, optional payments, uploads, receipts, and registration management.
 * Version: 1.3.68
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: event-registration-manager
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/includes/google-calendar.php';
require_once __DIR__.'/includes/microsoft-calendar.php';
require_once __DIR__.'/includes/shipping-providers.php';
require_once __DIR__.'/includes/shippo.php';
final class ERM_Plugin {
 const VERSION='1.3.68';
 static $reschedule_entry=0;
 const DEFAULT_FIELDS=[['id'=>'name','label'=>'Full Name','type'=>'text','required'=>true],['id'=>'business','label'=>'Business / Organization','type'=>'text','required'=>false],['id'=>'address','label'=>'Mailing Address','type'=>'textarea','required'=>false],['id'=>'phone','label'=>'Phone Number','type'=>'tel','required'=>false],['id'=>'email','label'=>'Email Address','type'=>'email','required'=>true],['id'=>'products','label'=>'Products Sold','type'=>'textarea','required'=>false]];
 static function init(){ERM_Google_Calendar::init();ERM_Shipping_Providers::init();ERM_Shippo::init();add_action('admin_post_nopriv_erm_manage_appointment',[__CLASS__,'customer_appointment']);add_action('admin_post_erm_manage_appointment',[__CLASS__,'customer_appointment']);add_action('admin_post_erm_reschedule',[__CLASS__,'reschedule']);add_action('wp_ajax_erm_available_slots',[__CLASS__,'available_slots']);add_action('wp_ajax_nopriv_erm_available_slots',[__CLASS__,'available_slots']);add_action('init',[__CLASS__,'types']);add_action('add_meta_boxes',[__CLASS__,'metaboxes']);add_action('save_post_erm_event',[__CLASS__,'save_event']);add_shortcode('event_registration',[__CLASS__,'form']);add_shortcode('event_registrations',[__CLASS__,'public_list']);add_action('admin_post_nopriv_erm_submit',[__CLASS__,'submit']);add_action('admin_post_erm_submit',[__CLASS__,'submit']);add_action('admin_menu',[__CLASS__,'admin_menu']);add_action('admin_head',[__CLASS__,'hide_dashboard_submenu']);add_action('admin_init',[__CLASS__,'redirect_forms_home']);add_action('admin_post_erm_status',[__CLASS__,'status']);add_action('admin_post_erm_delete_submission',[__CLASS__,'delete_submission']);add_action('admin_post_erm_save_payment_accounts',[__CLASS__,'save_payment_accounts']);add_action('admin_post_erm_save_appointment_integrations',[__CLASS__,'save_appointment_integrations']);add_action('admin_post_erm_record_payment',[__CLASS__,'record_payment']);add_action('admin_post_erm_export',[__CLASS__,'export']);add_action('admin_post_erm_reply',[__CLASS__,'reply']);add_action('admin_post_erm_checkin',[__CLASS__,'checkin']);add_action('admin_post_erm_undo_checkin',[__CLASS__,'undo_checkin']);add_action('admin_post_erm_ticket',[__CLASS__,'ticket_page']);add_action('admin_post_nopriv_erm_ticket',[__CLASS__,'ticket_page']);add_action('admin_post_erm_scanner',[__CLASS__,'scanner_page']);add_action('admin_enqueue_scripts',[__CLASS__,'admin_assets']);add_action('admin_footer-edit.php',[__CLASS__,'shortcode_copy_script']);add_action('admin_footer-post.php',[__CLASS__,'appointment_builder_visibility_script']);add_action('admin_footer-post-new.php',[__CLASS__,'appointment_builder_visibility_script']);add_action('wp_enqueue_scripts',[__CLASS__,'styles']);add_filter('manage_erm_event_posts_columns',[__CLASS__,'form_list_columns']);add_action('manage_erm_event_posts_custom_column',[__CLASS__,'form_list_column'],10,2);add_filter('post_row_actions',[__CLASS__,'clone_row_action'],10,2);add_action('admin_post_erm_clone_form',[__CLASS__,'clone_form']);add_action('admin_post_erm_export_form',[__CLASS__,'export_form']);add_action('admin_post_erm_import_form',[__CLASS__,'import_form']);}
 static function types(){register_post_type('erm_event',['label'=>'Super Forms','labels'=>['name'=>'Super Forms','singular_name'=>'Form','menu_name'=>'Super Forms','all_items'=>'All Forms','add_new_item'=>'Add Form'],'public'=>false,'show_ui'=>true,'show_in_menu'=>true,'menu_icon'=>'dashicons-forms','supports'=>['title']]);register_post_type('erm_entry',['label'=>'Registrations','public'=>false,'show_ui'=>false,'supports'=>['title']]);}
 static function clone_row_action($actions,$post){
  if($post->post_type!=='erm_event'||!current_user_can('edit_post',$post->ID)||!current_user_can('edit_posts'))return $actions;
  $url=wp_nonce_url(admin_url('admin-post.php?action=erm_clone_form&form_id='.$post->ID),'erm_clone_form_'.$post->ID);
  $actions['erm_export_form']='<a href='.esc_url(wp_nonce_url(admin_url('admin-post.php?action=erm_export_form&form_id='.$post->ID),'erm_export_form_'.$post->ID)).'">Export JSON</a>';
  $actions['erm_clone']='<a href="'.esc_url($url).'">Clone Form</a>';
  return $actions;
 }
 static function export_form(){
  $id=absint($_GET['form_id']??0);
  if(!$id||get_post_type($id)!=='erm_event'||!current_user_can('edit_post',$id))wp_die('Not permitted.');
  check_admin_referer('erm_export_form_'.$id);
  $data=['format'=>'fcrc-form','schema_version'=>1,'title'=>get_the_title($id),'settings'=>self::settings($id)];
  nocache_headers();header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="form-'.intval($id).'.json"');
  echo wp_json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;
 }
 static function import_form(){
  if(!current_user_can('manage_options'))wp_die('Not permitted.');check_admin_referer('erm_import_form');
  if(empty($_FILES['form_json'])||!is_array($_FILES['form_json'])||($_FILES['form_json']['error']??1)!==UPLOAD_ERR_OK)wp_die('Please upload a valid JSON file.');
  $f=$_FILES['form_json'];if((int)$f['size']>1048576||!is_uploaded_file($f['tmp_name']))wp_die('JSON file too large or invalid.');
  $data=json_decode(file_get_contents($f['tmp_name']),true);
  if(!is_array($data)||($data['format']??'')!=='fcrc-form'||($data['schema_version']??0)!==1||!isset($data['settings'])||!is_array($data['settings']))wp_die('Unrecognized form export format.');
  $raw=$data['settings'];$clean=self::defaults();
  foreach(['intro','ack','mail_address','admin_subject','customer_subject'] as $key)if(isset($raw[$key])&&is_string($raw[$key]))$clean[$key]=sanitize_textarea_field($raw[$key]);foreach(['admin_template','customer_template'] as $key)if(isset($raw[$key])&&is_string($raw[$key]))$clean[$key]=wp_kses_post($raw[$key]);
  foreach(['hero','background_image','paypal','venmo','cashapp','calendar_url'] as $key)if(isset($raw[$key])&&is_string($raw[$key]))$clean[$key]=esc_url_raw($raw[$key]);
  foreach(['zelle','payee'] as $key)if(isset($raw[$key])&&is_string($raw[$key]))$clean[$key]=sanitize_text_field($raw[$key]);
  foreach(['accent','background','text_color','label_bg','label_color','heading_color'] as $key)if(isset($raw[$key]))$clean[$key]=sanitize_hex_color($raw[$key])?:$clean[$key];
  foreach(['background_opacity','label_bg_opacity'] as $key)if(isset($raw[$key]))$clean[$key]=max(0,min(100,(int)$raw[$key]));
  if(isset($raw['font']))$clean['font']=preg_replace('/[^a-zA-Z0-9 ,\-]/','',substr((string)$raw['font'],0,90));
  if(in_array($raw['form_type']??'',['registration','contact','appointment','shop'],true))$clean['form_type']=$raw['form_type'];
  if(in_array($raw['payment_mode']??'',['none','optional','required'],true))$clean['payment_mode']=$raw['payment_mode'];
  $clean['amount']=max(0,(float)($raw['amount']??0));$clean['upload_mb']=max(1,min(10,(int)($raw['upload_mb']??5)));
  $clean['organizer_email']=sanitize_email($raw['organizer_email']??get_option('admin_email'));
  $clean['receipt']=empty($raw['receipt'])?0:1;$clean['tickets_enabled']=empty($raw['tickets_enabled'])?0:1;
  $clean['methods']=array_values(array_intersect(['paypal','venmo','zelle','cashapp','mail','cash'],array_map('sanitize_key',(array)($raw['methods']??[]))));
  $clean['fields']=[];$clean['public_fields']=[];$ids=[];
  foreach(array_slice((array)($raw['fields']??[]),0,100) as $field){if(!is_array($field))continue;$id=sanitize_key($field['id']??'');if(!$id||isset($ids[$id]))continue;$ids[$id]=true;$type=sanitize_key($field['type']??'text');if(!in_array($type,['text','email','tel','number','textarea','select','radio','checkbox','date','file'],true))$type='text';$clean['fields'][]=['id'=>$id,'label'=>sanitize_text_field($field['label']??$id),'type'=>$type,'options'=>sanitize_text_field($field['options']??''),'required'=>!empty($field['required']),'width'=>in_array((int)($field['width']??12),[4,6,12],true)?(int)$field['width']:12];if(in_array($id,(array)($raw['public_fields']??[]),true)&&!in_array($id,['email','phone','address'],true)&&$type!=='file')$clean['public_fields'][]=$id;}
  $clean['shipping_live']=!empty($raw['shipping_live'])?1:0;$clean['fulfillment_pickup']=!empty($raw['fulfillment_pickup'])?1:0;$clean['fulfillment_shipping']=!empty($raw['fulfillment_shipping'])?1:0;
 $clean['shipping_default']=round(min(100000,max(0,(float)($raw['shipping_default']??0))),2);$clean['shipping_rules']=[];
 foreach(array_slice((array)($raw['shipping_rules']??[]),0,100) as $rule){if(!is_array($rule))continue;$c=strtoupper(preg_replace('/[^A-Za-z]/','',(string)($rule['country']??'')));if(strlen($c)!==0&&strlen($c)!==2)continue;$prefix=substr(strtoupper(preg_replace('/[^A-Za-z0-9]/','',(string)($rule['prefix']??''))),0,12);$clean['shipping_rules'][]=['country'=>$c,'prefix'=>$prefix,'price'=>round(min(100000,max(0,(float)($rule['price']??0))),2)];}
 $clean['registration_levels']=[];foreach(array_slice((array)($raw['registration_levels']??[]),0,50) as $level){if(!is_array($level))continue;$name=substr(sanitize_text_field($level['name']??''),0,120);if($name!=='')$clean['registration_levels'][]=['name'=>$name,'price'=>round(min(10000000,max(0,(float)($level['price']??0))),2)];}
  $clean['products']=[];foreach(array_slice((array)($raw['products']??[]),0,50) as $item){if(!is_array($item))continue;$name=sanitize_text_field($item['name']??'');if(!$name)continue;$clean['products'][]=['name'=>$name,'description'=>sanitize_textarea_field($item['description']??''),'image'=>esc_url_raw($item['image']??''),'icon'=>sanitize_text_field($item['icon']??''),'price'=>round(max(0,(float)($item['price']??0)),2)];}
  $title=sanitize_text_field($data['title']??'Imported Form');$id=wp_insert_post(['post_type'=>'erm_event','post_status'=>'draft','post_title'=>$title.' (Imported)','post_author'=>get_current_user_id()],true);
  if(is_wp_error($id))wp_die(esc_html($id->get_error_message()));update_post_meta($id,'_erm_settings',wp_slash($clean));
  wp_safe_redirect(admin_url('post.php?post='.$id.'&action=edit&erm_imported=1'));exit;
 }
 static function transfer_page(){
  if(!current_user_can('manage_options'))wp_die('Not permitted.');
  $forms=get_posts(['post_type'=>'erm_event','post_status'=>'any','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC']);
  echo '<div class="wrap erm-transfer-page">';self::admin_header('Import & Export','Move reusable forms between WordPress websites without moving private submissions.');
  echo '<div class="erm-transfer-grid"><section class="erm-panel"><span class="dashicons dashicons-download"></span><h2>Export a Form</h2><p>Download a JSON file containing its fields, products, styling, and notification settings. No registrant records are exported.</p>';
  foreach($forms as $form){if(!current_user_can('edit_post',$form->ID))continue;$url=wp_nonce_url(admin_url('admin-post.php?action=erm_export_form&form_id='.$form->ID),'erm_export_form_'.$form->ID);echo '<div class="erm-transfer-row"><strong>'.esc_html($form->post_title).'</strong><a class="button" href="'.esc_url($url).'">Export JSON</a></div>';}
  echo '</section><section class="erm-panel"><span class="dashicons dashicons-upload"></span><h2>Import a Form</h2><p>Choose a JSON file exported by this plugin. A new draft form is created, leaving existing forms and entries unchanged.</p><form action="'.esc_url(admin_url('admin-post.php')).'" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="erm_import_form">';wp_nonce_field('erm_import_form');echo '<p><input type="file" name="form_json" accept=".json,application/json" required></p><button class="button button-primary">Import as Draft</button></form><p class="description">Check imported email recipients, payment links, image URLs, and settings before publishing on another site.</p></section></div></div></div>';
 }
 static function clone_form(){
  $id=absint($_GET['form_id']??0);
  if(!$id||get_post_type($id)!=='erm_event'||!current_user_can('edit_post',$id)||!current_user_can('edit_posts'))wp_die('You cannot clone this form.');
  check_admin_referer('erm_clone_form_'.$id);
  $source=get_post($id);
  $new_id=wp_insert_post(['post_type'=>'erm_event','post_status'=>'draft','post_title'=>$source->post_title.' (Copy)','post_author'=>get_current_user_id()],'wp_error');
  if(is_wp_error($new_id))wp_die(esc_html($new_id->get_error_message()));
  $saved=get_post_meta($id,'_erm_settings',true);
  if(is_array($saved))update_post_meta($new_id,'_erm_settings',wp_slash($saved));
  // Do not clone submissions, submission history, payment records or entry IDs.
  wp_safe_redirect(admin_url('post.php?post='.$new_id.'&action=edit&erm_cloned=1'));
  exit;
 }
 static function defaults(){return ['fields'=>self::DEFAULT_FIELDS,'intro'=>'','hero'=>'','accent'=>'#603f91','payment_mode'=>'optional','amount'=>'0','registration_levels'=>[],'fulfillment_pickup'=>1,'fulfillment_shipping'=>0,'shipping_default'=>0,'shipping_rules'=>[],'shipping_live'=>0,'methods'=>['paypal','venmo','zelle','cashapp','mail'],'paypal'=>'','venmo'=>'','zelle'=>'','cashapp'=>'','payee'=>'','mail_address'=>'','organizer_email'=>get_option('admin_email'),'receipt'=>1,'ack'=>'Thank you! Your registration has been received.','public_fields'=>[],'upload_types'=>['jpg','jpeg','png','webp','pdf'],'upload_mb'=>5,'form_type'=>'registration','calendar_url'=>'','appointment_modes'=>['studio'],'appointment_studio_address'=>'','appointment_travel_note'=>'','appointment_online_platform'=>'zoom','appointment_online_url'=>'','appointment_duration'=>30,'appointment_interval'=>30,'appointment_days'=>14,'appointment_notice'=>0,'appointment_buffer_before'=>0,'appointment_buffer_after'=>0,'appointment_blocked'=>'','appointment_extra_windows'=>'','appointment_ical_url'=>'','appointment_hours'=>['1'=>['09:00','17:00'],'2'=>['09:00','17:00'],'3'=>['09:00','17:00'],'4'=>['09:00','17:00'],'5'=>['09:00','17:00']],'admin_subject'=>'New submission: {form_title} #{entry_id}','admin_template'=>'{fields} {appointment} {items} Total: {total}','customer_subject'=>'Submission received: {form_title} (#{entry_id})','customer_template'=>'','font'=>'inherit','background'=>'#ffffff','text_color'=>'#222222','background_image'=>'','background_opacity'=>35,'label_bg'=>'#ffffff','label_bg_opacity'=>90,'label_color'=>'#222222','heading_color'=>'#222222','products'=>[],'tickets_enabled'=>0];}
 static function settings($id){$saved=get_post_meta($id,'_erm_settings',true);$settings=wp_parse_args(is_array($saved)?$saved:[],self::defaults());$global_integrations=(array)get_option('erm_appointment_integrations',[]);if(!empty($global_integrations['google_booking_url']))$settings['calendar_url']=$global_integrations['google_booking_url'];if(!empty($global_integrations['google_calendar_id']))$settings['google_calendar_id']=$global_integrations['google_calendar_id'];foreach((array)($global_integrations['provider_credentials']??[]) as $key=>$value)if(in_array($key,['zoom_account_id','zoom_client_id','zoom_client_secret','zoom_user','teams_tenant_id','teams_client_id','teams_client_secret','teams_user_id','discord_bot_token','discord_channel_id'],true)&&is_string($value)&&$value!=='')$settings[$key]=$value;$global=get_option('erm_payment_accounts',false);if(is_array($global))foreach(self::payment_account_keys() as $key)$settings[$key]=$global[$key]??'';return $settings;}
 static function payment_account_keys(){return ['paypal','venmo','zelle','cashapp','payee','mail_address'];}
 static function payment_setup_guide(){
  $providers=[
   ['Stripe — cards, Apple Pay and Google Pay','https://dashboard.stripe.com/','1. Create or sign in to a Stripe account and complete business verification. 2. In Developers > API keys, obtain test keys first. 3. A future Super Forms Stripe integration must create Checkout Sessions server-side and display a webhook endpoint. 4. Register that endpoint in Stripe Workbench/Webhooks, subscribe to checkout.session.completed and asynchronous payment events, and save the signing secret. 5. Test successful, failed and delayed payments before switching to live keys.','NOT CONNECTED: This version has no Stripe credential fields, checkout sessions or verified webhook handler. Do not paste Stripe secret keys into payment URL fields.'],
   ['PayPal — PayPal checkout and eligible Venmo','https://developer.paypal.com/dashboard/','1. Sign in with a PayPal Business account. 2. Under Apps & Credentials, create a REST app and copy Sandbox Client ID and Secret. 3. A future Super Forms PayPal integration must create and capture Orders on the server and expose a webhook endpoint. 4. Register that webhook with PayPal and save its Webhook ID. 5. Verify webhook signatures and confirmed captures, then repeat with Live credentials. Venmo availability depends on merchant eligibility and checkout configuration.','NOT CONNECTED: A PayPal.me or Venmo URL is only a payment link; it cannot verify payment or automatically confirm registrations. Never enter your Client Secret in a URL field.'],
   ['Square — card checkout','https://developer.squareup.com/apps','1. Create a Square developer application. 2. Find Sandbox access token and location ID, then create a test payment flow. 3. A future Super Forms integration must use Square hosted checkout or Payments API and provide a webhook notification URL. 4. Configure webhook subscriptions and verify signatures on the server. 5. Test payment completion, failure, refunds and Live credentials.','NOT CONNECTED: This version has no Square API integration or webhook handler. Do not enter access tokens in public payment fields.'],
   ['Zelle — manual reconciliation','https://www.zellepay.com/','1. Confirm Zelle is supported by your bank. 2. Enter your business payment recipient details in Payment Accounts > Zelle. 3. Give the registrant their reference number and payment instructions. 4. Verify receipt directly in your bank account, then record the amount received and approve the submission.','Zelle payments are not automatically verified by this plugin. Never rely on a screenshot as proof of payment.'],
   ['Cash App — manual reconciliation','https://cash.app/','1. Obtain your business payment link. 2. Enter the URL in Payment Accounts > Cash App. 3. Ask customers to include their registration reference. 4. Check your Cash App transaction history before manually recording payment and approving.','A Cash App link does not automatically confirm payment in this version.'],
   ['Cash or check — offline','', '1. Enter the payee and mailing address below for checks. 2. For cash, provide instructions to arrange in-person payment. 3. The submission remains pending. 4. After receiving and verifying the full amount, open Registrations > Submissions, record payment in Payment management, and change Registration status to Confirmed.','Do not mark the submission Paid or Confirmed until funds have been verified.']
  ];
  echo '<section style="max-width:1000px;margin-top:24px"><h2>Payment connection setup guide</h2><p><strong>Important:</strong> These instructions explain provider setup and the integration requirements. API-based checkout is not yet implemented in this version of Super Forms. Payment links alone do not prove payment. Never enter private API credentials in the URL fields above.</p>';
  foreach($providers as $p){
   echo '<details style="background:#fff;border:1px solid #c3c4c7;margin:10px 0;padding:14px 18px"><summary style="cursor:pointer;font-weight:600">'.esc_html($p[0]).'</summary><p>'.esc_html($p[2]).'</p><p><strong>'.esc_html($p[3]).'</strong></p>';
   if($p[1])echo '<p><a href="'.esc_url($p[1]).'" target="_blank" rel="noopener noreferrer">Open official provider dashboard / website ↗</a></p>';
   echo '</details>';
  }
  echo '<p><strong>Security checklist:</strong> Use HTTPS; store secrets server-side with administrator-only access; verify signed webhook events; validate amount, currency, transaction status and order ID; prevent duplicate webhook processing; never mark an online payment Paid based only on a browser redirect.</p></section>';
 }
 static function appointment_integrations_page(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);
  $o=(array)get_option('erm_appointment_integrations',[]);
  $formats=['zoom'=>'Zoom','teams'=>'Microsoft Teams','discord'=>'Discord','slack'=>'Slack','freeconferencecall'=>'FreeConferenceCall.com','other'=>'Other'];
  $enabled=(array)($o['formats']??['zoom']);
  echo '<div class="wrap"><h1>Appointment integrations</h1><p>Choose the online meeting services you offer and the Google calendars that block appointment availability. In-person visits do not require an online meeting.</p>';
  if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Appointment integration settings saved.</p></div>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_save_appointment_integrations">';
  wp_nonce_field('erm_save_appointment_integrations');
  echo '<h2>Allowed online meeting services</h2>';
  foreach($formats as $key=>$label)echo '<p><label><input type="checkbox" name="formats[]" value="'.esc_attr($key).'" '.checked(in_array($key,$enabled,true),true,false).'> '.esc_html($label).'</label> <input type="url" class="regular-text" name="meeting_links['.esc_attr($key).']" value="'.esc_attr($o['meeting_links'][$key]??'').'" placeholder="https://..." aria-label="'.esc_attr($label).' meeting link"></p>';
  echo '<h2>Automatic meeting creation credentials</h2><p>These account credentials apply to all appointment forms. Leave a saved secret blank to keep its existing value. Existing per-form credentials continue to work as fallback until you enter global credentials.</p>';
  $credentials=['zoom_account_id'=>'Zoom Account ID','zoom_client_id'=>'Zoom Client ID','zoom_client_secret'=>'Zoom Client Secret','zoom_user'=>'Zoom host email','teams_tenant_id'=>'Teams tenant ID','teams_client_id'=>'Teams app client ID','teams_client_secret'=>'Teams app client secret','teams_user_id'=>'Teams organizer user object ID','discord_bot_token'=>'Discord bot token','discord_channel_id'=>'Discord voice channel ID'];
  foreach($credentials as $key=>$label){$secret=in_array($key,['zoom_client_secret','teams_client_secret','discord_bot_token'],true);echo '<p><label><strong>'.esc_html($label).'</strong><br><input class="regular-text" type="'.($secret?'password':'text').'" name="provider_credentials['.esc_attr($key).']" value="'.($secret?'':esc_attr($o['provider_credentials'][$key]??'')).'" autocomplete="off" placeholder="'.($secret&&!empty($o['provider_credentials'][$key])?'Saved — leave blank to keep':'').'"></label></p>';}
  $ms=ERM_Microsoft_Calendar::config();
  echo '<h2>Microsoft 365 / Outlook Calendar</h2><p>Connect a Microsoft 365 work or school calendar through a Microsoft Entra application. This integration reads calendar events to block busy times or find guest-speaker meetings. It does not currently write confirmed appointments to Outlook.</p>';
  echo '<p><label><input type="checkbox" name="ms_enabled" value="1" '.checked(!empty($ms['enabled']),true,false).'> Enable Microsoft 365 calendar availability</label></p>';
  foreach(['tenant_id'=>'Directory (tenant) ID','client_id'=>'Application (client) ID','user_id'=>'Calendar owner email or user object ID'] as $key=>$label)echo '<p><label>'.esc_html($label).'<br><input class="regular-text" name="ms['.esc_attr($key).']" value="'.esc_attr($ms[$key]??'').'"></label></p>';
  echo '<p><label>Client secret<br><input type="password" class="regular-text" autocomplete="new-password" name="ms[client_secret]" placeholder="'.(!empty($ms['client_secret'])?'Saved — leave blank to keep':'Enter client secret').'"></label></p>';
  echo '<p class="description">Setup: Register an app in <a href="https://entra.microsoft.com/" target="_blank" rel="noopener noreferrer">Microsoft Entra</a>, grant Microsoft Graph <code>Calendars.Read</code> <strong>application</strong> permission, obtain tenant administrator consent, create a client secret, and enter the mailbox owner. Use an account authorized for that calendar. Restrict the app to the intended mailbox where supported.</p>';
  echo '<h2>Google appointment booking</h2><p>These settings apply across appointment forms. Previously configured per-form values are retained as fallbacks until a global value is entered.</p>';
  echo '<p><label>Google appointment booking page URL<br><input type="url" class="large-text" name="google_booking_url" value="'.esc_attr($o['google_booking_url']??'').'" placeholder="https://calendar.google.com/calendar/appointments/..."></label></p>';
  echo '<p><label>Google Calendar ID (connected account)<br><input type="text" class="regular-text" name="google_calendar_id" value="'.esc_attr($o['google_calendar_id']??'').'" placeholder="primary"></label></p>';
  echo '<p><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-google-calendar')).'">Connect Google Calendar and manage account settings</a></p>';
  echo '<h2>Google Calendar display</h2><p>An optional combined Google Calendar embed URL can display multiple calendars. It does not provide reliable conflict checks or calendar writes; use the connected Google account for those features.</p>';
  echo '<p><label>Combined calendar embed URL<br><input type="url" class="large-text" name="calendar_embed_url" value="'.esc_attr($o['calendar_embed_url']??'').'" placeholder="https://calendar.google.com/calendar/embed?src=..."></label></p>';
  echo '<h2>Google Calendar availability</h2><p>Enter one Google Calendar ID per line. All selected calendars are checked together for busy periods using the existing Google OAuth connection. A time is unavailable if any selected calendar is busy.</p>';
  echo '<textarea name="calendar_ids" rows="6" class="large-text code" placeholder="primary&#10;example@group.calendar.google.com">'.esc_textarea(implode("\n",(array)($o['calendar_ids']??[]))).'</textarea>';
  echo '<p>These IDs must be accessible to the connected Google account. If left blank, each form uses its existing configured calendar or primary calendar.</p>';
  echo '<p><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-google-calendar')).'">Connect or manage Google Calendar OAuth</a></p>';
  echo '<p><button class="button button-primary">Save appointment integrations</button></p></form>';
  echo '<hr>';ERM_Google_Calendar::page(true);
  echo '<hr><section class="erm-integration-guide" style="max-width:980px"><h2>Appointment integration setup guide</h2><p>Use the guides below to configure providers. Credentials are restricted to WordPress administrators. Start with a test appointment before enabling a service for customers.</p>';
  echo '<details open><summary><strong>Getting started: choose an appointment meeting option</strong></summary><ol><li>In Allowed online meeting services, enable only the services you want to offer.</li><li>Paste an HTTPS meeting link beside each enabled service and save. Services without saved links are not offered to customers.</li><li>For automatic meeting creation, configure the provider credentials below. A fixed meeting link and automatic meeting creation are different features.</li><li>Edit an appointment form and choose the permitted meeting modes, duration and availability.</li><li>Submit a test request, approve it and check the customer email and meeting link.</li></ol><p><strong>Privacy:</strong> Avoid placing private account credentials in meeting-link fields. Do not publish secret keys.</p></details>';
  echo '<details><summary><strong>Zoom — Server-to-Server OAuth</strong></summary><ol><li>Open the <a href="https://marketplace.zoom.us/" target="_blank" rel="noopener noreferrer">Zoom App Marketplace</a> and create a Server-to-Server OAuth application.</li><li>Find the Account ID, Client ID and Client Secret on the app credentials screen.</li><li>Add the meeting creation permission required by your Zoom app, such as <code>meeting:write:admin</code>, and activate the app.</li><li>Enter the credentials under Automatic meeting creation credentials. Set the host email if your account requires a specific host.</li><li>Save, then test creating and confirming an appointment.</li></ol><p>Zoom account permissions and available scopes can vary. Verify the app permissions in Zoom before going live.</p></details>';
  echo '<details><summary><strong>Microsoft Teams — Microsoft Entra app</strong></summary><ol><li>In the <a href="https://entra.microsoft.com/" target="_blank" rel="noopener noreferrer">Microsoft Entra admin center</a>, register an application.</li><li>Copy its Directory (tenant) ID and Application (client) ID.</li><li>Create a client secret and copy its value before leaving the page.</li><li>Configure the appropriate Microsoft Graph online meeting permissions and any required application access policy/admin consent for the organizer.</li><li>Find the organizer user object ID and enter all four values in the Teams fields.</li><li>Save and test using an authorized organizer account.</li></ol><p>Teams meeting creation requires the correct Graph permissions and tenant policy. Credentials alone do not guarantee meeting creation.</p></details>';
  echo '<details><summary><strong>Discord — bot and voice channel</strong></summary><ol><li>Open the <a href="https://discord.com/developers/applications" target="_blank" rel="noopener noreferrer">Discord Developer Portal</a> and create an application with a bot.</li><li>Generate or reset the bot token and keep it private.</li><li>Invite the bot to the correct server with only the necessary permissions.</li><li>Enable Developer Mode in Discord, right-click the intended voice channel and copy its channel ID.</li><li>Enter the bot token and voice channel ID, save, and verify access.</li></ol><p>A Discord voice channel is not the same as a unique scheduled meeting. Confirm the desired behavior before offering it as an appointment option.</p></details>';
  echo '<details><summary><strong>Slack, FreeConferenceCall.com and other meeting services</strong></summary><p>Enable the service, enter its HTTPS joining link, and save. These options use a configured link; no automatic meeting-creation credentials are available for these services on this page. Verify that the link is accessible to intended participants.</p></details>';
  echo '<details><summary><strong>Google Calendar — connect, check availability and display calendars</strong></summary><ol><li>Open <a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-google-calendar')).'">Google Calendar connection settings</a> and authorize an account that can access the required calendars.</li><li>Find calendar IDs in Google Calendar under Settings for the calendar → Integrate calendar.</li><li>Enter one calendar ID per line in Google Calendar availability and save.</li><li>Use the optional Google Calendar embed URL only for displaying calendars; it does not create events or check availability.</li><li>Test with a busy event on a connected calendar and verify that the scheduler does not offer a conflicting time.</li></ol><p>Calendar permissions, OAuth configuration and sync must be working for conflict checking. If a calendar cannot be accessed, investigate the connection rather than assuming its times are free.</p></details>';
  echo '<details><summary><strong>Troubleshooting and security checklist</strong></summary><ul><li>Meeting option missing? Confirm both its checkbox and HTTPS link are saved.</li><li>Automatic meeting creation fails? Verify credentials, provider scopes, organizer identity and account permissions.</li><li>Calendar events not blocking time? Verify OAuth access, calendar IDs and timezones.</li><li>After changing settings, save and submit a fresh test appointment.</li><li>Keep WordPress and the plugin updated, use HTTPS, restrict admin access, and rotate exposed secrets.</li><li>Secret inputs are intentionally blank after saving; blank means keep the saved secret.</li></ul></details></section></div>';
 }
 static function save_appointment_integrations(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);
  check_admin_referer('erm_save_appointment_integrations');
  $allowed=['zoom','teams','discord','slack','freeconferencecall','other'];
  $raw=isset($_POST['formats'])&&is_array($_POST['formats'])?wp_unslash($_POST['formats']):[];
  $formats=array_values(array_intersect($allowed,array_map('sanitize_key',$raw)));
  $lines=preg_split('/\\r?\\n/',(string)wp_unslash($_POST['calendar_ids']??''));
  $ids=[];
  foreach($lines as $line){
   $id=trim(sanitize_text_field($line));
   if($id!==''&&strlen($id)<=250&&preg_match('/^[a-zA-Z0-9@._#-]+$/',$id))$ids[]=$id;
  }
  $embed=esc_url_raw(trim((string)wp_unslash($_POST['calendar_embed_url']??'')));
  if($embed!==''&&(!wp_http_validate_url($embed)||strpos($embed,'https://calendar.google.com/calendar/embed')!==0))$embed='';
  $links=[];$posted=isset($_POST['meeting_links'])&&is_array($_POST['meeting_links'])?wp_unslash($_POST['meeting_links']):[];
  foreach($allowed as $service){$url=esc_url_raw(trim(is_string($posted[$service]??null)?$posted[$service]:''));if($url!==''&&wp_http_validate_url($url)&&strpos($url,'https://')===0)$links[$service]=$url;}
  $previous=(array)get_option('erm_appointment_integrations',[]);
  $credentials=(array)($previous['provider_credentials']??[]);
  $posted_credentials=isset($_POST['provider_credentials'])&&is_array($_POST['provider_credentials'])?wp_unslash($_POST['provider_credentials']):[];
  foreach(['zoom_account_id','zoom_client_id','zoom_client_secret','zoom_user','teams_tenant_id','teams_client_id','teams_client_secret','teams_user_id','discord_bot_token','discord_channel_id'] as $key){
   if(!isset($posted_credentials[$key])||!is_string($posted_credentials[$key]))continue;
   $value=sanitize_text_field($posted_credentials[$key]);
   if($value!==''||!in_array($key,['zoom_client_secret','teams_client_secret','discord_bot_token'],true))$credentials[$key]=$value;
  }
  $booking_url=esc_url_raw(trim((string)wp_unslash($_POST['google_booking_url']??'')));
  if($booking_url!==''&&strpos($booking_url,'https://')!==0)$booking_url='';
  $calendar_id=sanitize_text_field(wp_unslash($_POST['google_calendar_id']??''));
  $ms_old=ERM_Microsoft_Calendar::config();$ms_new=['enabled'=>!empty($_POST['ms_enabled'])];
  $ms_post=isset($_POST['ms'])&&is_array($_POST['ms'])?wp_unslash($_POST['ms']):[];
  foreach(['tenant_id','client_id','user_id'] as $key)$ms_new[$key]=sanitize_text_field($ms_post[$key]??'');
  $secret=trim((string)($ms_post['client_secret']??''));$ms_new['client_secret']=$secret!==''?$secret:($ms_old['client_secret']??'');
  update_option('erm_microsoft_calendar',$ms_new,false);
  update_option('erm_appointment_integrations',['formats'=>$formats,'meeting_links'=>$links,'calendar_ids'=>array_slice(array_values(array_unique($ids)),0,30),'calendar_embed_url'=>$embed,'provider_credentials'=>$credentials,'google_booking_url'=>$booking_url,'google_calendar_id'=>$calendar_id],false);
  wp_safe_redirect(add_query_arg('saved','1',admin_url('edit.php?post_type=erm_event&page=erm-appointment-integrations')));exit;
 }
 static function payment_accounts_page(){
  if(!current_user_can('manage_options'))wp_die('Not authorized',403);
  $values=get_option('erm_payment_accounts',false);
  if(!is_array($values))$values=array_fill_keys(self::payment_account_keys(),'');
  echo '<div class="wrap"><h1>Payment Accounts</h1><p>Configure your payment destinations once. Individual forms only choose which methods to offer. These are payment instructions and external links, not automatic payment processing or verification.</p>';
  if(isset($_GET['updated']))echo '<div class="notice notice-success"><p>Payment accounts saved. All forms now use these shared account details.</p></div>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_save_payment_accounts">';
  wp_nonce_field('erm_save_payment_accounts');
  foreach(['paypal'=>'PayPal.me / checkout URL','venmo'=>'Venmo payment URL','zelle'=>'Zelle recipient information','cashapp'=>'Cash App payment URL','payee'=>'Checks payable to','mail_address'=>'Check mailing address and instructions'] as $key=>$label){
   echo '<div style="max-width:760px;background:#fff;padding:14px 18px;margin:12px 0;border:1px solid #d6deeb;border-radius:8px"><label for="erm-pay-'.esc_attr($key).'"><strong>'.esc_html($label).'</strong></label><br>';
   if($key==='mail_address')echo '<textarea id="erm-pay-mail_address" rows="4" style="width:100%" name="accounts[mail_address]">'.esc_textarea($values[$key]??'').'</textarea>';
   else echo '<input id="erm-pay-'.esc_attr($key).'" type="'.(in_array($key,['paypal','venmo','cashapp'],true)?'url':'text').'" style="width:100%" name="accounts['.esc_attr($key).']" value="'.esc_attr($values[$key]??'').'" placeholder="'.esc_attr(in_array($key,['paypal','venmo','cashapp'],true)?'https://...':'').'">';
   echo '</div>';
  }
  echo '<p><button type="submit" class="button button-primary">Save Payment Accounts</button></p></form>';
  if(get_option('erm_payment_accounts',false)===false)echo '<p><strong>Existing forms:</strong> Their previous payment instructions remain available until you save shared account details here. Copy your existing account information into this page before saving.</p>';
  echo '<section style="max-width:850px;margin:22px 0;padding:18px;background:#fff;border:1px solid #c3c4c7"><h2>Secure online payment integration — preparation</h2>';
  echo '<p><strong>Important:</strong> Stripe, PayPal and Square are not yet connected to checkout or verified payment webhooks. Do not accept card payments through this plugin until the integration passes sandbox testing.</p>';
  echo '<p>For production, define credentials in <code>wp-config.php</code> or your hosting secret manager, not in form fields or payment links. Do not share secrets by email or chat.</p>';
  $checks=[
   'Stripe'=>['ERM_STRIPE_SECRET_KEY','ERM_STRIPE_WEBHOOK_SECRET'],
   'PayPal'=>['ERM_PAYPAL_CLIENT_ID','ERM_PAYPAL_CLIENT_SECRET','ERM_PAYPAL_WEBHOOK_ID'],
   'Square'=>['ERM_SQUARE_ACCESS_TOKEN','ERM_SQUARE_LOCATION_ID','ERM_SQUARE_WEBHOOK_SIGNATURE_KEY']
  ];
  echo '<table class="widefat striped"><thead><tr><th>Provider</th><th>Required server configuration</th><th>Readiness</th></tr></thead><tbody>';
  foreach($checks as $provider=>$constants){
   $present=0;foreach($constants as $key)if(defined($key)&&is_string(constant($key))&&constant($key)!=='')$present++;
   echo '<tr><td>'.esc_html($provider).'</td><td><code>'.esc_html(implode('</code>, <code>',$constants)).'</code></td><td>'.($present===count($constants)?'Credentials present — checkout not enabled':'Not configured ('.$present.'/'.count($constants).')').'</td></tr>';
  }
  echo '</tbody></table><p>Credential presence is not a connection test. Actual hosted checkout, signature validation, refunds, and automatic payment status updates require a separate tested integration.</p></section>';
  self::payment_setup_guide();
  echo '</div>';
 }
 static function save_payment_accounts(){
  if(!current_user_can('manage_options'))wp_die('Not authorized',403);
  check_admin_referer('erm_save_payment_accounts');
  $raw=isset($_POST['accounts'])&&is_array($_POST['accounts'])?wp_unslash($_POST['accounts']):[];
  $clean=[];
  foreach(self::payment_account_keys() as $key){
   $value=is_string($raw[$key]??null)?trim($raw[$key]):'';
   $clean[$key]=in_array($key,['paypal','venmo','cashapp'],true)?esc_url_raw($value):($key==='mail_address'?sanitize_textarea_field($value):sanitize_text_field($value));
  }
  update_option('erm_payment_accounts',$clean,false);
  wp_safe_redirect(add_query_arg('updated','1',admin_url('edit.php?post_type=erm_event&page=erm-payment-accounts')));exit;
 }
 static function metaboxes(){add_meta_box('erm_config','Form, Cart and Registration Creator — Form Builder',[__CLASS__,'edit_box'],'erm_event','normal','high');}
 static function edit_box($post){wp_enqueue_media();$s=self::settings($post->ID);wp_nonce_field('erm_save','erm_nonce');echo '<p><strong>Embed shortcode:</strong> <code>[event_registration id="'.esc_attr($post->ID).'"]</code> &nbsp; <strong>Public list:</strong> <code>[event_registrations id="'.esc_attr($post->ID).'"]</code></p>';echo '<p><strong>New here?</strong> <a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-help')).'">Read the step-by-step Getting Started guide</a>. You can test your form before sharing it.</p>';echo '<p>Form fields can be added, reordered, renamed, and made required. Field IDs must be unique. For dropdowns and radio choices, separate options with a vertical bar ( | ).</p>';
 echo '<table class="widefat" id="erm-fields"><thead><tr><th>Field ID</th><th>Label</th><th>Type</th><th>Options</th><th>Required</th><th>Public</th><th>Width</th><th>Move</th><th></th></tr></thead><tbody>';
 foreach($s['fields'] as $f)self::field_row($f,in_array($f['id'],$s['public_fields'],true));echo '</tbody></table><p><button type="button" class="button" id="erm-add">Add Field</button> <button type="button" class="button" id="erm-preset">Restore Missing Defaults</button></p>';
 echo '<h3>Form type</h3><select id="erm-form-type" name="erm[form_type]" onchange="var section=document.getElementById(&quot;erm-appointment-settings&quot;);if(section){section.style.display=this.value===&quot;appointment&quot;?&quot;block&quot;:&quot;none&quot;;}">';foreach(['registration'=>'Registration','contact'=>'Question / Comment','appointment'=>'Appointment Request','shop'=>'Shopping Cart / Storefront'] as $v=>$label)echo '<option value="'.esc_attr($v).'" '.selected($s['form_type'],$v,false).'>'.esc_html($label).'</option>';echo '</select>';echo '<div id="erm-appointment-settings"'.(($s['form_type']??'registration')==='appointment'?'':' style="display:none"').'><h3>Appointment location and meeting options</h3><p>Choose which appointment types customers may request.</p>';
 foreach(['studio'=>'In studio / onsite','travel'=>'At the customer location','online'=>'Online meeting'] as $key=>$label)echo '<p><label><input type="checkbox" name="erm[appointment_modes][]" value="'.esc_attr($key).'" '.checked(in_array($key,(array)($s['appointment_modes']??['studio']),true),true,false).'> '.esc_html($label).'</label></p>';
 self::setting('appointment_studio_address','Studio / onsite address','text',$s);self::setting('appointment_travel_note','Travel coverage or additional instructions','textarea',$s);
 echo '<p class="description">Zoom, Teams and Discord credentials are managed globally under <a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-appointment-integrations')).'">Settings → Appointment Integrations</a>. Existing per-form credentials are retained as a fallback until replaced globally.</p>';
 echo '<h3>Appointment Scheduling</h3><p class="description">Google booking URLs and calendar IDs are configured under <a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-appointment-integrations')).'">Settings → Appointment Integrations</a>. Appointment duration and availability below remain specific to this form.</p>';echo '<p><label><input type="checkbox" name="erm[appointment_event_only]" value="1" '.checked(!empty($s['appointment_event_only']),true,false).'> <strong>Only allow bookings during matching Google Calendar events</strong></label></p><p class="description">Ideal for guest speakers. A calendar event with the keyword in its title becomes an allowed booking window even if Google marks that event busy. Existing bookings in this form still block duplicate slots. Requires connected Google Calendar.</p><p><label>Event title contains <input type="text" name="erm[appointment_event_keyword]" value="'.esc_attr($s['appointment_event_keyword']??'meeting').'" placeholder="meeting"></label></p>';echo '<p>Configure which weekdays accept appointment requests. Times use the WordPress site timezone. The system also blocks duplicate requested slots for this form; Google Calendar conflicts are not checked automatically.</p><p><label>Appointment length (minutes) <select name="erm[appointment_duration]">';foreach([15,30,45,60,90,120] as $d)echo '<option value="'.$d.'" '.selected($s['appointment_duration']??30,$d,false).'>'.$d.'</option>';echo '</select></label> <label>Start-time interval (minutes) <select name="erm[appointment_interval]">';foreach([15,30,45,60,90,120] as $interval)echo '<option value="'.$interval.'" '.selected($s['appointment_interval']??($s['appointment_duration']??30),$interval,false).'>'.$interval.'</option>';echo '</select></label> <label>Book up to (days ahead) <input type="number" min="1" max="365" name="erm[appointment_days]" value="'.esc_attr($s['appointment_days']??14).'"></label></p><p><label>Minimum advance notice (hours) <input type="number" name="erm[appointment_notice]" min="0" max="720" value="'.esc_attr($s['appointment_notice']??0).'"></label> <label>Preparation buffer (minutes) <input type="number" name="erm[appointment_buffer_before]" min="0" max="240" value="'.esc_attr($s['appointment_buffer_before']??0).'"></label> <label>Cleanup buffer (minutes) <input type="number" name="erm[appointment_buffer_after]" min="0" max="240" value="'.esc_attr($s['appointment_buffer_after']??0).'"></label></p><p><label>Blocked dates (one YYYY-MM-DD per line)<br><textarea name="erm[appointment_blocked]" rows="3" style="width:100%">'.esc_textarea($s['appointment_blocked']??'').'</textarea></label></p><p><label>Additional availability windows (one per line: weekday number 0=Sunday through 6=Saturday, HH:MM-HH:MM; e.g. 1 13:00-16:00)<br><textarea name="erm[appointment_extra_windows]" rows="3" style="width:100%">'.esc_textarea($s['appointment_extra_windows']??'').'</textarea></label></p><table class="widefat striped"><thead><tr><th>Day</th><th>Accept requests</th><th>From</th><th>Until</th></tr></thead><tbody>';foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day=>$label){$hours=$s['appointment_hours'][$day]??null;echo '<tr><td>'.esc_html($label).'</td><td><input type="checkbox" name="erm[appointment_enabled]['.$day.']" value="1" '.checked(is_array($hours),true,false).'></td><td><input type="time" name="erm[appointment_start]['.$day.']" value="'.esc_attr($hours[0]??'09:00').'"></td><td><input type="time" name="erm[appointment_end]['.$day.']" value="'.esc_attr($hours[1]??'17:00').'"></td></tr>';}echo '</tbody></table><p class="description">Google Calendar connections and multiple availability calendars are configured globally under Settings → Appointment Integrations.</p></div><h3>Products, Tickets & Service Levels</h3><p>Add individual offerings with a title, description, image or icon, and price. Drag products to reorder them, or use Move Up/Down buttons. Prices are used to calculate the cart total.</p><div id="erm-products">';foreach($s['products'] as $product)self::product_row($product);echo '</div><p><button type="button" class="button button-secondary" id="erm-add-product">+ Add Product / Level</button></p><h3>Appearance & Notifications</h3>';self::setting('font','Font family','text',$s);self::setting('background','Background hex color','text',$s);self::setting('text_color','Text hex color','text',$s);self::setting('background_image','Form background image URL','text',$s);echo '<p><button type="button" class="button erm-bg-media">Choose Background from Media Library</button></p>';self::setting('background_opacity','Background image opacity (0–100%)','number',$s);echo '<p class="description">0 hides the image; 100 shows it fully. The background color remains behind the image.</p>';self::setting('label_bg','Title and field-label highlight color','color',$s);self::setting('label_bg_opacity','Highlight opacity (0–100%)','number',$s);self::setting('label_color','Field label font color','color',$s);self::setting('heading_color','Heading font color','color',$s);echo '<p class="description">Use the color picker to select exact colors. Labels and headings get a translucent highlight for readability over photos.</p>';echo '<h3>Email Notifications</h3><p>Use the Visual tab to format messages with headings, links, lists and images. Available placeholders: <code>{form_title}</code>, <code>{entry_id}</code>, <code>{fields}</code>, <code>{appointment}</code>, <code>{items}</code>, <code>{total}</code>, <code>{payment}</code>.</p>';self::setting('admin_subject','Admin email subject ({form_title}, {entry_id})','text',$s);self::setting('admin_template','Admin email template ({fields}, {appointment}, {items}, {total}, {payment})','textarea',$s);self::setting('customer_subject','Customer email subject','text',$s);self::setting('customer_template','Customer email message (leave blank for original receipt)','textarea',$s);self::setting('intro','Introductory text','textarea',$s);self::setting('hero','Hero image URL (Media Library URL or external HTTPS URL)','text',$s);self::setting('accent','Accent color (hex)','text',$s);self::setting('organizer_email','Organizer notification email','email',$s);self::setting('ack','Acknowledgment message','textarea',$s);echo '<p><label><input type="checkbox" name="erm[receipt]" value="1" '.checked(!empty($s['receipt']),true,false).'> Email the registrant a copy / receipt</label></p>';
 echo '<h3>Payment</h3><p><label>Payment requirement <select name="erm[payment_mode]">';foreach(['none'=>'Free / No payment','optional'=>'Optional','required'=>'Required'] as $v=>$label)echo '<option value="'.esc_attr($v).'" '.selected($s['payment_mode'],$v,false).'>'.esc_html($label).'</option>';echo '</select></label></p>';self::setting('amount','Registration amount (USD, 0 for optional amount)','number',$s);echo '<div class="fcrc-registration-levels"><h4>Registration pricing levels</h4><p>Offer different registration rates based on the selected area, category, or level. Visitors choose exactly one level. If no levels are added, the single registration amount above is used.</p><div id="fcrc-level-rows">';
 foreach((array)($s['registration_levels']??[]) as $level)self::registration_level_row($level);
 echo '</div><p><button type="button" class="button" id="fcrc-add-level">+ Add registration level</button></p></div>';echo '<p>Enabled payment methods:</p>';foreach(['paypal'=>'PayPal','venmo'=>'Venmo','zelle'=>'Zelle','cashapp'=>'Cash App','mail'=>'Mail a check','cash'=>'Pay in person'] as $k=>$label)echo '<label style="display:inline-block;margin-right:18px"><input type="checkbox" name="erm[methods][]" value="'.esc_attr($k).'" '.checked(in_array($k,$s['methods'],true),true,false).'> '.esc_html($label).'</label>';echo '<p class="description"><strong>Payment account information is managed centrally.</strong> Select the payment methods to offer on this form. <a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-payment-accounts')).'">Manage Payment Accounts</a></p>';
 echo '<div class="fcrc-shipping-config"><h3>Shopping cart delivery & shipping</h3><p>Offer local pickup, shipping, or both. Shipping rates are fixed rules based on destination country and postal-code prefix. These are your estimates, not live carrier quotes.</p>';
 echo '<p><label><input type="checkbox" name="erm[fulfillment_pickup]" value="1" '.checked(!empty($s['fulfillment_pickup']),true,false).'> Allow pickup</label> &nbsp; <label><input type="checkbox" name="erm[fulfillment_shipping]" value="1" '.checked(!empty($s['fulfillment_shipping']),true,false).'> Allow shipping</label></p>';
 self::setting('shipping_default','Default shipping cost (USD; fallback if no rule matches)','number',$s);
 echo '<p><label><input type="checkbox" name="erm[shipping_live]" value="1" '.checked(!empty($s['shipping_live']),true,false).'> Use live Shippo carrier rates at checkout (requires configured Shippo account). When enabled, customers must select a live rate before placing an order.</label></p>';echo '<h4>Shipping zones</h4><p>Use a two-letter country code (US, CA, GB) and optional postal/ZIP prefix (e.g. 70 for Louisiana ZIP codes). More specific prefixes win. Leave country blank to match any country.</p><div id="fcrc-shipping-rows">';
 foreach((array)($s['shipping_rules']??[]) as $rule)echo '<div class="fcrc-shipping-row"><label>Country <input name="erm_shipping[country][]" maxlength="2" placeholder="US" value="'.esc_attr($rule['country']??'').'"></label><label>ZIP / postal prefix <input name="erm_shipping[prefix][]" maxlength="12" value="'.esc_attr($rule['prefix']??'').'"></label><label>Shipping cost ($) <input type="number" name="erm_shipping[price][]" min="0" max="100000" step="0.01" value="'.esc_attr($rule['price']??0).'"></label><button type="button" class="button-link-delete fcrc-remove-shipping">Remove</button></div>';
 echo '</div><button type="button" class="button" id="fcrc-add-shipping">+ Add shipping zone</button></div>';
 echo '<h3>QR Tickets</h3><p>Issue one unique scannable ticket per selected ticket quantity. Ticket links are included in the attendee receipt email. Ticket validation and check-in require administrator access. QR images are generated locally in the visitor’s browser; the encoded value is an opaque ticket URL, not attendee details.</p><p><label><input type="checkbox" name="erm[tickets_enabled]" value="1" '.checked(!empty($s['tickets_enabled']),true,false).'> Enable individual QR tickets for this form</label></p>';echo '<h3>Uploads</h3>';self::setting('upload_mb','Maximum upload size in MB (1–10)','number',$s);echo '<p>Allowed extensions: JPG, JPEG, PNG, WEBP, PDF. Uploaded files are stored privately in the Media Library as unattached files but their URLs may be accessible to anyone with the link. Do not collect sensitive identity documents.</p>';
 echo '<p><strong>Public listing:</strong> Only fields checked “Public” above appear in the optional public list shortcode. Obtain consent before publishing registrant information.</p>';
 echo <<<'JS'
<script>
(function(){
 const table=document.querySelector('#erm-fields tbody');if(!table)return;
 const types=['text','email','tel','number','textarea','select','radio','checkbox','date','file'];
 function row(f={}){const tr=document.createElement('tr');tr.innerHTML='<td><input class="erm-id" name="erm_fields[id][]" style="width:110px"></td><td><input class="erm-label" name="erm_fields[label][]" style="width:150px"></td><td><select class="erm-type" name="erm_fields[type][]"></select></td><td><input class="erm-options" name="erm_fields[options][]" style="width:150px"></td><td><input class="erm-required" type="checkbox" name="erm_fields[required][]" value="1"></td><td><input class="erm-public" type="checkbox" name="erm_fields[public][]" value="1"></td><td><select class="erm-width" name="erm_fields[width][]"><option value="12">Full</option><option value="6">Half</option><option value="4">Third</option></select></td><td><button type="button" class="button erm-up">↑</button><button type="button" class="button erm-down">↓</button></td><td><button type="button" class="button erm-remove">Remove</button></td>';
 const select=tr.querySelector('select');types.forEach(t=>select.add(new Option(t,t)));tr.querySelector('.erm-id').value=f.id||'';tr.querySelector('.erm-label').value=f.label||'';tr.querySelector('.erm-options').value=f.options||'';select.value=f.type||'text';tr.querySelector('.erm-width').value=String(f.width||12);tr.querySelector('.erm-required').checked=!!f.required;tr.querySelector('.erm-public').checked=!!f.public;table.appendChild(tr);sync();}
 function sync(){[...table.children].forEach((tr,i)=>{tr.querySelector('.erm-required').name='erm_fields[required]['+i+']';tr.querySelector('.erm-public').name='erm_fields[public]['+i+']';});}
 document.querySelector('#erm-add').onclick=()=>row({});document.querySelector('#erm-preset').onclick=()=>{const defaults=[['name','Full Name','text',true],['business','Business / Organization','text',false],['address','Mailing Address','textarea',false],['phone','Phone Number','tel',false],['email','Email Address','email',true],['products','Products Sold','textarea',false]];const ids=[...table.querySelectorAll('.erm-id')].map(e=>e.value);defaults.forEach(d=>{if(!ids.includes(d[0]))row({id:d[0],label:d[1],type:d[2],required:d[3]});});};
 table.onclick=e=>{const tr=e.target.closest('tr');if(!tr)return;if(e.target.matches('.erm-remove'))tr.remove();if(e.target.matches('.erm-up')&&tr.previousElementSibling)table.insertBefore(tr,tr.previousElementSibling);if(e.target.matches('.erm-down')&&tr.nextElementSibling)table.insertBefore(tr.nextElementSibling,tr);sync();};
 const products=document.querySelector('#erm-products');const add=document.querySelector('#erm-add-product');
 if(add&&products){add.addEventListener('click',()=>{const box=document.createElement('div');box.className='erm-product';box.style='border:1px solid #ccd0d4;padding:14px;margin:12px 0;background:#fff';box.innerHTML='<p><label><strong>Title</strong><br><input style="width:100%" name="erm_products[name][]"></label></p><p><label><strong>Description</strong><br><textarea style="width:100%" rows="3" name="erm_products[description][]"></textarea></label></p><p><label><strong>Image URL</strong><br><input style="width:80%" class="erm-product-image" name="erm_products[image][]"></label> <button type="button" class="button erm-media">Media Library</button></p><p><label><strong>Icon / Emoji</strong><br><input name="erm_products[icon][]"></label> &nbsp; <label><strong>Price (USD)</strong> <input type="number" min="0" step="0.01" name="erm_products[price][]" value="0" style="width:120px"></label></p><p><button type="button" class="button erm-product-up">↑ Move Up</button> <button type="button" class="button erm-product-down">↓ Move Down</button> <button type="button" class="button erm-product-remove">Remove</button></p>';products.appendChild(box);});products.addEventListener('click',e=>{const card=e.target.closest('.erm-product');if(!card)return;if(e.target.matches('.erm-product-remove'))card.remove();if(e.target.matches('.erm-product-up')&&card.previousElementSibling)products.insertBefore(card,card.previousElementSibling);if(e.target.matches('.erm-product-down')&&card.nextElementSibling)products.insertBefore(card.nextElementSibling,card);if(e.target.matches('.erm-media')&&window.wp&&wp.media){const frame=wp.media({title:'Choose product image',multiple:false,library:{type:'image'}});frame.on('select',()=>{const selected=frame.state().get('selection').first().toJSON();card.querySelector('.erm-product-image').value=selected.url;});frame.open();}});}
 const form=table.closest('form');if(form)form.addEventListener('submit',()=>{sync();});
})();
</script>
JS;
 }
 static function product_row($p){echo '<div class="erm-product" style="border:1px solid #ccd0d4;padding:14px;margin:12px 0;background:#fff"><p><label><strong>Title</strong><br><input style="width:100%" name="erm_products[name][]" value="'.esc_attr($p['name']??'').'"></label></p><p><label><strong>Description</strong><br><textarea style="width:100%" rows="3" name="erm_products[description][]">'.esc_textarea($p['description']??'').'</textarea></label></p><p><label><strong>Image URL</strong><br><input style="width:80%" class="erm-product-image" name="erm_products[image][]" value="'.esc_attr($p['image']??'').'"></label> <button type="button" class="button erm-media">Media Library</button></p><p><label><strong>Icon / Emoji (when no image)</strong><br><input name="erm_products[icon][]" value="'.esc_attr($p['icon']??'').'"></label> &nbsp; <label><strong>Price (USD)</strong> <input type="number" min="0" step="0.01" name="erm_products[price][]" value="'.esc_attr($p['price']??0).'" style="width:120px"></label></p><p><button type="button" class="button erm-product-up">↑ Move Up</button> <button type="button" class="button erm-product-down">↓ Move Down</button> <button type="button" class="button erm-product-remove">Remove</button></p></div>';}
 static function field_row($f,$public){echo '<tr>';foreach(['id','label'] as $k)echo '<td><input class="erm-'.esc_attr($k).'" name="erm_fields['.esc_attr($k).'][]" value="'.esc_attr($f[$k]??''). '" style="width:130px"></td>';echo '<td><select name="erm_fields[type][]">';foreach(['text','email','tel','number','textarea','select','radio','checkbox','date','file'] as $t)echo '<option '.selected($f['type']??'text',$t,false).'>'.esc_html($t).'</option>';echo '</select></td><td><input name="erm_fields[options][]" value="'.esc_attr($f['options']??''). '" style="width:140px"></td>';static $i=0;echo '<td><input type="checkbox" name="erm_fields[required]['.$i.']" value="1" '.checked(!empty($f['required']),true,false).'></td><td><input type="checkbox" name="erm_fields[public]['.$i.']" value="1" '.checked($public,true,false).'></td><td><select class="erm-width" name="erm_fields[width][]"><option value="12">Full</option><option value="6">Half</option><option value="4">Third</option></select></td><td><button type="button" class="button erm-up">↑</button><button type="button" class="button erm-down">↓</button></td><td><button type="button" class="button erm-remove">Remove</button></td></tr>';$i++;}
 static function setting($k,$label,$type,$s){$v=$s[$k]??'';echo '<p><label><strong>'.esc_html($label).'</strong><br>';if(in_array($k,['accent','background','text_color','label_bg','label_color','heading_color'],true)){
  $hex=sanitize_hex_color($v)?:'#ffffff';
  echo '<span class="fcrc-color-control" data-color-key="'.esc_attr($k).'"><input class="fcrc-color-hex" type="text" name="erm['.esc_attr($k).']" value="'.esc_attr($hex).'" maxlength="7" spellcheck="false" aria-label="'.esc_attr($label).' hex value"><button type="button" class="button fcrc-eyedropper" title="Pick a color from the screen">Pick from screen</button><input class="fcrc-color-map" type="color" value="'.esc_attr($hex).'" aria-label="'.esc_attr($label).' color map"></span><small class="description">Use the color map, type a hex value, or pick a color from your screen (supported browsers).</small>';
 }elseif(in_array($k,['admin_template','customer_template'],true)){echo '</label></p>';wp_editor($v,'erm_email_'.$k,['textarea_name'=>'erm['.$k.']','textarea_rows'=>10,'media_buttons'=>true,'teeny'=>false,'quicktags'=>true]);echo '<p class="description">Switch between Visual and Text to edit your email. Placeholders are replaced when sent.</p>';return;}elseif($type==='textarea')echo '<textarea name="erm['.esc_attr($k).']" rows="3" style="width:100%">'.esc_textarea($v).'</textarea>';else echo '<input type="'.esc_attr($type).'" name="erm['.esc_attr($k).']" value="'.esc_attr($v).'" style="width:100%" '.($type==='number'?'step="any"':'').'>';echo '</label></p>';}
 static function registration_level_row($level){
  echo '<div class="fcrc-level-row"><label>Level / Area <input type="text" name="erm_levels[name][]" maxlength="120" required value="'.esc_attr($level['name']??'').'" placeholder="e.g. Area A"></label> <label>Price ($) <input type="number" min="0" step="0.01" max="10000000" name="erm_levels[price][]" value="'.esc_attr($level['price']??0).'"></label> <button type="button" class="button-link-delete fcrc-remove-level">Remove</button></div>';
 }
 static function save_event($id){if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!isset($_POST['erm_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['erm_nonce'])),'erm_save')||!current_user_can('edit_post',$id)||wp_is_post_revision($id))return;$raw=isset($_POST['erm'])&&is_array($_POST['erm'])?wp_unslash($_POST['erm']):[];$old=self::settings($id);$s=self::defaults();foreach(['intro','ack','admin_subject','customer_subject'] as $k)$s[$k]=sanitize_textarea_field($raw[$k]??'');foreach(['admin_template','customer_template'] as $k)$s[$k]=wp_kses_post($raw[$k]??'');foreach(['hero','background_image'] as $k)$s[$k]=esc_url_raw($raw[$k]??'');$s['calendar_url']=$old['calendar_url']??'';foreach(self::payment_account_keys() as $k)$s[$k]=$old[$k]??'';foreach(['accent','background','text_color','label_bg','label_color','heading_color'] as $color)$s[$color]=sanitize_hex_color($raw[$color]??'')?:$s[$color];foreach(['background_opacity','label_bg_opacity'] as $k)$s[$k]=max(0,min(100,(int)($raw[$k]??$s[$k])));$s['font']=preg_replace('/[^a-zA-Z0-9 ,\-]/','',substr($raw['font']??'inherit',0,90));$s['appointment_modes']=array_values(array_intersect(['studio','travel','online'],array_map('sanitize_key',(array)($raw['appointment_modes']??[]))));if(!$s['appointment_modes'])$s['appointment_modes']=['studio'];
 $s['appointment_studio_address']=sanitize_text_field($raw['appointment_studio_address']??'');$s['appointment_travel_note']=sanitize_textarea_field($raw['appointment_travel_note']??'');
 $s['appointment_online_platform']=in_array($raw['appointment_online_platform']??'', ['zoom','teams','discord','slack','freeconferencecall','other'],true)?$raw['appointment_online_platform']:'zoom';
 $s['appointment_online_url']=esc_url_raw($raw['appointment_online_url']??'');foreach(['zoom_account_id','zoom_client_id','zoom_user'] as $k)$s[$k]=sanitize_text_field($raw[$k]??'');
 // API credentials are administrator-only and never cleared by an editor saving a form.
 foreach(['zoom_client_secret','teams_client_secret','discord_bot_token'] as $key){
  $s[$key]=$old[$key]??'';
  if(current_user_can('manage_options')&&isset($raw[$key])&&is_scalar($raw[$key])&&trim((string)$raw[$key])!=='')$s[$key]=sanitize_text_field($raw[$key]);
 }
 // Editors may configure ordinary form fields, but not change meeting integration identity.
 if(!current_user_can('manage_options')){
  foreach(['zoom_account_id','zoom_client_id','zoom_user','teams_tenant_id','teams_client_id','teams_user_id','discord_channel_id'] as $key)$s[$key]=$old[$key]??'';
 }
 $s['google_calendar_id']=sanitize_text_field($old['google_calendar_id']??'primary');$s['appointment_event_only']=!empty($raw['appointment_event_only']);$s['appointment_event_keyword']=substr(sanitize_text_field($raw['appointment_event_keyword']??'meeting'),0,100);$s['appointment_duration']=in_array((int)($raw['appointment_duration']??30),[15,30,45,60,90,120],true)?(int)$raw['appointment_duration']:30;$s['appointment_interval']=in_array((int)($raw['appointment_interval']??$s['appointment_duration']),[15,30,45,60,90,120],true)?(int)($raw['appointment_interval']??$s['appointment_duration']):$s['appointment_duration'];$s['appointment_days']=max(1,min(365,(int)($raw['appointment_days']??14)));$url=esc_url_raw(trim((string)($raw['appointment_ical_url']??'')));$s['appointment_ical_url']=(strpos($url,'https://calendar.google.com/calendar/ical/')===0&&substr($url,-4)==='.ics')?$url:'';foreach(['appointment_notice'=>720,'appointment_buffer_before'=>240,'appointment_buffer_after'=>240] as $key=>$max)$s[$key]=max(0,min($max,(int)($raw[$key]??0)));$blocked=preg_split('/\r?\n/',(string)($raw['appointment_blocked']??''));$s['appointment_blocked']=implode("\n",array_slice(array_unique(array_filter(array_map(function($line){$line=trim($line);return preg_match('/^\d{4}-\d{2}-\d{2}$/',$line)?$line:'';},$blocked))),0,366));$extra=preg_split('/\r?\n/',(string)($raw['appointment_extra_windows']??''));$valid=[];foreach($extra as $line){$line=trim($line);if(preg_match('/^([0-6])\s+([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/',$line,$m)&&$m[2].':'.$m[3]<$m[4].':'.$m[5])$valid[]=$line;}$s['appointment_extra_windows']=implode("\n",array_slice($valid,0,30));$s['appointment_hours']=[];foreach(range(0,6) as $weekday){if(empty($raw['appointment_enabled'][$weekday]))continue;$start=$raw['appointment_start'][$weekday]??'09:00';$end=$raw['appointment_end'][$weekday]??'17:00';if(preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/',$start)&&preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/',$end)&&$start<$end)$s['appointment_hours'][$weekday]=[$start,$end];}$s['form_type']=in_array($raw['form_type']??'',['registration','contact','appointment','shop'],true)?$raw['form_type']:'registration';$s['products']=[];$pr=isset($_POST['erm_products'])&&is_array($_POST['erm_products'])?wp_unslash($_POST['erm_products']):[];foreach(array_slice((array)($pr['name']??[]),0,50,true) as $i=>$name){$name=sanitize_text_field($name);if($name==='')continue;$s['products'][]=['name'=>$name,'description'=>sanitize_textarea_field($pr['description'][$i]??''),'image'=>esc_url_raw($pr['image'][$i]??''),'icon'=>sanitize_text_field($pr['icon'][$i]??''),'price'=>round(max(0,(float)($pr['price'][$i]??0)),2)];}$s['accent']=sanitize_hex_color($raw['accent']??'')?:'#603f91';$s['organizer_email']=sanitize_email($raw['organizer_email']??'');$s['receipt']=empty($raw['receipt'])?0:1;$s['tickets_enabled']=empty($raw['tickets_enabled'])?0:1;$s['payment_mode']=in_array($raw['payment_mode']??'', ['none','optional','required'],true)?$raw['payment_mode']:'none';$s['amount']=max(0,(float)($raw['amount']??0));$s['shipping_live']=!empty($raw['shipping_live'])?1:0;$s['fulfillment_pickup']=!empty($raw['fulfillment_pickup'])?1:0;$s['fulfillment_shipping']=!empty($raw['fulfillment_shipping'])?1:0;if(!$s['fulfillment_pickup']&&!$s['fulfillment_shipping'])$s['fulfillment_pickup']=1;
 $s['shipping_default']=round(min(100000,max(0,(float)($raw['shipping_default']??0))),2);
 $s['shipping_rules']=[];$rules=isset($_POST['erm_shipping'])&&is_array($_POST['erm_shipping'])?wp_unslash($_POST['erm_shipping']):[];
 foreach(array_slice((array)($rules['country']??[]),0,100,true) as $i=>$country){$country=strtoupper(preg_replace('/[^A-Za-z]/','',(string)$country));$prefix=strtoupper(preg_replace('/[^A-Za-z0-9]/','',(string)($rules['prefix'][$i]??'')));if(strlen($country)!==0&&strlen($country)!==2)continue;$s['shipping_rules'][]=['country'=>$country,'prefix'=>substr($prefix,0,12),'price'=>round(min(100000,max(0,(float)($rules['price'][$i]??0))),2)];}
 $s['registration_levels']=[];$levels=isset($_POST['erm_levels'])&&is_array($_POST['erm_levels'])?wp_unslash($_POST['erm_levels']):[];foreach(array_slice((array)($levels['name']??[]),0,50,true) as $i=>$label){$label=substr(sanitize_text_field($label),0,120);if($label==='')continue;$s['registration_levels'][]=['name'=>$label,'price'=>round(min(10000000,max(0,(float)($levels['price'][$i]??0))),2)];}$s['upload_mb']=max(1,min(10,(int)($raw['upload_mb']??5)));$s['methods']=array_values(array_intersect(['paypal','venmo','zelle','cashapp','mail','cash'],array_map('sanitize_key',(array)($raw['methods']??[]))));$f=isset($_POST['erm_fields'])&&is_array($_POST['erm_fields'])?wp_unslash($_POST['erm_fields']):[];$s['fields']=[];$s['public_fields']=[];$ids=[];foreach((array)($f['id']??[]) as $i=>$raw_id){$fid=sanitize_key($raw_id);if(!$fid||isset($ids[$fid]))continue;$ids[$fid]=true;$type=sanitize_key($f['type'][$i]??'text');if(!in_array($type,['text','email','tel','number','textarea','select','radio','checkbox','date','file'],true))$type='text';$s['fields'][]=['id'=>$fid,'label'=>sanitize_text_field($f['label'][$i]??$fid),'type'=>$type,'options'=>sanitize_text_field($f['options'][$i]??''),'required'=>!empty($f['required'][$i]),'width'=>in_array((int)($f['width'][$i]??12),[4,6,12],true)?(int)$f['width'][$i]:12];if(!empty($f['public'][$i])&&$type!=='file'&&$fid!=='email'&&$fid!=='phone'&&$fid!=='address')$s['public_fields'][]=$fid;}update_post_meta($id,'_erm_settings',$s);}
 static function rgba($hex,$opacity){$hex=ltrim(sanitize_hex_color($hex)?:'#ffffff','#');return 'rgba('.hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2)).','.max(0,min(1,$opacity)).')';}
 static function styles(){wp_enqueue_script('fcrc-cart-frontend',plugins_url('assets/cart-frontend.js',__FILE__),[],self::VERSION,true);wp_enqueue_script('fcrc-shipping',plugins_url('assets/shipping.js',__FILE__),['fcrc-cart-frontend'],self::VERSION,true);wp_enqueue_script('fcrc-shipping-live',plugins_url('assets/shipping-live.js',__FILE__),['fcrc-shipping'],self::VERSION,true);wp_enqueue_style('erm-appointment-design',plugins_url('assets/appointment-design.css',__FILE__),[],self::VERSION);wp_enqueue_script('erm-appointment-slots',plugins_url('assets/appointment-slots.js',__FILE__),[],self::VERSION,true);wp_enqueue_script('erm-appointment-location',plugins_url('assets/appointment-location.js',__FILE__),[],self::VERSION,true);wp_register_style('erm-inline',false,[],self::VERSION);wp_enqueue_style('erm-inline');wp_add_inline_style('erm-inline','.erm-form{max-width:780px;margin:24px auto;padding:24px;border:1px solid #ddd;background:#fff;color:#222}.erm-form{position:relative;isolation:isolate}.erm-form::before{content:"";position:absolute;inset:0;background-image:var(--erm-photo);background-size:cover;background-position:center;opacity:var(--erm-photo-opacity);pointer-events:none;z-index:-1}.erm-form h2,.erm-form h3,.erm-form legend,.erm-form label{background:var(--erm-highlight);box-decoration-break:clone;-webkit-box-decoration-break:clone;padding:4px 7px;border-radius:4px}.erm-form h2,.erm-form h3,.erm-form legend{color:var(--erm-heading-color)}.erm-form label{color:var(--erm-label-color)}.erm-form .erm-hero{width:100%;max-height:290px;object-fit:cover}.erm-form label{display:block;font-weight:600;margin:14px 0 5px}.erm-form input:not([type=radio]):not([type=checkbox]),.erm-form select,.erm-form textarea{box-sizing:border-box;width:100%;padding:10px;border:1px solid #aaa;border-radius:3px}.erm-form .erm-choice label{font-weight:400;margin:8px 0}.erm-form button{border:0;color:#fff;padding:13px 25px;cursor:pointer;background:var(--erm-accent)}.erm-form .erm-error{color:#a00}.erm-form fieldset{margin:20px 0;padding:15px;border:1px solid #ddd}.erm-form .erm-required{color:#a00}.erm-field-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:0 14px}.erm-field{grid-column:span 12;min-width:0}.erm-span-6{grid-column:span 6}.erm-span-4{grid-column:span 4}@media(max-width:650px){.erm-field{grid-column:span 12!important}}');}
 static function form($atts){$a=shortcode_atts(['id'=>0],$atts);$id=absint($a['id']);if(get_post_type($id)!=='erm_event'||get_post_status($id)!=='publish')return '<p>Registration form unavailable.</p>';$s=self::settings($id);$accent=esc_attr($s['accent']);$image=!empty($s['background_image'])?'url(&quot;'.esc_url($s['background_image']).'&quot;)':'none';$out='<div class="erm-form" style="--erm-accent:'.$accent.';--erm-photo:'.$image.';--erm-photo-opacity:'.esc_attr((float)$s['background_opacity']/100).';--erm-highlight:'.esc_attr(self::rgba($s['label_bg'],(float)$s['label_bg_opacity']/100)).';--erm-label-color:'.esc_attr($s['label_color']).';--erm-heading-color:'.esc_attr($s['heading_color']).';background:'.esc_attr($s['background']).';color:'.esc_attr($s['text_color']).';font-family:'.esc_attr($s['font']).'">';if($s['hero'])$out.='<img class="erm-hero" src="'.esc_url($s['hero']).'" alt="">';$out.='<h2>'.esc_html(get_the_title($id)).'</h2><p>'.nl2br(esc_html($s['intro'])).'</p>';
 if(isset($_GET['erm_result'],$_GET['erm_event'])&&absint($_GET['erm_event'])===$id){$result=sanitize_key(wp_unslash($_GET['erm_result']));if($result==='success'){ $out.='<div role="status"><h3>Registration received</h3><p>'.nl2br(esc_html($s['ack'])).'</p><p>If you selected an electronic payment option, use the payment link in your emailed receipt to complete payment. Your registration is not proof of payment.</p></div></div>'; return $out; }if($result==='error')$out.='<p class="erm-error">We could not submit your registration. Please check all required fields and try again.</p>';}
 if($s['form_type']==='appointment'&&!empty($s['calendar_url']))$out.='<p><a href="'.esc_url($s['calendar_url']).'" target="_blank" rel="noopener noreferrer">View available appointments ↗</a></p><p>Choose an available time, then request it below. This does not book the appointment.</p>'; $out.='<form action="'.esc_url(admin_url('admin-post.php')).'" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="erm_submit"><input type="hidden" name="event_id" value="'.$id.'">'.wp_nonce_field('erm_submit_'.$id,'erm_nonce',true,false).'<div style="display:none" aria-hidden="true"><label>Leave blank<input type="text" name="erm_website" tabindex="-1" autocomplete="off"></label></div>';
 $out.='<div class="erm-field-grid">';foreach($s['fields'] as $f){$fid=$f['id'];$label=esc_html($f['label']);$required=!empty($f['required']);$attr=$required?' required':'';$out.='<div class="erm-field erm-span-'.(int)($f['width']??12).'"><label for="erm_'.esc_attr($fid).'">'.$label.($required?' <span class="erm-required">*</span>':'').'</label>';$name='erm_data['.esc_attr($fid).']';$type=$f['type'];if($type==='textarea')$out.='<textarea id="erm_'.esc_attr($fid).'" name="'.$name.'"'.$attr.'></textarea>';elseif($type==='select'){$out.='<select id="erm_'.esc_attr($fid).'" name="'.$name.'"'.$attr.'><option value="">Choose...</option>';foreach(explode('|',$f['options']??'') as $option){$option=trim($option);if($option!=='')$out.='<option>'.esc_html($option).'</option>';}$out.='</select>';}elseif($type==='radio'||$type==='checkbox'){$out.='<div class="erm-choice">';foreach(explode('|',$f['options']??'') as $option){$option=trim($option);if($option==='')continue;$out.='<label><input type="'.$type.'" name="'.$name.($type==='checkbox'?'[]':'').'" value="'.esc_attr($option).'"'.($required&&$type==='radio'?' required':'').'> '.esc_html($option).'</label>';}$out.='</div>';}elseif($type==='file')$out.='<input id="erm_'.esc_attr($fid).'" type="file" name="erm_file_'.esc_attr($fid).'[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple'.$attr.'><small>Choose up to 10 images or PDFs (each file must meet the upload size limit).</small>';else $out.='<input id="erm_'.esc_attr($fid).'" type="'.esc_attr($type).'" name="'.$name.'"'.$attr.'>'; $out.='</div>';} $out.='</div>';
 if($s['form_type']==='appointment'){$modes=(array)($s['appointment_modes']??['studio']);$out.='<fieldset class="erm-appointment-mode"><legend>How would you like to meet?</legend>';
 foreach(['studio'=>'In studio / onsite','travel'=>'At my location','online'=>'Online meeting'] as $mode=>$label){$global=(array)get_option('erm_appointment_integrations',[]);$links=(array)($global['meeting_links']??[]);$allowed=(array)($global['formats']??[]);if($mode==='online'&&!array_filter($allowed,static function($service)use($links){return !empty($links[$service]);}))continue;if(in_array($mode,$modes,true))$out.='<label><input type="radio" name="erm_appointment_mode" value="'.esc_attr($mode).'" required '.(count($modes)===1?'checked':'').'> '.esc_html($label).'</label>';}
 $out.='<div class="erm-appointment-travel" hidden><label>Street address <input name="erm_appointment_street" maxlength="180"></label><label>City <input name="erm_appointment_city" maxlength="100"></label><label>State / province <input name="erm_appointment_region" maxlength="100"></label><label>ZIP / postal code <input name="erm_appointment_postcode" maxlength="20"></label></div>';
 if(in_array('studio',$modes,true)&&!empty($s['appointment_studio_address']))$out.='<p>Studio location: '.esc_html($s['appointment_studio_address']).'</p>';
 if(in_array('travel',$modes,true)&&!empty($s['appointment_travel_note']))$out.='<p>'.esc_html($s['appointment_travel_note']).'</p>';
 if(in_array('online',$modes,true)){
  $integrations=(array)get_option('erm_appointment_integrations',[]);
  $enabled=array_key_exists('formats',$integrations)?(array)$integrations['formats']:['zoom'];
  $names=['zoom'=>'Zoom','teams'=>'Microsoft Teams','discord'=>'Discord','slack'=>'Slack','freeconferencecall'=>'FreeConferenceCall.com','other'=>'Other'];
  $enabled=array_values(array_filter(array_intersect(array_keys($names),$enabled),static function($service)use($integrations){return !empty($integrations['meeting_links'][$service]);}));
  $out.='<div class="erm-online-meeting-options" hidden>';
  if($enabled){
   $out.='<label>Online meeting service <select name="erm_online_service" disabled>';
   foreach($enabled as $service)$out.='<option value="'.esc_attr($service).'">'.esc_html($names[$service]).'</option>';
   $out.='</select></label><p>Joining details will be provided in your appointment email.</p>';
  }else $out.='<p>Online meeting services have not been enabled by the administrator.</p>';
  $out.='</div>';
 }
 $out.='</fieldset>';
 $out.='<script>(function(){function update(){document.querySelectorAll(".erm-appointment-mode").forEach(function(field){var selected=field.querySelector("input[name=erm_appointment_mode]:checked");var online=field.querySelector(".erm-online-meeting-options");if(online){var show=!!selected&&selected.value==="online";online.hidden=!show;online.querySelectorAll("select").forEach(function(select){select.disabled=!show;});}var travel=field.querySelector(".erm-appointment-travel");if(travel)travel.hidden=!selected||selected.value!=="travel";});}document.addEventListener("change",function(e){if(e.target&&e.target.name==="erm_appointment_mode")update();});if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",update);else update();})();</script>';
 $out.='<fieldset class="erm-appointment-request"><legend>Choose your preferred date &amp; time</legend><p>Only times within the configured availability window can be requested. All times use '.esc_html(wp_timezone_string()).'.</p><label>Requested date <input type="date" name="erm_appointment_date" min="'.esc_attr(wp_date('Y-m-d')).'" max="'.esc_attr(wp_date('Y-m-d',current_time('timestamp')+DAY_IN_SECONDS*(int)$s['appointment_days'])).'" required></label><label>Requested time <select name="erm_appointment_time" required><option value="">Select a date first</option></select></label><p class="erm-slot-feedback" role="status"></p></fieldset>'; $out.='<script type="application/json" class="erm-appointment-settings">'.wp_json_encode(['event'=>$id,'nonce'=>wp_create_nonce('erm_slots_'.$id),'ajax'=>admin_url('admin-ajax.php')]).'</script>'; }if(!empty($s['products'])){$out.='<fieldset class="fcrc-cart-catalog"><legend>'.($s['form_type']==='shop'?'Shop products':'Tickets and products').'</legend>';foreach($s['products'] as $i=>$p){if($p['image'])$out.='<img src="'.esc_url($p['image']).'" alt="" style="max-width:100px;max-height:90px;object-fit:contain">';$out.='<label><strong>'.esc_html($p['name']).'</strong> — $'.esc_html(number_format($p['price'],2)).(!empty($p['description'])?'<br><small>'.nl2br(esc_html($p['description'])).'</small>':'').(empty($p['image'])&&!empty($p['icon'])?' '.esc_html($p['icon']):'').' <input type="number" min="0" max="100" value="0" name="erm_qty['.$i.']" style="max-width:100px" aria-label="Quantity"></label>';}$out.='</fieldset>';if($s['form_type']==='shop')$out.='<section class="fcrc-cart-summary" aria-live="polite"><h3>Your cart</h3><div class="fcrc-cart-lines">Choose products above to build your cart.</div><p><strong>Subtotal: <span class="fcrc-cart-total">$0.00</span></strong></p><p class="fcrc-cart-empty">Add at least one product to continue.</p></section>'; }if($s['form_type']==='shop'){
  $pickup=!empty($s['fulfillment_pickup']);$ship=!empty($s['fulfillment_shipping']);
  if($pickup||$ship){
   $out.='<fieldset class="fcrc-fulfillment" data-default="'.esc_attr((float)$s['shipping_default']).'" data-rules="'.esc_attr(wp_json_encode($s['shipping_rules'])).'" data-live="'.(!empty($s['shipping_live'])?'1':'0').'"><legend>How would you like to receive your order?</legend>';
   if($pickup)$out.='<label><input type="radio" name="erm_fulfillment" value="pickup" '.(!$ship?'checked':'').' required> Pickup (free)</label>';
   if($ship)$out.='<label><input type="radio" name="erm_fulfillment" value="shipping" '.(!$pickup?'checked':'').' required> Ship my order</label>';
   if($ship)$out.='<div class="fcrc-shipping-address" hidden><label>Country (2-letter code) <input name="erm_ship_country" maxlength="2" placeholder="US" value="US"></label><label>ZIP / postal code <input name="erm_ship_postcode" maxlength="20" autocomplete="postal-code"></label><label>Street address <input name="erm_ship_street" maxlength="180" autocomplete="shipping street-address"></label><label>City <input name="erm_ship_city" maxlength="100" autocomplete="shipping address-level2"></label><label>State / province <input name="erm_ship_region" maxlength="100" autocomplete="shipping address-level1"></label><p>Estimated shipping: <strong class="fcrc-shipping-estimate">$0.00</strong></p><p class="description">Estimate based on the destination country and postal code you enter. Carrier-calculated rates are not connected.</p></div>';
   if(!empty($s['shipping_live']))$out.='<div class="fcrc-shippo-live" data-form="'.absint($id).'" data-ajax="'.esc_url(admin_url('admin-ajax.php')).'" data-nonce="'.esc_attr(wp_create_nonce('erm_shippo_quote_'.$id)).'"><button class="button fcrc-shippo-quote-button" type="button">Get live shipping rates</button><p class="fcrc-shippo-feedback">Enter your delivery address, then get current carrier rates.</p><div class="fcrc-shippo-rates"></div></div>';
   $out.='</fieldset>';
  }
 }
 if($s['form_type']==='registration'&&!empty($s['registration_levels'])){$out.='<fieldset><legend>Registration level</legend><p>Select the area or registration level that applies to you.</p>';foreach($s['registration_levels'] as $i=>$level)$out.='<label><input type="radio" name="erm_registration_level" value="'.absint($i).'" required> '.esc_html($level['name']).' — $'.esc_html(number_format((float)$level['price'],2)).'</label>';$out.='</fieldset>';}
 if($s['payment_mode']!=='none'){$out.='<fieldset><legend>Payment '.($s['payment_mode']==='required'?'(required)':'(optional)').'</legend>';if(empty($s['registration_levels'])&&(float)$s['amount']>0)$out.='<p>Registration amount: $'.esc_html(number_format((float)$s['amount'],2)).'</p>';$labels=['paypal'=>'PayPal','venmo'=>'Venmo','zelle'=>'Zelle','cashapp'=>'Cash App','mail'=>'Mail a check','cash'=>'Pay in person'];if($s['payment_mode']==='optional')$out.='<label><input type="radio" name="erm_payment" value="none" checked> No payment at this time</label>';foreach($s['methods'] as $m)$out.='<label><input type="radio" name="erm_payment" value="'.esc_attr($m).'" '.($s['payment_mode']==='required'?'required':'').'> '.esc_html($labels[$m]??$m).'</label>';$out.='</fieldset>';}
 $out.='<p><button type="submit">Submit Registration</button></p></form></div>';return $out;}
 static function calendar_busy($s,$date){
  $url=(string)($s['appointment_ical_url']??'');
  if(!$url||strpos($url,'https://calendar.google.com/calendar/ical/')!==0||substr($url,-4)!=='.ics')return [];
  $cache='erm_ical_'.md5($url);
  $body=get_transient($cache);
  if($body===false){
   $response=wp_safe_remote_get($url,['timeout'=>8,'redirection'=>2,'limit_response_size'=>1024*1024]);
   if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)return [];
   $body=wp_remote_retrieve_body($response);set_transient($cache,$body,5*MINUTE_IN_SECONDS);
  }
  // Unfold iCalendar continuation lines; include all-day and timed busy events.
  $body=preg_replace("/\r?\n[ \t]/",'',$body);$busy=[];
  if(!preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s',$body,$events))return [];
  foreach($events[1] as $event){
   if(preg_match('/^TRANSP:TRANSPARENT$/mi',$event)||preg_match('/^STATUS:CANCELLED$/mi',$event))continue;
   if(!preg_match('/^DTSTART(?:;[^:\r\n]*)?:(\d{8}(?:T\d{6}Z?)?)/mi',$event,$a)||!preg_match('/^DTEND(?:;[^:\r\n]*)?:(\d{8}(?:T\d{6}Z?)?)/mi',$event,$b))continue;
   $parse=function($value)use($event){
    $utc=substr($value,-1)==='Z';$fmt=strpos($value,'T')!==false?'!Ymd\\THis':'!Ymd';
    $tz=$utc?new DateTimeZone('UTC'):wp_timezone();
    // TZID-qualified events use their declared timezone when available.
    if(!$utc&&preg_match('/^DTSTART;TZID=([^:;]+):/mi',$event,$m)){try{$tz=new DateTimeZone($m[1]);}catch(Exception $e){}}
    $dt=DateTimeImmutable::createFromFormat($fmt,rtrim($value,'Z'),$tz);return $dt?$dt->getTimestamp():null;
   };
   $start=$parse($a[1]);$end=$parse($b[1]);
   if($start!==null&&$end!==null&&$start<$end)$busy[]=[$start,$end];
  }
  return $busy;
 }
 static function slots_for($id,$date,$s){
  try{$day=new DateTimeImmutable($date.' 00:00:00',wp_timezone());}catch(Exception $e){return [];}
  if($day->format('Y-m-d')!==$date)return [];
  $today=new DateTimeImmutable('today',wp_timezone());$max=$today->modify('+'.max(1,(int)($s['appointment_days']??14)).' days');
  if($day<$today||$day>$max)return [];
  $blocked=preg_split('/\\r?\\n/',(string)($s['appointment_blocked']??''));
  if(in_array($date,array_map('trim',$blocked),true))return [];
  $weekday=(int)$day->format('w');$windows=[];
  $normal=$s['appointment_hours'][$weekday]??null;
  if(is_array($normal)&&count($normal)>=2)$windows[]=$normal;
  foreach(preg_split('/\\r?\\n/',(string)($s['appointment_extra_windows']??'')) as $line){
   if(preg_match('/^([0-6])\\s+([01]\\d|2[0-3]):([0-5]\\d)-([01]\\d|2[0-3]):([0-5]\\d)$/',trim($line),$m)&&(int)$m[1]===$weekday)$windows[]=[ $m[2].':'.$m[3],$m[4].':'.$m[5] ];
  }
  $event_only=!empty($s['appointment_event_only']);
  if($event_only){
   $matched=ERM_Google_Calendar::matching_event_windows($date,$s);
   if(ERM_Microsoft_Calendar::enabled()){
    $ms_matches=ERM_Microsoft_Calendar::ranges($date,trim((string)($s['appointment_event_keyword']??'meeting')));
    if(is_wp_error($ms_matches))return [];
    if(is_wp_error($matched))$matched=[];
    $matched=array_merge($matched,$ms_matches);
   }
   if(is_wp_error($matched)||!$matched)return [];
   $windows=[];
   foreach($matched as $range){
    $from=(new DateTimeImmutable('@'.$range[0]))->setTimezone(wp_timezone());
    $to=(new DateTimeImmutable('@'.$range[1]))->setTimezone(wp_timezone());
    $windows[]=[$from->format('H:i'),$to->format('H:i')];
   }
  }
  if(!$windows)return [];
  $duration=max(1,(int)($s['appointment_duration']??30));$interval=max(1,(int)($s['appointment_interval']??$duration));
  $before=max(0,(int)($s['appointment_buffer_before']??0));$after=max(0,(int)($s['appointment_buffer_after']??0));
  $now=new DateTimeImmutable('now',wp_timezone());$earliest=$now->modify('+'.max(0,(int)($s['appointment_notice']??0)).' hours');
  $busy=[];$entries=get_posts(['post_type'=>'erm_entry','post_status'=>'private','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>[['key'=>'_erm_event','value'=>$id],['key'=>'_erm_appointment','value'=>$date.' ','compare'=>'LIKE']]]);
  foreach($entries as $entry){
   if(!empty(self::$reschedule_entry)&&$entry===self::$reschedule_entry)continue;
   if(get_post_meta($entry,'_erm_registration_status',true)==='cancelled')continue;
   $value=(string)get_post_meta($entry,'_erm_appointment',true);
   if(strpos($value,$date.' ')!==0)continue;
   $taken=DateTimeImmutable::createFromFormat('!Y-m-d H:i',substr($value,0,16),wp_timezone());if(!$taken)continue;
   $stored=(int)get_post_meta($entry,'_erm_appointment_duration',true);
   $length=$stored>0?$stored:$duration;
   $busy[]=[$taken->getTimestamp()-$before*60,$taken->getTimestamp()+($length+$after)*60];
  }
  $external=$event_only?[]:ERM_Google_Calendar::busy($id,$date,$s);if(is_wp_error($external))return [];
  if(ERM_Microsoft_Calendar::enabled()&&!$event_only){$ms_busy=ERM_Microsoft_Calendar::ranges($date);if(is_wp_error($ms_busy))return [];foreach($ms_busy as $range)$external[]=$range;} // Google OAuth free-busy checks replace legacy single-ICS conflict checking.
  $slots=[];
  foreach($windows as $window){
   $start=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$window[0],wp_timezone());
   $end=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$window[1],wp_timezone());
   if(!$start||!$end||$start>=$end)continue;
   for($candidate=$start;$candidate->modify('+'.$duration.' minutes')<=$end;$candidate=$candidate->modify('+'.$interval.' minutes')){
    if($candidate<$earliest)continue;
    $begin=$candidate->getTimestamp()-$before*60;$finish=$candidate->getTimestamp()+($duration+$after)*60;
    $conflict=false;foreach($busy as $range){if($begin<$range[1]&&$finish>$range[0]){$conflict=true;break;}}
    if(!$conflict)foreach($external as $range){if($begin<$range[1]&&$finish>$range[0]){$conflict=true;break;}}
    if(!$conflict)$slots[]=$candidate->format('H:i');
   }
  }
  $slots=array_values(array_unique($slots));sort($slots);return $slots;
 }
 static function available_slots(){
  $id=absint($_GET['event']??0);$date=sanitize_text_field(wp_unslash($_GET['date']??''));$nonce=sanitize_text_field(wp_unslash($_GET['nonce']??''));
  if(get_post_type($id)!=='erm_event'||get_post_status($id)!=='publish'||!wp_verify_nonce($nonce,'erm_slots_'.$id)||!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date))wp_send_json_error('Invalid request',400);
  $ip=substr(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']??'')),0,100);
  $key='erm_slot_limit_'.hash('sha256',$id.'|'.$ip);
  $hits=(int)get_transient($key);
  if($hits>=90)wp_send_json_error('Too many availability requests. Please try again later.',429);
  set_transient($key,$hits+1,10*MINUTE_IN_SECONDS);
  $s=self::settings($id);
  if($s['form_type']!=='appointment')wp_send_json_error('This form does not offer appointments.',400);
  wp_send_json_success(['slots'=>self::slots_for($id,$date,$s)]);
 }
 static function shipping_quote($settings,$country,$postcode){
  $country=strtoupper(preg_replace('/[^A-Za-z]/','',$country));$postcode=strtoupper(preg_replace('/[^A-Za-z0-9]/','',$postcode));
  $best=-1;$amount=(float)($settings['shipping_default']??0);
  foreach((array)($settings['shipping_rules']??[]) as $rule){
   $c=strtoupper((string)($rule['country']??''));$prefix=strtoupper((string)($rule['prefix']??''));
   if($c!==''&&$c!==$country)continue;
   if($prefix!==''&&strpos($postcode,$prefix)!==0)continue;
   $score=strlen($prefix)*10+($c!==''?1:0);
   if($score>$best){$best=$score;$amount=(float)($rule['price']??0);}
  }
  return round(max(0,$amount),2);
 }
 static function submit(){ $id=absint($_POST['event_id']??0);$redirect=get_permalink($id);if(!$redirect)$redirect=home_url('/');$redirect=wp_get_referer()?:$redirect;$redirect=wp_validate_redirect($redirect,home_url('/'));$fail=function()use($redirect,$id){wp_safe_redirect(add_query_arg(['erm_result'=>'error','erm_event'=>$id],$redirect));exit;};if(get_post_type($id)!=='erm_event'||get_post_status($id)!=='publish'||!isset($_POST['erm_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['erm_nonce'])),'erm_submit_'.$id)||!empty($_POST['erm_website']))$fail();$s=self::settings($id);
 $ip=isset($_SERVER['REMOTE_ADDR'])?sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])):'';
 $rate_key='erm_rate_'.hash('sha256',$id.'|'.$ip);
 $attempts=(int)get_transient($rate_key);
 if($attempts>=8)$fail();
 set_transient($rate_key,$attempts+1,10*MINUTE_IN_SECONDS);
 $raw=isset($_POST['erm_data'])&&is_array($_POST['erm_data'])?wp_unslash($_POST['erm_data']):[];$data=[];$files=[];$email='';foreach($s['fields'] as $f){$key=$f['id'];$type=$f['type'];if($type==='file'){
  $upload=$_FILES['erm_file_'.$key]??null;
  if($upload!==null&&!is_array($upload))$fail();
  $uploads=[];
  if($upload){
   if(is_array($upload['name']??null)){
    $count=count($upload['name']);
    if($count>10||!isset($upload['error'],$upload['size'],$upload['tmp_name'])||!is_array($upload['error'])||!is_array($upload['size'])||!is_array($upload['tmp_name'])||count($upload['error'])!==$count||count($upload['size'])!==$count||count($upload['tmp_name'])!==$count)$fail();
    for($i=0;$i<$count;$i++){
     if(!isset($upload['name'][$i],$upload['error'][$i],$upload['size'][$i],$upload['tmp_name'][$i]))$fail();
     if((int)$upload['error'][$i]===UPLOAD_ERR_NO_FILE)continue;
     $uploads[]=['name'=>$upload['name'][$i],'type'=>$upload['type'][$i]??'','tmp_name'=>$upload['tmp_name'][$i],'error'=>$upload['error'][$i],'size'=>$upload['size'][$i]];
    }
   }else{
    if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$uploads[]=$upload;
   }
  }
  if(!empty($f['required'])&&!$uploads)$fail();
  if(count($uploads)>10)$fail();
  foreach($uploads as $item){
   if((int)$item['error']!==UPLOAD_ERR_OK||(int)$item['size']<=0||(int)$item['size']>$s['upload_mb']*1048576)$fail();
   $ext=strtolower(pathinfo((string)$item['name'],PATHINFO_EXTENSION));
   if(!in_array($ext,['jpg','jpeg','png','webp','pdf'],true))$fail();
   require_once ABSPATH.'wp-admin/includes/file.php';
   require_once ABSPATH.'wp-admin/includes/media.php';
   require_once ABSPATH.'wp-admin/includes/image.php';
   $file=wp_handle_upload($item,['test_form'=>false,'mimes'=>['jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf']]);
   if(isset($file['error']))$fail();
   $verified=wp_check_filetype_and_ext($file['file'],basename($file['file']));
   if(empty($verified['ext'])||empty($verified['type'])||!in_array($verified['ext'],['jpg','jpeg','png','webp','pdf'],true)){@unlink($file['file']);$fail();}
   $attachment=wp_insert_attachment(['post_mime_type'=>$file['type'],'post_title'=>sanitize_file_name($item['name']),'post_status'=>'inherit'],$file['file']);
   if(is_wp_error($attachment)||!$attachment){@unlink($file['file']);$fail();}
   wp_update_attachment_metadata($attachment,wp_generate_attachment_metadata($attachment,$file['file']));
   if(!isset($files[$key]))$files[$key]=[];
   $files[$key][]=(int)$attachment;
  }
  continue;}$v=$raw[$key]??'';if(is_array($v))$v=array_map('sanitize_text_field',$v);else $v=sanitize_textarea_field($v);if(!empty($f['required'])&&($v===''||$v===[]))$fail();if($type==='email'&&$v!==''&&!is_email($v))$fail();if(in_array($type,['select','radio','checkbox'],true)){$choices=array_map('trim',explode('|',$f['options']??''));foreach((array)$v as $choice)if($choice!==''&&!in_array($choice,$choices,true))$fail();}if($key==='email'||($type==='email'&&!$email))$email=is_array($v)?'':sanitize_email($v);$data[$key]=$v;}
 $appointment='';if($s['form_type']==='appointment'){$date=sanitize_text_field(wp_unslash($_POST['erm_appointment_date']??''));$time=sanitize_text_field(wp_unslash($_POST['erm_appointment_time']??''));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$time)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)))$fail();if(!in_array($time,self::slots_for($id,$date,$s),true))$fail();$appointment=$date.' '.$time;}$items=[];$total=0;if(!empty($s['products']))foreach($s['products'] as $i=>$p){$qty=filter_var($_POST['erm_qty'][$i]??0,FILTER_VALIDATE_INT);if($qty===false||$qty<0||$qty>100)$fail();if($qty){$items[]=['name'=>$p['name'],'description'=>$p['description']??'','qty'=>$qty,'price'=>$p['price']];$total+=$qty*$p['price'];}}$total=round($total,2);$registration_level=null;$registration_price=$s['form_type']==='registration'?(float)$s['amount']:0;if($s['form_type']==='registration'&&!empty($s['registration_levels'])){$level_id=filter_var($_POST['erm_registration_level']??null,FILTER_VALIDATE_INT);if($level_id===false||$level_id===null||!isset($s['registration_levels'][$level_id]))$fail();$registration_level=$s['registration_levels'][$level_id];$registration_price=(float)$registration_level['price'];}$shippo_rate=null;$shipping=0;$fulfillment='';$ship_address=[];
 if($s['form_type']==='shop'){
  $fulfillment=sanitize_key($_POST['erm_fulfillment']??'');
  if($fulfillment==='pickup'&&empty($s['fulfillment_pickup']))$fail();
  if($fulfillment==='shipping'){
   if(empty($s['fulfillment_shipping']))$fail();
   foreach(['country'=>2,'postcode'=>20,'street'=>180,'city'=>100,'region'=>100] as $key=>$limit)$ship_address[$key]=substr(sanitize_text_field(wp_unslash($_POST['erm_ship_'.$key]??'')),0,$limit);
   if(!preg_match('/^[A-Za-z]{2}$/',$ship_address['country'])||$ship_address['postcode']===''||$ship_address['street']===''||$ship_address['city']===''||$ship_address['region']==='')$fail();
   if(!empty($s['shipping_live'])){$shippo_rate=ERM_Shippo::selected_rate($id,$ship_address);if(is_wp_error($shippo_rate))$fail();$shipping=(float)$shippo_rate['amount'];}else $shipping=self::shipping_quote($s,$ship_address['country'],$ship_address['postcode']);
  }elseif($fulfillment!=='pickup')$fail();
 }
 $charged_total=round($total+$registration_price+$shipping,2);
 if($s['form_type']==='shop'&&$total<=0)$fail();$method=sanitize_key($_POST['erm_payment']??'none');if($s['payment_mode']==='none')$method='none';elseif($method!=='none'&&!in_array($method,$s['methods'],true))$fail();elseif($s['payment_mode']==='required'&&$method==='none')$fail();$appointment_mode='';$appointment_address=[];
 if($s['form_type']==='appointment'){
  $appointment_mode=sanitize_key($_POST['erm_appointment_mode']??'');
  if(!in_array($appointment_mode,(array)($s['appointment_modes']??['studio']),true))$fail();
  if($appointment_mode==='online'){
   $integrations=(array)get_option('erm_appointment_integrations',[]);
   $service=sanitize_key(wp_unslash($_POST['erm_online_service']??''));
   if(!in_array($service,(array)($integrations['formats']??[]),true)||empty($integrations['meeting_links'][$service]))$fail();
  }
  if($appointment_mode==='travel'){
   foreach(['street'=>180,'city'=>100,'region'=>100,'postcode'=>20] as $k=>$max)$appointment_address[$k]=substr(sanitize_text_field(wp_unslash($_POST['erm_appointment_'.$k]??'')),0,$max);
   if(in_array('',$appointment_address,true))$fail();
  }
 }
 $name=isset($data['name'])&&!is_array($data['name'])?$data['name']:'Registrant';$entry=wp_insert_post(['post_type'=>'erm_entry','post_status'=>'private','post_title'=>sanitize_text_field(get_the_title($id).' — '.$name.' — '.current_time('mysql'))],true);if(is_wp_error($entry))$fail();update_post_meta($entry,'_erm_event',$id);update_post_meta($entry,'_erm_appointment',$appointment);if($appointment_mode!==''){update_post_meta($entry,'_erm_appointment_mode',$appointment_mode);if($appointment_mode==='online')update_post_meta($entry,'_erm_online_service',$service);update_post_meta($entry,'_erm_appointment_address',$appointment_address);}if($appointment!=='')update_post_meta($entry,'_erm_appointment_duration',(int)$s['appointment_duration']);update_post_meta($entry,'_erm_items',$items);if($registration_level!==null)update_post_meta($entry,'_erm_registration_level',$registration_level);update_post_meta($entry,'_erm_data',$data);update_post_meta($entry,'_erm_files',$files);update_post_meta($entry,'_erm_method',$method);update_post_meta($entry,'_erm_payment_status',$method==='none'?'not_required':'pending');update_post_meta($entry,'_erm_registration_status','submitted');update_post_meta($entry,'_erm_amount',$charged_total);if($s['form_type']==='shop'){update_post_meta($entry,'_erm_fulfillment',$fulfillment);update_post_meta($entry,'_erm_shipping_cost',$shipping);update_post_meta($entry,'_erm_shipping_address',$ship_address);if($shippo_rate!==null)update_post_meta($entry,'_erm_shippo_rate',$shippo_rate);}$tickets=[];if(!empty($s['tickets_enabled'])){foreach($items as $item){for($n=0;$n<(int)$item['qty'];$n++){if(count($tickets)>=200)break 2;$tickets[]=['code'=>strtoupper(bin2hex(random_bytes(12))),'label'=>$item['name'],'checked_in'=>false,'checked_at'=>''];}}if(!$tickets && empty($s['products']))$tickets[]=['code'=>strtoupper(bin2hex(random_bytes(12))),'label'=>get_the_title($id),'checked_in'=>false,'checked_at'=>''];update_post_meta($entry,'_erm_tickets',$tickets);}$body="Event: ".get_the_title($id)."\nRegistration #".$entry."\n\n";foreach($s['fields'] as $f){$v=$data[$f['id']]??'';if(isset($files[$f['id']]))$v='File uploaded (view in admin)';$body.=$f['label'].': '.(is_array($v)?implode(', ',$v):$v)."\n";}if($fulfillment!==''){$body.="\nFulfillment: ".ucfirst($fulfillment)."\nShipping: $".number_format($shipping,2)."\n";if($ship_address)$body.="Ship to: ".implode(', ',$ship_address)."\n";}
 if($appointment_mode!==''){$body.="\nAppointment type: ".ucfirst($appointment_mode)."\n";if($appointment_address)$body.="Requested visit address: ".implode(', ',$appointment_address)."\n";if($appointment_mode==='studio'&&!empty($s['appointment_studio_address']))$body.="Studio address: ".$s['appointment_studio_address']."\n";if($appointment_mode==='online')$body.="Requested online service: ".ucfirst($service)."\nJoining details will be supplied after approval.\n";}
 $body.=$registration_level!==null?"\nRegistration level: ".$registration_level['name']." ($".number_format($registration_price,2).")\n":'';$body.="\nPayment selection: ".$method."\nPayment status: ".($method==='none'?'Not required':'Pending verification')."\n";if($method==='mail')$body.="Check payable to: {$s['payee']}\nMail to: {$s['mail_address']}\n";if($method==='zelle')$body.="Zelle details: {$s['zelle']}\n";if($method==='venmo')$body.="Venmo: {$s['venmo']}\n";if($method==='cashapp')$body.="Cash App: {$s['cashapp']}\n";if($method==='paypal')$body.="PayPal: {$s['paypal']}\n";$body.="\nRegistration received. Payment has not been verified.\n";$items_text='';foreach($items as $item)$items_text.=$item['name'].' x '.$item['qty'].' @ $'.number_format($item['price'],2)."\n";if($tickets&&($method==='none'||$charged_total<=0)){$body.="\nYour individual QR tickets (one link per ticket):\n";foreach($tickets as $t)$body.=$t['label'].': '.add_query_arg(['action'=>'erm_ticket','code'=>$t['code']],admin_url('admin-post.php'))."\n";} if($appointment!=='')$body.="\nManage your appointment: ".self::appointment_manage_url($entry)."\n";$body.="\nAppointment requested: ".$appointment."\nItems:\n".$items_text."Total: $".number_format($charged_total,2)."\n";$tokens=['{form_title}'=>get_the_title($id),'{entry_id}'=>$entry,'{fields}'=>$body,'{items}'=>$items_text,'{appointment}'=>$appointment,'{payment}'=>$method,'{total}'=>'$'.number_format($charged_total,2)];$subject='Submission received: '.get_the_title($id).' (#'.$entry.')';$attachments=[];foreach($files as $field_uploads)foreach((array)$field_uploads as $attachment){$path=get_attached_file((int)$attachment);if($path&&is_file($path))$attachments[]=$path;}if(is_email($s['organizer_email'])){$reply_link=admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$entry);$reply_email=is_email($email)?'\nReply by email: '. 'mailto:'.$email.'?subject='.rawurlencode('Re: '.get_the_title($id).' #'.$entry):'';$admin_body=strtr($s['admin_template'],$tokens)."\n\nView and reply in dashboard: ".$reply_link.str_replace('\\n',"\n",$reply_email);$admin_html=wp_strip_all_tags($s['admin_template'])!==$s['admin_template'];if($admin_html){$admin_body=wp_kses_post(strtr($s['admin_template'],array_map('esc_html',$tokens))).'<p><a href="'.esc_url($reply_link).'">Review this request</a></p>';}if($s['form_type']==='appointment'){$formatted=$admin_html?$admin_body:nl2br(esc_html($admin_body));$admin_body=self::appointment_email_design('New appointment request',get_the_title($id),$formatted,$reply_link,'Review appointment');$admin_html=true;}wp_mail($s['organizer_email'],strtr($s['admin_subject'],$tokens),$admin_body,$admin_html?['Content-Type: text/html; charset=UTF-8']:[],$attachments);}if($s['receipt']&&is_email($email)){
  $customer_subject=trim((string)($s['customer_subject']??''));if($customer_subject==='')$customer_subject=$subject;else $customer_subject=strtr($customer_subject,$tokens);
  $customer_template=(string)($s['customer_template']??'');
  $customer_body=trim($customer_template)===''?$body:strtr($customer_template,$tokens);
  if($method!=='none'&&$charged_total>0){
   // A receipt confirms only that a request was submitted, not payment or acceptance.
   $customer_subject='Payment pending — '.get_the_title($id).' (#'.$entry.')';
   $customer_body="We received your submission #".$entry." for ".get_the_title($id).".\nAmount due: $".number_format($charged_total,2)."\nYour registration/order is not confirmed until payment is verified.\n";
   if($method==='mail')$customer_body.="Pay by check. Payable to: ".$s['payee']."\nMail payment to: ".$s['mail_address']."\n";
   elseif($method==='cash')$customer_body.="Pay by cash using the organizer's instructions. Contact the organizer to arrange payment.\n";
   else $customer_body.="Online payment has NOT been verified. Please contact the organizer for a secure checkout or payment instructions.\n";
   $customer_body.="You will receive a separate confirmation after payment is verified and your submission is approved.\n";
  }
  // Preserve private appointment management and ticket links in customized receipts.
  if(trim($customer_template)!==''&&($method==='none'||$charged_total<=0)){
   if($appointment!=='')$customer_body.="

Manage your appointment: ".self::appointment_manage_url($entry).self::appointment_location_details($entry,$s,'requested');
   if($tickets&&($method==='none'||$charged_total<=0)){$customer_body.="\n\nYour QR ticket links:\n";foreach($tickets as $t)$customer_body.=$t['label'].': '.add_query_arg(['action'=>'erm_ticket','code'=>$t['code']],admin_url('admin-post.php'))."\n";}
  }
  $customer_html=trim($customer_template)!==''&&wp_strip_all_tags($customer_template)!==$customer_template&&($method==='none'||$charged_total<=0);if($customer_html){$customer_body=wp_kses_post(strtr($customer_template,array_map('esc_html',$tokens)));if($appointment!=='')$customer_body.='<p><a href="'.esc_url(self::appointment_manage_url($entry)).'">Manage your appointment</a></p>';if($tickets){$customer_body.='<p>Your QR ticket links:</p><ul>';foreach($tickets as $t)$customer_body.='<li><a href="'.esc_url(add_query_arg(['action'=>'erm_ticket','code'=>$t['code']],admin_url('admin-post.php'))).'">'.esc_html($t['label']).'</a></li>';$customer_body.='</ul>';}}if($s['form_type']==='appointment'){$formatted=$customer_html?$customer_body:nl2br(esc_html($customer_body));$customer_body=self::appointment_email_design('Request received',get_the_title($id),$formatted,self::appointment_manage_url($entry),'Manage your appointment');$customer_html=true;}wp_mail($email,$customer_subject,$customer_body,$customer_html?['Content-Type: text/html; charset=UTF-8']:[],$attachments);
 }
 wp_safe_redirect(add_query_arg(['erm_result'=>'success','erm_event'=>$id],$redirect),303);exit;}

 static function admin_assets($hook){$screen=function_exists('get_current_screen')?get_current_screen():null;if(!($screen&&$screen->post_type==='erm_event')&&strpos((string)$hook,'erm_event')===false && strpos((string)$hook,'erm-registrations')===false && strpos((string)$hook,'erm-scanner')===false && strpos((string)$hook,'erm-dashboard')===false && strpos((string)$hook,'erm-changelog')===false && strpos((string)$hook,'erm-help')===false && strpos((string)$hook,'erm-transfer')===false && strpos((string)$hook,'erm-shipping')===false)return;wp_enqueue_style('erm-admin',plugins_url('assets/admin.css',__FILE__),[],self::VERSION);wp_enqueue_script('fcrc-appearance-colors',plugins_url('assets/appearance-colors.js',__FILE__),[],self::VERSION,true);wp_enqueue_script('erm-builder-live',plugins_url('assets/builder-live.js',__FILE__),[],self::VERSION,true);wp_enqueue_script('erm-admin-ui',plugins_url('assets/admin-ui.js',__FILE__),[],self::VERSION,true);wp_enqueue_script('fcrc-visual-studio',plugins_url('assets/visual-studio.js',__FILE__),['erm-builder-live'],self::VERSION,true);wp_enqueue_script('fcrc-contextual-builder',plugins_url('assets/contextual-builder.js',__FILE__),['fcrc-visual-studio'],self::VERSION,true);wp_enqueue_script('fcrc-email-studio',plugins_url('assets/email-studio.js',__FILE__),['fcrc-contextual-builder'],self::VERSION,true);wp_enqueue_script('fcrc-schedule-editor',plugins_url('assets/schedule-editor.js',__FILE__),['fcrc-contextual-builder'],self::VERSION,true);wp_enqueue_script('fcrc-theme-studio',plugins_url('assets/theme-studio.js',__FILE__),[],self::VERSION,true);wp_enqueue_script('fcrc-registration-levels',plugins_url('assets/registration-levels.js',__FILE__),[],self::VERSION,true);wp_enqueue_script('fcrc-shipping-admin',plugins_url('assets/shipping-admin.js',__FILE__),[],self::VERSION,true);wp_enqueue_script('fcrc-cart-frontend',plugins_url('assets/cart-frontend.js',__FILE__),[],self::VERSION,true);}
 static function ticket_url($code){return add_query_arg(['action'=>'erm_ticket','code'=>$code],admin_url('admin-post.php'));}
 static function find_ticket($code){if(!preg_match('/^[A-F0-9]{24}$/',$code))return [0,-1,null];$entries=get_posts(['post_type'=>'erm_entry','post_status'=>'private','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>[['key'=>'_erm_tickets','compare'=>'EXISTS']]]);foreach($entries as $id){foreach((array)get_post_meta($id,'_erm_tickets',true) as $i=>$t){if(isset($t['code'])&&hash_equals($t['code'],$code))return [$id,$i,$t];}}return [0,-1,null];}
 static function scanner_page(){
  if(!current_user_can('manage_options'))wp_die('Not authorized',403);
  $event=absint($_GET['event']??0);$q=trim(sanitize_text_field(wp_unslash($_GET['q']??'')));
  $events=get_posts(['post_type'=>'erm_event','post_status'=>'any','posts_per_page'=>-1]);
  $entries=get_posts(['post_type'=>'erm_entry','post_status'=>'private','posts_per_page'=>-1,'fields'=>'ids']);
  $issued=0;$checked=0;$recent=[];$matches=[];
  foreach($entries as $entry){$eid=(int)get_post_meta($entry,'_erm_event',true);if($event&&$eid!==$event)continue;
   $data=(array)get_post_meta($entry,'_erm_data',true);$haystack=strtolower(implode(' ',array_map(function($v){return is_array($v)?implode(' ',$v):(string)$v;},$data)).' '.get_the_title($eid).' '.$entry);
   foreach((array)get_post_meta($entry,'_erm_tickets',true) as $t){$issued++;if(!empty($t['checked_in'])){$checked++;$recent[]=['entry'=>$entry,'ticket'=>$t];}
    if($q!==''&&(stripos($haystack,$q)!==false||stripos($t['code']??'',$q)!==false))$matches[]=['entry'=>$entry,'ticket'=>$t,'event'=>$eid];
   }
  }
  usort($recent,function($a,$b){return strcmp($b['ticket']['checked_at']??'',$a['ticket']['checked_at']??'');});
  echo '<div class="wrap erm-scanner-page"><h1>Event Check-In</h1><p class="erm-lead">Scan tickets or search for a guest. All check-ins are saved to the website immediately.</p>';
  echo '<form method="get" class="erm-checkin-filter"><input type="hidden" name="post_type" value="erm_event"><input type="hidden" name="page" value="erm-scanner"><label for="erm-event-filter"><strong>Event: </strong></label><select id="erm-event-filter" name="event"><option value="0">All Events</option>';
  foreach($events as $e)echo '<option value="'.absint($e->ID).'" '.selected($event,$e->ID,false).'>'.esc_html($e->post_title).'</option>';
  echo '</select> <button class="button">Show Event</button></form><div class="erm-checkin-stats"><div><strong>'.number_format_i18n($issued).'</strong><span>Tickets Issued</span></div><div><strong>'.number_format_i18n($checked).'</strong><span>Checked In</span></div><div><strong>'.number_format_i18n(max(0,$issued-$checked)).'</strong><span>Remaining</span></div></div>';
  echo '<div class="erm-checkin-columns"><section class="erm-scan-card"><h2>Scan Ticket</h2><p>Use your device camera or enter the ticket code.</p><video id="erm-camera" autoplay playsinline muted style="max-width:100%;width:100%;background:#16213b;border-radius:12px" aria-label="Ticket camera"></video><p id="erm-scan-status" role="status">Camera not started.</p><p><button type="button" class="button button-primary" id="erm-start-camera">Open Camera Scanner</button> <button type="button" class="button" id="erm-stop-camera">Stop</button></p><form id="erm-code-form"><label for="erm-code">Ticket code</label><p><input id="erm-code" type="text" maxlength="48" placeholder="Enter ticket code"> <button class="button button-primary">Find Ticket</button></p></form><p class="description">Camera scanning requires HTTPS and browser QR support. Manual entry is always available.</p></section>';
  echo '<section class="erm-scan-card"><h2>Find Attendee</h2><form method="get"><input type="hidden" name="post_type" value="erm_event"><input type="hidden" name="page" value="erm-scanner"><input type="hidden" name="event" value="'.absint($event).'"><p><input name="q" value="'.esc_attr($q).'" placeholder="Name, email, ticket code or entry ID" style="width:100%"></p><button class="button button-primary">Search Tickets</button></form>';
  if($q!==''){echo '<h3>Search Results ('.count($matches).')</h3><div class="erm-results">';foreach(array_slice($matches,0,100) as $r){$t=$r['ticket'];echo '<p><a href="'.esc_url(self::ticket_url($t['code'])).'"><strong>'.esc_html($t['label']).'</strong> · #'.absint($r['entry']).'</a><br>'.esc_html(get_the_title($r['event'])).' — '.(!empty($t['checked_in'])?'Checked In':'Not Checked In').'</p>';}echo '</div>';}
  echo '</section></div><section class="erm-scan-card"><h2>Recent Check-Ins</h2><table class="widefat striped"><thead><tr><th>Ticket</th><th>Checked In</th><th>Action</th></tr></thead><tbody>';
  foreach(array_slice($recent,0,30) as $r){$t=$r['ticket'];echo '<tr><td><a href="'.esc_url(self::ticket_url($t['code'])).'">'.esc_html($t['label']).' — #'.absint($r['entry']).'</a></td><td>'.esc_html($t['checked_at']??'').'</td><td><a href="'.esc_url(self::ticket_url($t['code'])).'">View</a></td></tr>';}
  if(!$recent)echo '<tr><td colspan="3">No check-ins yet.</td></tr>';echo '</tbody></table></section></div>';
  echo '<script>window.ERMScannerBase='.wp_json_encode(admin_url('admin-post.php?action=erm_ticket&code=')).';</script><script src="'.esc_url(plugins_url('assets/scanner.js',__FILE__)).'"></script>';
 }
 static function ticket_page(){ $code=strtoupper(sanitize_text_field(wp_unslash($_GET['code']??'')));[$entry,$index,$ticket]=self::find_ticket($code);if(!$entry||!$ticket){status_header(404);wp_die('Ticket not found.');} $event=(int)get_post_meta($entry,'_erm_event',true);$status=get_post_meta($entry,'_erm_registration_status',true);$valid=$status!=='cancelled';$used=!empty($ticket['checked_in']);$url=self::ticket_url($code);nocache_headers();header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Event Ticket</title></head><body style="font:16px system-ui;max-width:540px;margin:30px auto;padding:22px;border:1px solid #ddd;border-radius:14px;text-align:center"><h1>'.esc_html(get_the_title($event)).'</h1><h2>'.esc_html($ticket['label']).'</h2><p>Ticket '.esc_html($code).'</p><div id="erm-qr" data-value="'.esc_attr($url).'" aria-label="Ticket QR code"></div><p>'.esc_html(!$valid?'Cancelled':($used?'Already checked in':'Ticket issued — present at entry')).'</p>';if(current_user_can('manage_options')&&$valid&&!$used){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_checkin"><input type="hidden" name="code" value="'.esc_attr($code).'">';wp_nonce_field('erm_checkin_'.$code);echo '<button style="padding:12px 22px;background:#2563eb;color:white;border:0;border-radius:7px">Check in this ticket</button></form>';}if(current_user_can('manage_options')){ $payment=get_post_meta($entry,'_erm_payment_status',true);echo '<p><strong>Payment:</strong> '.esc_html($payment?:'Not verified').'</p><p><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$entry)).'">View registration and payment details</a></p>';if($used){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_undo_checkin"><input type="hidden" name="code" value="'.esc_attr($code).'">';wp_nonce_field('erm_undo_checkin_'.$code);echo '<button style="padding:10px 16px">Undo Check-In</button></form>';}}echo '<p><small>Payment is not automatically verified. Staff must confirm payment status separately.</small></p><script src="'.esc_url(plugins_url('assets/qr-local.js',__FILE__)).'"></script><script>if(window.ERMQR){ERMQR.render(document.getElementById("erm-qr"));}</script></body></html>';exit;}
 static function checkin(){if(!current_user_can('manage_options'))wp_die('Not authorized',403);$code=strtoupper(sanitize_text_field(wp_unslash($_POST['code']??'')));check_admin_referer('erm_checkin_'.$code);global $wpdb; $lock_name='erm_checkin_'.hash('sha256',$code); $locked=(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock_name)); if($locked!==1)wp_die('Ticket is being processed; please try again.',409); [$entry,$index,$ticket]=self::find_ticket($code);if(!$entry||!$ticket)wp_die('Invalid ticket',404);if(get_post_meta($entry,'_erm_registration_status',true)==='cancelled')wp_die('Cancelled registration',400);$tickets=(array)get_post_meta($entry,'_erm_tickets',true);if(empty($tickets[$index]['checked_in'])){$tickets[$index]['checked_in']=true;$tickets[$index]['checked_at']=current_time('mysql');update_post_meta($entry,'_erm_tickets',$tickets);}$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock_name));wp_safe_redirect(self::ticket_url($code));exit;}
 static function undo_checkin(){if(!current_user_can('manage_options'))wp_die('Not authorized',403);$code=strtoupper(sanitize_text_field(wp_unslash($_POST['code']??'')));check_admin_referer('erm_undo_checkin_'.$code);[$entry,$index,$ticket]=self::find_ticket($code);if(!$entry||!$ticket)wp_die('Invalid ticket',404);$tickets=(array)get_post_meta($entry,'_erm_tickets',true);$tickets[$index]['checked_in']=false;unset($tickets[$index]['checked_at']);update_post_meta($entry,'_erm_tickets',$tickets);wp_safe_redirect(self::ticket_url($code));exit;}
 static function ticket_admin_panel($entry){$tickets=get_post_meta($entry,'_erm_tickets',true);if(!is_array($tickets)||!$tickets)return;echo '<div class="notice notice-info" style="padding:12px 18px"><h2>Individual QR Tickets</h2><p>Each ticket has a unique code. Share ticket links with the attendee, or open them to scan and check in.</p><ul>';foreach($tickets as $t){if(!is_array($t)||empty($t['code'])||empty($t['label']))continue;echo '<li><a target="_blank" rel="noopener" href="'.esc_url(self::ticket_url($t['code'])).'">'.esc_html($t['label']).' — '.esc_html($t['code']).'</a> — '.(!empty($t['checked_in'])?'Checked in '.esc_html($t['checked_at']):'Not checked in').'</li>';}echo '</ul></div>';}
 static function help_page(){
  if(!current_user_can('edit_posts'))wp_die('Insufficient permissions.');
  $new=admin_url('post-new.php?post_type=erm_event');
  $all=admin_url('edit.php?post_type=erm_event&erm_view=list');
  echo '<div class="wrap" style="max-width:1050px"><h1>Form, Cart and Registration Creator — Getting Started</h1><p>Create contact forms, appointment requests, event registrations, and ticket or product order forms without writing code.</p>';
  echo '<p><a class="button" href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-payment-accounts')).'">Payment connection instructions (Stripe, PayPal, Square, Zelle, Cash App, cash/check)</a></p>';
  echo '<p><a class="button button-primary" href="'.esc_url($new).'">Create a New Form</a> <a class="button" href="'.esc_url($all).'">View My Forms</a></p>';
  $sections=[
   ['Before updating','Back up your database and wp-content/uploads. Existing form IDs and entry metadata remain unchanged. Test on staging before accepting real payments or collecting personal information.'],['1. Create your form','Choose Forms → Add New. Enter a clear title, such as “2027 Vendor Registration.” Select a Form Type: Registration, Question / Comment, Appointment Request, or Tickets / Products.'],
   ['2. Choose the questions','Start with the provided Name, Business, Address, Phone, Email, and Products Sold fields. Rename or remove fields, or select Add Field for additional questions. Use a unique Field ID for every field. Set Required for mandatory questions. File fields let visitors upload pictures or documents.'],
   ['3. Set up tickets, products, or service levels','Under Products, Tickets & Service Levels, select Add Product / Level. Give each item a title, description, optional picture or icon, and price. Visitors choose quantities; the total is calculated from saved prices. Leave this section empty for ordinary contact forms.'],
   ['4. Configure payment (optional)','Choose Free / No payment, Optional, or Required. Enable PayPal, Venmo, Zelle, Cash App, mailed checks, or in-person payment and fill in the corresponding destination or instructions. These are payment links or instructions, not verified automatic checkout. Mark offline payments paid after confirming receipt.'],
   ['5. Customize the appearance','Add introductory text, a hero image URL, font, background, text color, and accent color. Use an image from the WordPress Media Library or a permitted external HTTPS image URL.'],
   ['6. Set up emails and confirmation','Enter the organizer notification email, customize the admin subject and template, and decide whether to email submitters a receipt. The acknowledgment message appears after submission. Uploaded files may be attached to emails; avoid collecting confidential documents.'],
   ['7. Preview and publish','Publish the form, copy its [event_registration id="123"] shortcode (replace 123 with your form ID), and paste it into a WordPress Shortcode block on any page. Test a submission, check both emails, and confirm the thank-you message before sharing the link.'],
   ['8. Review and reply','Open Forms → Registrations to review submissions, filter by form, update payment status, export records, and reply from the dashboard. Entries stay associated with the form they were submitted through.'],
   ['9. Reuse next year','On Forms → All Forms, hover over a form and choose Clone Form. Rename and edit the new draft. Previous entries are not copied.'],
   ['Appointment requests','Add a Google appointment booking URL to direct visitors to available slots. Requested date/time fields are requests only: the plugin does not synchronize or reserve Google Calendar appointments.'],
   ['Public lists and privacy','The optional [event_registrations id="123"] shortcode displays only fields explicitly marked Public. Do not mark private contact details Public without consent.'],
   ['QR tickets and check-in','Enable QR Tickets on an individual form. Each selected product quantity generates its own unique ticket link in the registrant receipt. Staff with WordPress administrator access can scan/open a ticket and mark it checked in. Payment verification is separate. QR images are generated locally, without a third-party QR service.'],
   ['Email troubleshooting','WordPress sends emails using wp_mail(). If receipts do not arrive, verify the organizer address, check spam, and configure a reputable SMTP/mail delivery service. Test with a small upload first.']
  ];
  foreach($sections as $section){echo '<div class="postbox" style="padding:14px 20px;margin:14px 0"><h2>'.esc_html($section[0]).'</h2><p>'.esc_html($section[1]).'</p></div>';}
  echo '</div>';
 }
 static function redirect_forms_home(){
  if(!is_admin()||!current_user_can('manage_options'))return;
  global $pagenow;
  if($pagenow!=='edit.php'||($_GET['post_type']??'')!=='erm_event')return;
  if(isset($_GET['page'])||isset($_GET['erm_view'])||isset($_GET['s'])||isset($_GET['post_status'])||isset($_GET['action'])||isset($_GET['action2']))return;
  wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-dashboard'));exit;
 }
 static function form_list_columns($columns){
  $out=[];foreach($columns as $key=>$label){$out[$key]=$label;if($key==='title')$out['erm_embed_shortcode']='Embed shortcode';}
  if(!isset($out['erm_embed_shortcode']))$out['erm_embed_shortcode']='Embed shortcode';
  return $out;
 }
 static function form_list_column($column,$post_id){
  if($column!=='erm_embed_shortcode')return;
  $shortcode='[event_registration id="'.absint($post_id).'"]';
  echo '<div class="erm-shortcode-cell" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">';
  echo '<code style="white-space:nowrap">'.esc_html($shortcode).'</code>';
  echo '<button type="button" class="button button-small erm-copy-shortcode" data-shortcode="'.esc_attr($shortcode).'">Copy</button>';
  echo '</div>';
 }
 static function appointment_builder_visibility_script(){
  $screen=get_current_screen();if(!$screen||$screen->id!=='erm_event')return;
  echo '<script>(function(){function refreshAppointment(){var select=document.getElementById("erm-form-type");var section=document.getElementById("erm-appointment-settings");if(!select||!section)return;section.style.display=select.value==="appointment"?"block":"none";}document.addEventListener("change",function(event){if(event.target&&event.target.id==="erm-form-type")refreshAppointment();});if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",refreshAppointment);}else{refreshAppointment();}})();</script>';
 }
 static function shortcode_copy_script(){
  $screen=get_current_screen();if(!$screen||$screen->id!=='edit-erm_event')return;
  echo '<script>document.addEventListener("click",function(e){const btn=e.target.closest(".erm-copy-shortcode");if(!btn)return;const code=btn.getAttribute("data-shortcode");const done=function(){btn.textContent="Copied!";setTimeout(function(){btn.textContent="Copy";},1600);};if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(code).then(done).catch(function(){window.prompt("Copy shortcode:",code);});}else{const el=document.createElement("textarea");el.value=code;el.style.position="fixed";el.style.opacity="0";document.body.appendChild(el);el.select();try{if(document.execCommand("copy"))done();else window.prompt("Copy shortcode:",code);}catch(err){window.prompt("Copy shortcode:",code);}el.remove();}});</script>';
 }
 static function admin_menu(){
  $parent='edit.php?post_type=erm_event';
  add_submenu_page($parent,'Super Forms Dashboard','Dashboard','manage_options','erm-dashboard',[__CLASS__,'dashboard_page']);
  // WordPress supplies All Forms and Add New automatically for this post type.
  add_submenu_page($parent,'Registrations','Registrations','manage_options','erm-registrations-hub',[__CLASS__,'registrations_hub_page']);
  add_submenu_page($parent,'Submissions','Submissions','manage_options','erm-registrations',[__CLASS__,'entries_page']);
  add_submenu_page($parent,'Appointments','Appointments','manage_options','erm-appointment-hub',[__CLASS__,'appointment_hub_page']);
  add_submenu_page($parent,'Settings','Settings','manage_options','erm-settings-hub',[__CLASS__,'settings_hub_page']);
  add_submenu_page($parent,'Help & Updates','Help & Updates','edit_posts','erm-help-hub',[__CLASS__,'help_hub_page']);
  // Keep original page registrations for permissions, bookmarks, and existing internal links.
  add_submenu_page($parent,'Shipping Providers','Shipping Providers','manage_options','erm-shipping',['ERM_Shipping_Providers','settings_page']);
  add_submenu_page($parent,'Payment Review','Payment Review','manage_options','erm-payment-review',[__CLASS__,'payment_review_page']);
  add_submenu_page($parent,'Appointment Calendar','Appointment Calendar','manage_options','erm-appointment-calendar',[__CLASS__,'appointment_calendar_page']);
  add_submenu_page($parent,'Payment Accounts','Payment Accounts','manage_options','erm-payment-accounts',[__CLASS__,'payment_accounts_page']);add_submenu_page($parent,'Appointment Integrations','Appointment Integrations','manage_options','erm-appointment-integrations',[__CLASS__,'appointment_integrations_page']);
  add_submenu_page($parent,'Import / Export','Import / Export','manage_options','erm-transfer',[__CLASS__,'transfer_page']);
  add_submenu_page($parent,'Changelog','Changelog','edit_posts','erm-changelog',[__CLASS__,'changelog_page']);
  add_submenu_page($parent,'Event Check-In','Event Check-In','manage_options','erm-scanner',[__CLASS__,'scanner_page']);
  add_submenu_page($parent,'Getting Started & Help','Getting Started & Help','edit_posts','erm-help',[__CLASS__,'help_page']);
  add_action('admin_menu',[__CLASS__,'consolidate_menu'],999);
 }
 static function consolidate_menu(){
  global $submenu;
  $parent='edit.php?post_type=erm_event';
  if(empty($submenu[$parent]))return;
  $hidden=['erm-dashboard','erm-registrations','erm-shipping','erm-payment-review','erm-appointment-calendar','erm-payment-accounts','erm-transfer','erm-changelog','erm-scanner','erm-help','erm-google-calendar','erm-shipping-settings','erm-shipping-labels'];
  // Remove only sidebar entries, never registered admin pages or capabilities.
  foreach($submenu[$parent] as $index=>$item){
   if(in_array($item[2]??'',$hidden,true))unset($submenu[$parent][$index]);
  }
 }
 static function hub_links($links){
  echo '<div class="wrap"><div class="erm-shell"><div class="erm-panel" style="padding:24px"><div class="erm-action-grid">';
  foreach($links as $item){
   if(!current_user_can($item[2]))continue;
   echo '<a class="erm-action" href="'.esc_url(admin_url($item[1])).'"><strong>'.esc_html($item[0]).'</strong><small>'.esc_html($item[3]).'</small></a>';
  }
  echo '</div></div></div></div>';
 }
 static function registrations_hub_page(){
  if(!current_user_can('manage_options'))return;
  self::admin_header('Registrations','Submissions, payments and event check-in.');
  self::hub_links([
   ['Submissions','edit.php?post_type=erm_event&page=erm-registrations','manage_options','Review registrations and form responses'],
   ['Payment Review','edit.php?post_type=erm_event&page=erm-payment-review','manage_options','Reconcile and manage payments'],
   ['Event Check-In','edit.php?post_type=erm_event&page=erm-scanner','manage_options','Scan tickets and check in guests']
  ]);
 }
 static function appointment_hub_page(){
  if(!current_user_can('manage_options'))return;
  self::admin_header('Appointments','Manage appointment bookings and calendar integrations.');
  self::hub_links([
   ['Appointment Calendar','edit.php?post_type=erm_event&page=erm-appointment-calendar','manage_options','Review bookings and availability'],
   ['Google Calendar','edit.php?post_type=erm_event&page=erm-google-calendar','manage_options','Connect calendars and manage synchronization']
  ]);
 }
 static function settings_hub_page(){
  if(!current_user_can('manage_options'))return;
  self::admin_header('Settings','Payments, shipping, integrations and data management.');
  self::hub_links([
   ['Payment Accounts','edit.php?post_type=erm_event&page=erm-payment-accounts','manage_options','Configure payment destinations'],
   ['Appointment Integrations','edit.php?post_type=erm_event&page=erm-appointment-integrations','manage_options','Choose online meeting services and Google calendars'],
   ['Shipping Providers','edit.php?post_type=erm_event&page=erm-shipping','manage_options','Manage carrier credentials'],
   ['Shipping Settings','edit.php?post_type=erm_event&page=erm-shipping-settings','manage_options','Configure Shippo shipping rates'],
   ['Shipping Labels','edit.php?post_type=erm_event&page=erm-shipping-labels','manage_options','Review and purchase shipping labels'],
   ['Import / Export','edit.php?post_type=erm_event&page=erm-transfer','manage_options','Move form configurations']
  ]);
 }
 static function help_hub_page(){
  if(!current_user_can('edit_posts'))return;
  self::admin_header('Help & Updates','Documentation and version history.');
  self::hub_links([
   ['Getting Started & Help','edit.php?post_type=erm_event&page=erm-help','edit_posts','Setup instructions and help'],
   ['Changelog','edit.php?post_type=erm_event&page=erm-changelog','edit_posts','See what changed']
  ]);
 }
 static function hide_dashboard_submenu(){
  // Hide visually only. WordPress must retain the registered submenu to authorize
  // edit.php?post_type=erm_event&page=erm-dashboard during admin.php access checks.
  echo '<style id="erm-dashboard-menu-hide">#adminmenu a[href="edit.php?post_type=erm_event&page=erm-dashboard"]{display:none!important}#adminmenu li a[href="edit.php?post_type=erm_event&page=erm-dashboard"]{display:none!important}</style>';
 }
 static function admin_header($heading,$subtitle){echo '<div class="erm-shell"><div class="erm-topbar"><span class="erm-brand">✦ FORM, CART & REGISTRATION CREATOR</span><span class="erm-version">v'.esc_html(self::VERSION).'</span></div><div class="erm-banner"><div><p class="erm-eyebrow">YOUR WORKSPACE</p><h1>'.esc_html($heading).'</h1><p>'.esc_html($subtitle).'</p></div><span class="dashicons dashicons-feedback" aria-hidden="true"></span></div><nav class="erm-quicknav" aria-label="Plugin sections"><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-dashboard')).'">⌂ Dashboard</a><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&erm_view=list')).'">▤ My Forms</a><a href="'.esc_url(admin_url('post-new.php?post_type=erm_event')).'">＋ Create Form</a><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-registrations')).'">✉ Submissions</a><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-scanner')).'">▣ Check-In</a><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-transfer')).'">⇄ Import / Export</a><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-help')).'">? Help</a><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-changelog')).'">↻ Changelog</a></nav>';}
 static function dashboard_page(){if(!current_user_can('manage_options'))return;$forms=wp_count_posts('erm_event');$form_total=(int)($forms->publish??0)+(int)($forms->draft??0);$entry_total=(int)(wp_count_posts('erm_entry')->private??0);$recent=get_posts(['post_type'=>'erm_entry','post_status'=>'private','posts_per_page'=>6,'orderby'=>'date','order'=>'DESC']);$all=get_posts(['post_type'=>'erm_entry','post_status'=>'private','posts_per_page'=>-1,'fields'=>'ids']);$pending=0;$issued=0;$checked=0;foreach($all as $eid){$pay=get_post_meta($eid,'_erm_payment_status',true);if(in_array($pay,['unpaid','pending','partial'],true))$pending++;foreach((array)get_post_meta($eid,'_erm_tickets',true) as $ticket){$issued++;if(!empty($ticket['checked_in']))$checked++;}}echo '<div class="wrap">';self::admin_header('Dashboard','Create beautiful forms, manage orders and welcome your guests.');echo '<div class="erm-metrics"><div><span class="erm-metric-icon blue">▤</span><strong>'.number_format_i18n($form_total).'</strong><span>Forms</span></div><div><span class="erm-metric-icon green">✉</span><strong>'.number_format_i18n($entry_total).'</strong><span>Submissions</span></div><div><span class="erm-metric-icon orange">$</span><strong>'.number_format_i18n($pending).'</strong><span>Payments to Review</span></div><div><span class="erm-metric-icon purple">▣</span><strong>'.number_format_i18n($checked).' / '.number_format_i18n($issued).'</strong><span>Tickets Checked In</span></div></div><div class="erm-dashboard-grid"><section class="erm-panel"><h2>Quick Start</h2><p>Choose what you want to build. Each form has its own fields, products, emails and entries.</p><div class="erm-action-grid"><a class="erm-action blue" href="'.esc_url(admin_url('post-new.php?post_type=erm_event')).'"><span>＋</span><strong>New Form</strong><small>Registration, questions or appointments</small></a><a class="erm-action green" href="'.esc_url(admin_url('edit.php?post_type=erm_event&erm_view=list')).'"><span>▤</span><strong>Manage Forms</strong><small>Edit, duplicate or publish</small></a><a class="erm-action purple" href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-scanner')).'"><span>▣</span><strong>Ticket Check-In</strong><small>Scan QR codes and search guests</small></a><a class="erm-action orange" href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-help')).'"><span>?</span><strong>Getting Started</strong><small>Step-by-step instructions</small></a></div></section><section class="erm-panel"><h2>Recent Submissions</h2>';if(!$recent)echo '<p>No entries yet. Publish a form to start receiving submissions.</p>';else{echo '<div class="erm-recent-list">';foreach($recent as $entry){$eid=(int)get_post_meta($entry->ID,'_erm_event',true);echo '<a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$entry->ID)).'"><strong>#'.(int)$entry->ID.' · '.esc_html(get_the_title($eid)).'</strong><small>'.esc_html(get_date_from_gmt($entry->post_date_gmt?:$entry->post_date,'M j, Y g:i a')).'</small><span>View →</span></a>';}echo '</div>';}echo '<p><a class="button button-primary" href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-registrations')).'">Open Submission Inbox</a></p></section></div><div class="erm-panel erm-tips"><h2>Build your first form in minutes</h2><div class="erm-tip-grid"><div><b>1. Create</b><p>Name the form and choose its type.</p></div><div><b>2. Customize</b><p>Add fields, products, images and payment options.</p></div><div><b>3. Notify</b><p>Set up receipt and organizer emails.</p></div><div><b>4. Publish</b><p>Copy the shortcode into any page.</p></div></div></div></div></div>';}
 static function changelog_page(){if(!current_user_can('edit_posts'))return;echo '<div class="wrap">';self::admin_header('Changelog','See what changed in each release of your plugin.');$releases=[['1.3.36','The final form-builder section now shows Submit Form and uses the WordPress Publish/Update action to save the form.'],['1.3.34','Fix dashboard access by keeping WordPress submenu registration intact and hiding only its visual menu link.'],['1.3.29','Shippo live checkout rates and 4x6 label purchase; direct carrier credentials saved for later API integration.'],['1.3.24','Google Calendar synchronization status, automatic retries, and manual retry controls.'],['1.3.23','Payment review dashboard and manual reconciliation history.'],['1.3.22','Weekly appointment calendar and visual availability editor.'],['1.3.21','Separate customer email templates and live preview.'],['1.3.20','Centralized payment accounts and per-form payment method selection.'],['1.3.19','Organizer email customization and preview.'],['1.3.18','Removed duplicate appearance color swatches; one interactive picker appears to the right of the hex field and eyedropper button.'],['1.3.17','Appearance controls: native color maps, editable hex values, and supported-browser eyedropper for all six form colors.'],['1.3.14','Appointment scheduling: preparation/cleanup buffers, minimum advance notice, blocked dates, and multiple daily availability windows.'],['1.3.10','Context-aware form builder, appointment-only scheduling controls, product-only catalog controls, and reliable loading of visual editor assets.'],['1.3.8','Separate locked appointment duration and start-time intervals; avoid overlapping bookings and preserve appointment duration for new submissions.'],['1.3.7','Field width options (full, half, third), responsive front-end grid, and visual designer crash and focus fixes.'],['1.3.5','Visual editor usability: property fields retain focus while typing, accessible move-up/move-down buttons for field ordering, and clearer keyboard focus states.'],['1.3.4','Interactive visual form canvas with click-to-edit field properties, drag-and-drop positioning, field palette, and synchronized live preview.'],['1.3.3','Three-panel form studio with field toolbox, section navigation, responsive live preview, and improved field controls.'],['1.3.2','Form JSON import/export, safe draft-only imports, and redesigned admin workspace navigation and cards.'],['1.3.1','Security hardening: submission rate limiting, safer uploaded-file validation, improved ticket check-in concurrency, and expanded administrator guidance.'],['1.3.0','Drag-and-drop field and product ordering, live form and email previews, and builder layout improvements.'],['1.2.8','Enhanced admin dashboard, colorful quick actions, live summary cards, recent submissions and built-in changelog.'],['1.2.7','Event check-in dashboard with event filtering, search, QR scanning, counts and undo check-in.'],['1.2.6','Locally generated QR codes and camera-based ticket scanning.'],['1.2.5','Individual ticket codes and initial visual improvements to administration.'],['1.2.4','Getting Started and Help documentation.'],['1.2.3','Expanded product and service tiers with item descriptions, pictures, prices and quantities.'],['1.2.2','Renamed to Form, Cart and Registration Creator.'],['1.2.1','Clone an existing form without copying its submissions.'],['1.2.0','Submission inbox, administrator email replies and reply history.'],['1.1.0','Contact and appointment forms, product orders, and custom email templates.'],['1.0.1','Uploaded file attachments included in notification emails.'],['1.0.0','Initial form builder, payments, email receipts and submission storage.']];echo '<div class="erm-changelog">';foreach($releases as $i=>$r)echo '<section class="erm-panel"><div class="erm-release-head"><h2>Version '.esc_html($r[0]).'</h2>'.($i===0?'<span class="erm-current">Current</span>':'').'</div><p>'.esc_html($r[1]).'</p></section>';echo '</div></div></div>';}
 static function appointment_calendar_page(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);
  $date=sanitize_text_field(wp_unslash($_GET['week']??wp_date('Y-m-d')));
  try{$start=new DateTimeImmutable($date,wp_timezone());}catch(Exception $e){$start=new DateTimeImmutable('today',wp_timezone());}
  $start=$start->modify('monday this week')->setTime(0,0);
  $end=$start->modify('+7 days');
  $forms=get_posts(['post_type'=>'erm_event','post_status'=>['publish','draft','private'],'numberposts'=>-1]);
  $selected=absint($_GET['form']??0);
  $valid=[];foreach($forms as $form){$settings=self::settings($form->ID);if($settings['form_type']==='appointment')$valid[$form->ID]=$form->post_title;}
  $entries=get_posts(['post_type'=>'erm_entry','post_status'=>'private','numberposts'=>-1,'meta_query'=>[['key'=>'_erm_appointment','value'=>$start->format('Y-m-d'),'compare'=>'>='],['key'=>'_erm_appointment','value'=>$end->format('Y-m-d'),'compare'=>'<']]]);
  $days=array_fill(0,7,[]);
  foreach($entries as $entry){
   $form_id=(int)get_post_meta($entry->ID,'_erm_event',true);
   if(!isset($valid[$form_id])||($selected&&$form_id!==$selected))continue;
   $when=(string)get_post_meta($entry->ID,'_erm_appointment',true);
   try{$at=new DateTimeImmutable($when,wp_timezone());}catch(Exception $e){continue;}
   $index=(int)$start->diff($at)->format('%r%a');if($index<0||$index>6)continue;
   $days[$index][]=['entry'=>$entry->ID,'time'=>$at->format('H:i'),'form'=>$valid[$form_id],'status'=>get_post_meta($entry->ID,'_erm_registration_status',true)];
  }
  echo '<div class="wrap"><h1>Appointment Calendar</h1><p>Weekly view of bookings. Select a booking to confirm, cancel, or reschedule it using availability-checked controls.</p>';
  echo '<form method="get"><input type="hidden" name="post_type" value="erm_event"><input type="hidden" name="page" value="erm-appointment-calendar"><label>Week of <input type="date" name="week" value="'.esc_attr($start->format('Y-m-d')).'"></label> <label>Form <select name="form"><option value="0">All appointment forms</option>';
  foreach($valid as $id=>$name)echo '<option value="'.absint($id).'" '.selected($selected,$id,false).'>'.esc_html($name).'</option>';
  echo '</select></label> <button class="button button-primary">View week</button></form>';
  $base=admin_url('edit.php?post_type=erm_event&page=erm-appointment-calendar');
  echo '<p><a class="button" href="'.esc_url(add_query_arg(['week'=>$start->modify('-7 days')->format('Y-m-d'),'form'=>$selected],$base)).'">← Previous week</a> <a class="button" href="'.esc_url(add_query_arg(['week'=>$start->modify('+7 days')->format('Y-m-d'),'form'=>$selected],$base)).'">Next week →</a></p>';
  echo '<div class="fcrc-booking-week">';
  for($i=0;$i<7;$i++){
   $day=$start->modify('+'.$i.' days');
   echo '<section class="fcrc-booking-day"><h2>'.esc_html($day->format('D, M j')).'</h2>';
   usort($days[$i],fn($a,$b)=>strcmp($a['time'],$b['time']));
   if(!$days[$i])echo '<p class="description">No appointments</p>';
   foreach($days[$i] as $booking){
    $link=admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$booking['entry']);
    echo '<a class="fcrc-booking-card" href="'.esc_url($link).'"><strong>'.esc_html($booking['time']).'</strong><span>'.esc_html($booking['form']).'</span><small>#'.absint($booking['entry']).' · '.esc_html(ucfirst($booking['status'])).'</small></a>';
   }
   echo '</section>';
  }
  echo '</div></div>';
 }
 static function delete_submission(){
  if(!current_user_can('manage_options'))wp_die('Not authorized',403);
  if(($_SERVER['REQUEST_METHOD']??'')!=='POST')wp_die('Invalid request method',405);
  $entry=absint($_POST['entry']??0);
  check_admin_referer('erm_delete_submission_'.$entry);
  if(!$entry||get_post_type($entry)!=='erm_entry')wp_die('Invalid submission',400);
  $event=absint(get_post_meta($entry,'_erm_event',true));
  $deleted=wp_trash_post($entry);
  if(!$deleted)wp_die('Unable to move submission to Trash. Please try again.',500);
  $url=admin_url('edit.php?post_type=erm_event&page=erm-registrations');
  wp_safe_redirect(add_query_arg(['event'=>$event,'submission_trashed'=>1],$url));exit;
 }
 static function delete_submission_form($entry,$compact=false){
  $entry=absint($entry);
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin:4px 0" onsubmit="return confirm(\'Move submission #'.esc_js((string)$entry).' to Trash? Its uploaded files will remain in the Media Library.\');">';
  echo '<input type="hidden" name="action" value="erm_delete_submission"><input type="hidden" name="entry" value="'.esc_attr($entry).'">';
  wp_nonce_field('erm_delete_submission_'.$entry);
  echo '<button type="submit" class="button '.($compact?'button-small':'').'" style="color:#b32d2e;border-color:#b32d2e">Delete Submission</button></form>';
 }
 static function entries_page(){if(!current_user_can('manage_options'))return;$events=get_posts(['post_type'=>'erm_event','posts_per_page'=>-1,'post_status'=>'any']);$id=absint($_GET['event']??0);$selected=absint($_GET['entry']??0);if($selected&&get_post_type($selected)==='erm_entry'&&get_post_status($selected)==='private')self::entry_detail($selected);echo '<div class="wrap erm-inbox"><h1>Submission Inbox</h1><p class="erm-lead">Review form entries, update payments, and reply to registrants from one place.</p>' . (isset($_GET['submission_trashed'])?'<div class="notice notice-success"><p>Submission moved to Trash. Its uploaded files were retained.</p></div>':'') . '<form method="get"><input type="hidden" name="post_type" value="erm_event"><input type="hidden" name="page" value="erm-registrations"><select name="event"><option value="0">All Events</option>';foreach($events as $e)echo '<option value="'.$e->ID.'" '.selected($id,$e->ID,false).'>'.esc_html($e->post_title).'</option>';echo '</select> <button class="button">Filter</button></form><p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=erm_export&event='.$id),'erm_export')).'">Export CSV</a></p><table class="widefat striped"><thead><tr><th>ID / Date</th><th>Event</th><th>Registrant Details</th><th>Uploads</th><th>Payment</th><th>Registration</th></tr></thead><tbody>';$args=['post_type'=>'erm_entry','post_status'=>'private','posts_per_page'=>100,'orderby'=>'date','order'=>'DESC'];if($id)$args['meta_query']=[['key'=>'_erm_event','value'=>$id]];$entries=get_posts($args);foreach($entries as $e){$event=(int)get_post_meta($e->ID,'_erm_event',true);$data=(array)get_post_meta($e->ID,'_erm_data',true);$files=(array)get_post_meta($e->ID,'_erm_files',true);$s=self::settings($event);echo '<tr><td><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$e->ID)).'">View / Reply #'.$e->ID.'</a><br>'.esc_html($e->post_date).'<br>';self::delete_submission_form($e->ID,true);echo '</td><td>'.esc_html(get_the_title($event)).'</td><td>';foreach($s['fields'] as $f)if(isset($data[$f['id']]))echo '<strong>'.esc_html($f['label']).':</strong> '.esc_html(is_array($data[$f['id']])?implode(', ',$data[$f['id']]):$data[$f['id']]).'<br>';echo '</td><td>';foreach($files as $field=>$attachments)foreach((array)$attachments as $attachment)echo '<a href="'.esc_url(wp_get_attachment_url((int)$attachment)).'" target="_blank" rel="noopener noreferrer">'.esc_html($field).' — '.esc_html(get_the_title((int)$attachment)).'</a><br>';echo '<br><strong>Appointment:</strong> '.esc_html(get_post_meta($e->ID,'_erm_appointment',true)).'<br><strong>Items:</strong> ';foreach((array)get_post_meta($e->ID,'_erm_items',true) as $item)echo esc_html($item['name'].' × '.$item['qty']).'<br>';echo '</td><td>'.esc_html(get_post_meta($e->ID,'_erm_method',true)).'<br>';self::status_form($e->ID,'payment',['not_required','unpaid','pending','partial','paid','refunded']);echo '</td><td>';self::status_form($e->ID,'registration',['submitted','confirmed','waitlisted','cancelled']);echo '</td></tr>';}echo '</tbody></table><p>Showing latest 100 entries. Export CSV for all entries.</p></div>';}

 static function appointment_manage_url($entry){
  $token=get_post_meta($entry,'_erm_customer_token',true);
  if(!is_string($token)||strlen($token)!==64){$token=bin2hex(random_bytes(32));update_post_meta($entry,'_erm_customer_token',$token);}
  return add_query_arg(['action'=>'erm_manage_appointment','entry'=>$entry,'token'=>$token],admin_url('admin-post.php'));
 }
 static function customer_appointment(){
  $entry=absint($_REQUEST['entry']??0);$token=sanitize_text_field(wp_unslash($_REQUEST['token']??''));
  $saved=(string)get_post_meta($entry,'_erm_customer_token',true);
  if(get_post_type($entry)!=='erm_entry'||strlen($saved)!==64||strlen($token)!==64||!hash_equals($saved,$token))wp_die('This appointment link is invalid.',403);
  $event=(int)get_post_meta($entry,'_erm_event',true);$s=self::settings($event);
  if($s['form_type']!=='appointment')wp_die('This entry is not an appointment.',400);
  header('Referrer-Policy: no-referrer');
  header('X-Robots-Tag: noindex, nofollow',true);
  $url=self::appointment_manage_url($entry);$status=(string)get_post_meta($entry,'_erm_registration_status',true);
  if($_SERVER['REQUEST_METHOD']==='POST'){
   if(!isset($_POST['erm_customer_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['erm_customer_nonce'])),'erm_customer_'.$entry))wp_die('Security check failed.',403);
   if($status==='cancelled')wp_die('Cancelled appointments cannot be modified.',400);
   $action=sanitize_key($_POST['appointment_action']??'');
   if($action==='cancel'){
    update_post_meta($entry,'_erm_registration_status','cancelled');
    self::appointment_email($entry,'cancelled');
    wp_safe_redirect(add_query_arg('result','cancelled',$url));exit;
   }
   if($action==='reschedule'){
    $date=sanitize_text_field(wp_unslash($_POST['date']??''));$time=sanitize_text_field(wp_unslash($_POST['time']??''));
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$time))wp_die('Invalid appointment date or time.',400);
    global $wpdb;$lock='erm_schedule_'.hash('sha256',(string)$event);
    if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)',$lock))!==1)wp_die('Scheduler busy. Try again.',409);
    try{
     self::$reschedule_entry=$entry;
     if(!in_array($time,self::slots_for($event,$date,$s),true))wp_die('That time is unavailable.',409);
     $old=(string)get_post_meta($entry,'_erm_appointment',true);
     update_post_meta($entry,'_erm_appointment',$date.' '.$time);
     update_post_meta($entry,'_erm_appointment_duration',(int)$s['appointment_duration']);
     update_post_meta($entry,'_erm_registration_status','pending');
    }finally{self::$reschedule_entry=0;$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    self::appointment_email($entry,'rescheduled',$old);
    wp_safe_redirect(add_query_arg('result','rescheduled',$url));exit;
   }
  }
  nocache_headers();
  $nonce=wp_create_nonce('erm_customer_'.$entry);
  $current=esc_html(get_post_meta($entry,'_erm_appointment',true));
  $dateText=(string)get_post_meta($entry,'_erm_appointment',true);
  $dateDisplay=$dateText;
  try{$dateDisplay=(new DateTimeImmutable($dateText,wp_timezone()))->format('l, F j, Y \\a\\t g:i A');}catch(Exception $e){}
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Manage appointment</title><link rel="stylesheet" href="'.esc_url(plugins_url('assets/appointment-design.css',__FILE__)).'?ver='.esc_attr(self::VERSION).'"></head><body class="erm-manage-page"><main class="erm-manage-shell"><header class="erm-manage-hero"><div class="erm-manage-eyebrow">Appointment center</div><h1>Manage your appointment</h1><p>'.esc_html(get_the_title($event)).'</p></header><div class="erm-manage-content"><div class="erm-manage-summary"><div><small>Requested appointment</small><strong>'.esc_html($dateDisplay).'</strong></div><div><small>Current status</small><strong class="erm-manage-status">'.esc_html($status).'</strong></div></div>';
  // Display only the fields belonging to this appointment's original form.
  $submitted=(array)get_post_meta($entry,'_erm_data',true);
  $mode=(string)get_post_meta($entry,'_erm_appointment_mode',true);
  $mode_labels=['studio'=>'In person — onsite','travel'=>'In person — at your location','online'=>'Online meeting'];
  echo '<section class="erm-manage-card erm-manage-details"><div class="erm-manage-section-heading"><div><span class="erm-manage-kicker">Your request</span><h2>Appointment details</h2></div><span class="erm-manage-reference">Reference #'.absint($entry).'</span></div><div class="erm-manage-details-grid">';
  if($mode!=='')echo '<div class="erm-manage-detail"><small>Meeting type</small><strong>'.esc_html($mode_labels[$mode]??ucfirst($mode)).'</strong></div>';
  $duration=(int)get_post_meta($entry,'_erm_appointment_duration',true);
  if($duration>0)echo '<div class="erm-manage-detail"><small>Duration</small><strong>'.esc_html($duration).' minutes</strong></div>';
  if($mode==='studio'&&!empty($s['appointment_studio_address']))echo '<div class="erm-manage-detail"><small>Location</small><strong>'.esc_html($s['appointment_studio_address']).'</strong></div>';
  if($mode==='travel'){$address=(array)get_post_meta($entry,'_erm_appointment_address',true);$address=array_filter(array_map('sanitize_text_field',$address));if($address)echo '<div class="erm-manage-detail"><small>Visit address</small><strong>'.esc_html(implode(', ',$address)).'</strong></div>';}
  if($mode==='online'){$platform=(string)get_post_meta($entry,'_erm_online_service',true);if($platform!=='')echo '<div class="erm-manage-detail"><small>Meeting platform</small><strong>'.esc_html(ucfirst($platform)).'</strong></div>';}
  echo '</div><h3>Information you submitted</h3><dl class="erm-manage-fields">';
  $displayed=0;
  foreach((array)($s['fields']??[]) as $field){
   $key=(string)($field['id']??'');if($key===''||!array_key_exists($key,$submitted))continue;
   $value=$submitted[$key];if(is_array($value))$value=implode(', ',array_map('sanitize_text_field',array_filter($value,'is_scalar')));
   if(!is_scalar($value)||trim((string)$value)==='')continue;
   $label=(string)($field['label']??$key);
   echo '<div><dt>'.esc_html($label).'</dt><dd>'.nl2br(esc_html((string)$value)).'</dd></div>';
   $displayed++;
  }
  if(!$displayed)echo '<div><dt>Details</dt><dd>No additional form fields were provided.</dd></div>';
  echo '</dl><p class="erm-manage-privacy">This information is visible only through your private appointment management link. Keep that link secure.</p></section>';
  if(isset($_GET['result']))echo '<p class="erm-manage-notice" role="status">Your appointment change has been recorded.</p>';
  if($status!=='cancelled'){
   echo '<section class="erm-manage-card"><h2>Request a different time</h2><p>Choose a new date and available start time. Your change will be reviewed before confirmation.</p><form method="post" action="'.esc_url($url).'"><input type="hidden" name="appointment_action" value="reschedule"><input type="hidden" name="erm_customer_nonce" value="'.esc_attr($nonce).'"><label for="erm-manage-date">New date</label><input id="erm-manage-date" type="date" name="date" min="'.esc_attr(wp_date('Y-m-d')).'" required><label for="erm-manage-time">Available start time</label><select id="erm-manage-time" name="time" required><option value="">Select a date first</option></select><p class="erm-slot-feedback" role="status"></p><button type="submit">Request reschedule</button></form></section>';
   echo '<section class="erm-manage-card erm-manage-danger"><h2>Need to cancel?</h2><p>Cancel this appointment request if you can no longer attend.</p><form method="post" action="'.esc_url($url).'"><input type="hidden" name="appointment_action" value="cancel"><input type="hidden" name="erm_customer_nonce" value="'.esc_attr($nonce).'"><button type="submit" onclick="return confirm(&quot;Cancel this appointment request?&quot;)">Cancel appointment</button></form></section>';
   echo '<script type="application/json" class="erm-appointment-settings">'.wp_json_encode(['event'=>$event,'nonce'=>wp_create_nonce('erm_slots_'.$event),'ajax'=>admin_url('admin-ajax.php')]).'</script>';
   echo '<script src="'.esc_url(plugins_url('assets/appointment-slots.js',__FILE__)).'?ver='.esc_attr(self::VERSION).'"></script>';
  }
  echo '</div></main>';
  echo '</body></html>';exit;
 }
 static function appointment_location_details($entry,$s,$kind){
  $mode=get_post_meta($entry,'_erm_appointment_mode',true);if(!$mode)return '';
  $labels=['studio'=>'In studio / onsite','travel'=>'At customer location','online'=>'Online meeting'];
  $body="\nAppointment location: ".($labels[$mode]??$mode)."\n";
  if($mode==='studio'&&!empty($s['appointment_studio_address']))$body.="Studio address: ".$s['appointment_studio_address']."\n";
  if($mode==='travel')$body.="Visit address: ".implode(', ',array_values((array)get_post_meta($entry,'_erm_appointment_address',true)))."\n";
  if($mode==='online'&&$kind!=='cancelled'){
   $global=(array)get_option('erm_appointment_integrations',[]);
   $service=(string)get_post_meta($entry,'_erm_online_service',true);
   if($service!==''&&!empty($global['meeting_links'][$service])){
    $body.="Platform: ".ucfirst($service)."\n";
    if($kind==='confirmed')$body.="Join meeting: ".$global['meeting_links'][$service]."\n";
    else $body.="Joining details will be provided after approval.\n";
    return $body;
   }
   $url=get_post_meta($entry,'_erm_meeting_url',true);
   if(!$url&&$kind==='confirmed'){$platform=$s['appointment_online_platform']??'';if($platform==='zoom')$url=self::create_zoom_meeting($entry,$s);elseif($platform==='teams')$url=self::create_teams_meeting($entry,$s);elseif($platform==='discord')$url=self::create_discord_invite($entry,$s);}
   if(!$url&&$kind==='confirmed')self::notify_meeting_failure($entry);
   if(!$url&&!in_array($s['appointment_online_platform']??'', ['zoom','teams','discord'],true))$url=$s['appointment_online_url']??'';
   $body.="Platform: ".ucfirst($s['appointment_online_platform']??'online')."\n";
   $body.=$url?"Join meeting: ".$url."\n":"Meeting link pending: the organizer has been notified.\n";
  }
  return $body;
 }
 static function meeting_creation_lock($entry){
  $key='erm_meeting_create_'.$entry;
  if(!add_option($key,(string)time(),'','no')){
   $age=(int)get_option($key,0);
   if($age>0&&time()-$age>120){delete_option($key);if(!add_option($key,(string)time(),'','no'))return false;}
   else return false;
  }
  register_shutdown_function(static function()use($key){delete_option($key);});
  return true;
 }
 static function notify_meeting_failure($entry){
  $error=(string)get_post_meta($entry,'_erm_meeting_error',true);
  if(!$error)return;
  $fingerprint=hash('sha256',$error);
  if(get_post_meta($entry,'_erm_meeting_alert_hash',true)===$fingerprint)return;
  $recipient=get_option('admin_email');
  if(is_email($recipient)){
   wp_mail($recipient,'Super Forms: meeting creation failed for appointment #'.$entry,
    "Appointment #".$entry." requires attention.\n".get_the_title((int)get_post_meta($entry,'_erm_event',true))."\n".$error."\n".admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$entry));
   update_post_meta($entry,'_erm_meeting_alert_hash',$fingerprint);
  }
 }
 static function meeting_warning($entry){
  $error=(string)get_post_meta($entry,'_erm_meeting_error',true);
  if($error)echo '<div class="notice notice-error inline"><p><strong>Online meeting needs attention:</strong> '.esc_html($error).'</p></div>';
 }
 static function create_zoom_meeting($entry,$s){
  if(get_post_meta($entry,'_erm_meeting_url',true))return get_post_meta($entry,'_erm_meeting_url',true);
  if(!self::meeting_creation_lock($entry))return (string)get_post_meta($entry,'_erm_meeting_url',true);
  foreach(['zoom_account_id','zoom_client_id','zoom_client_secret'] as $k)if(empty($s[$k])){update_post_meta($entry,'_erm_meeting_error','Zoom credentials are incomplete.');return '';}
  $token_response=wp_safe_remote_post('https://zoom.us/oauth/token',['timeout'=>15,'headers'=>['Authorization'=>'Basic '.base64_encode($s['zoom_client_id'].':'.$s['zoom_client_secret'])],'body'=>['grant_type'=>'account_credentials','account_id'=>$s['zoom_account_id']]]);
  if(is_wp_error($token_response)||wp_remote_retrieve_response_code($token_response)!==200)return '';
  $token=json_decode(wp_remote_retrieve_body($token_response),true);if(empty($token['access_token']))return '';
  $when=get_post_meta($entry,'_erm_appointment',true);try{$dt=new DateTimeImmutable($when,wp_timezone());}catch(Exception $e){return '';}
  $event=(int)get_post_meta($entry,'_erm_event',true);
  $payload=['topic'=>get_the_title($event).' — Appointment #'.$entry,'type'=>2,'start_time'=>$dt->format('Y-m-d\TH:i:s'),'timezone'=>wp_timezone_string(),'duration'=>(int)($s['appointment_duration']??30),'settings'=>['waiting_room'=>true]];
  $host=rawurlencode($s['zoom_user']??'me');
  $response=wp_safe_remote_post('https://api.zoom.us/v2/users/'.$host.'/meetings',['timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$token['access_token'],'Content-Type'=>'application/json'],'body'=>wp_json_encode($payload)]);
  if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==201){update_post_meta($entry,'_erm_meeting_error','Zoom meeting could not be created. Check credentials and API permissions.');return '';}
  $meeting=json_decode(wp_remote_retrieve_body($response),true);$url=esc_url_raw($meeting['join_url']??'');
  if($url){update_post_meta($entry,'_erm_meeting_url',$url);update_post_meta($entry,'_erm_meeting_id',sanitize_text_field((string)($meeting['id']??'')));delete_post_meta($entry,'_erm_meeting_error');delete_post_meta($entry,'_erm_meeting_alert_hash');}
  return $url;
 }
 static function create_teams_meeting($entry,$s){
  if(get_post_meta($entry,'_erm_meeting_url',true))return get_post_meta($entry,'_erm_meeting_url',true);
  if(!self::meeting_creation_lock($entry))return (string)get_post_meta($entry,'_erm_meeting_url',true);
  foreach(['teams_tenant_id','teams_client_id','teams_client_secret','teams_user_id'] as $key)if(empty($s[$key])){update_post_meta($entry,'_erm_meeting_error','Teams credentials are incomplete.');return '';}
  $tenant=rawurlencode($s['teams_tenant_id']);
  $token=wp_safe_remote_post('https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token',['timeout'=>20,'body'=>['client_id'=>$s['teams_client_id'],'client_secret'=>$s['teams_client_secret'],'scope'=>'https://graph.microsoft.com/.default','grant_type'=>'client_credentials']]);
  if(is_wp_error($token)||wp_remote_retrieve_response_code($token)!==200){update_post_meta($entry,'_erm_meeting_error','Microsoft Teams authentication failed.');return '';}
  $auth=json_decode(wp_remote_retrieve_body($token),true);if(empty($auth['access_token']))return '';
  $when=get_post_meta($entry,'_erm_appointment',true);try{$start=new DateTimeImmutable($when,wp_timezone());}catch(Exception $e){return '';}
  $end=$start->modify('+'.max(1,(int)($s['appointment_duration']??30)).' minutes');
  $event=(int)get_post_meta($entry,'_erm_event',true);
  $payload=['startDateTime'=>$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),'endDateTime'=>$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),'subject'=>get_the_title($event).' — Appointment #'.$entry];
  $endpoint='https://graph.microsoft.com/v1.0/users/'.rawurlencode($s['teams_user_id']).'/onlineMeetings';
  $response=wp_safe_remote_post($endpoint,['timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$auth['access_token'],'Content-Type'=>'application/json'],'body'=>wp_json_encode($payload)]);
  if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==201){update_post_meta($entry,'_erm_meeting_error','Teams meeting creation failed. Verify OnlineMeetings.ReadWrite.All application permission and the organizer application access policy.');return '';}
  $meeting=json_decode(wp_remote_retrieve_body($response),true);$url=esc_url_raw($meeting['joinWebUrl']??'');
  if($url){update_post_meta($entry,'_erm_meeting_url',$url);update_post_meta($entry,'_erm_meeting_id',sanitize_text_field($meeting['id']??''));update_post_meta($entry,'_erm_meeting_provider','teams');delete_post_meta($entry,'_erm_meeting_error');delete_post_meta($entry,'_erm_meeting_alert_hash');}
  return $url;
 }
 static function create_discord_invite($entry,$s){
  if(get_post_meta($entry,'_erm_meeting_url',true))return get_post_meta($entry,'_erm_meeting_url',true);
  if(!self::meeting_creation_lock($entry))return (string)get_post_meta($entry,'_erm_meeting_url',true);
  if(empty($s['discord_bot_token'])||!preg_match('/^[0-9]{10,25}$/',(string)($s['discord_channel_id']??''))){update_post_meta($entry,'_erm_meeting_error','Discord credentials are missing or invalid.');return '';}
  $endpoint='https://discord.com/api/v10/channels/'.$s['discord_channel_id'].'/invites';
  $response=wp_safe_remote_post($endpoint,['timeout'=>20,'headers'=>['Authorization'=>'Bot '.$s['discord_bot_token'],'Content-Type'=>'application/json'],'body'=>wp_json_encode(['max_age'=>604800,'max_uses'=>0,'temporary'=>false,'unique'=>true])]);
  if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200){update_post_meta($entry,'_erm_meeting_error','Discord invite creation failed. Verify bot access and Create Instant Invite permission.');return '';}
  $invite=json_decode(wp_remote_retrieve_body($response),true);$code=preg_replace('/[^A-Za-z0-9]/','',(string)($invite['code']??''));if(!$code)return '';
  $url='https://discord.gg/'.$code;update_post_meta($entry,'_erm_meeting_url',$url);update_post_meta($entry,'_erm_meeting_id',$code);update_post_meta($entry,'_erm_meeting_provider','discord');delete_post_meta($entry,'_erm_meeting_error');delete_post_meta($entry,'_erm_meeting_alert_hash');return $url;
 }
 static function appointment_email_design($title,$subtitle,$content,$cta_url='',$cta_label=''){
  $header='background:#38305f;background:linear-gradient(125deg,#29264e,#514180);color:#ffffff;padding:34px 32px;border-radius:14px 14px 0 0;';
  $html='<div style="background:#f2f4f9;padding:28px 12px;font-family:Arial,Helvetica,sans-serif;color:#26334a"><div style="max-width:640px;margin:auto;background:#ffffff;border:1px solid #e2e6f0;border-radius:14px;overflow:hidden">';
  $html.='<div style="'.$header.'"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:bold;color:#dcd6f1">Appointment center</div><h1 style="margin:10px 0 6px;font-size:27px;line-height:1.25;color:#ffffff">'.esc_html($title).'</h1><p style="margin:0;color:#e7e3f2;font-size:15px">'.esc_html($subtitle).'</p></div>';
  $html.='<div style="padding:30px 32px;font-size:15px;line-height:1.65"><div style="background:#f7f8fc;border:1px solid #e7eaf2;border-radius:10px;padding:20px 22px;overflow-wrap:anywhere">'.$content.'</div>';
  if($cta_url!=='')$html.='<p style="margin:26px 0 12px"><a href="'.esc_url($cta_url).'" style="display:inline-block;background:#594395;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:8px;font-weight:bold">'.esc_html($cta_label).'</a></p>';
  $html.='<p style="font-size:12px;color:#737e90;margin:26px 0 0">Please keep your appointment management link private.</p></div></div></div>';
  return $html;
 }
 static function appointment_email($entry,$kind,$old=''){if($kind==='cancelled'||($kind==='confirmed')||($kind==='rescheduled'&&get_post_meta($entry,'_erm_registration_status',true)==='confirmed'))ERM_Google_Calendar::sync($entry,$kind);
  $event=(int)get_post_meta($entry,'_erm_event',true);
  $data=(array)get_post_meta($entry,'_erm_data',true);$settings=self::settings($event);
  $email='';foreach($settings['fields'] as $f){if(($f['type']??'')==='email'&&is_email($data[$f['id']]??'')){$email=sanitize_email($data[$f['id']]);break;}}
  if(!$email&&is_email($data['email']??''))$email=sanitize_email($data['email']);
  $date=(string)get_post_meta($entry,'_erm_appointment',true);
  $titles=['confirmed'=>'Appointment confirmed','cancelled'=>'Appointment cancelled','rescheduled'=>'Appointment rescheduled'];
  $subject=($titles[$kind]??'Appointment update').' — '.get_the_title($event);
  $body="Regarding: ".get_the_title($event)."\nReference: #".$entry."\nStatus: ".$kind."\n";
  if($old!=='')$body.="Previous appointment: ".$old."\n";
  $body.="Appointment: ".$date."\nTimezone: ".wp_timezone_string()."\n";$body.=self::appointment_location_details($entry,$settings,$kind);
  if($kind!=='cancelled')$body.="Manage appointment: ".self::appointment_manage_url($entry)."\n";
  $body.="\nPlease contact the organizer if you have questions.";
  $body=self::appointment_email_design($titles[$kind]??'Appointment update',get_the_title($event),nl2br(esc_html($body)),$kind==='cancelled'?'':self::appointment_manage_url($entry),'Manage your appointment');
  $sent=$email?(bool)wp_mail($email,$subject,$body,['Content-Type: text/html; charset=UTF-8']):false;
  $history=(array)get_post_meta($entry,'_erm_appointment_history',true);
  $history[]=['date'=>current_time('mysql'),'action'=>$kind,'old'=>$old,'new'=>$date,'user'=>get_current_user_id(),'email_sent'=>$sent];
  update_post_meta($entry,'_erm_appointment_history',$history);
  return $sent;
 }
 static function reschedule(){
  if(!current_user_can('manage_options'))wp_die('Not authorized',403);
  $entry=absint($_POST['entry']??0);check_admin_referer('erm_reschedule_'.$entry);
  if(get_post_type($entry)!=='erm_entry')wp_die('Invalid appointment',400);
  $event=(int)get_post_meta($entry,'_erm_event',true);$s=self::settings($event);
  if($s['form_type']!=='appointment')wp_die('Not an appointment form',400);
  $date=sanitize_text_field(wp_unslash($_POST['date']??''));
  $time=sanitize_text_field(wp_unslash($_POST['time']??''));
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$time))wp_die('Invalid date or time',400);
  global $wpdb;$lock='erm_schedule_'.hash('sha256',(string)$event);
  if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)',$lock))!==1)wp_die('Scheduler is busy; retry.',409);
  try{
   self::$reschedule_entry=$entry;
   if(!in_array($time,self::slots_for($event,$date,$s),true))wp_die('Requested time is not available',409);
   $old=(string)get_post_meta($entry,'_erm_appointment',true);
   update_post_meta($entry,'_erm_appointment',$date.' '.$time);
   update_post_meta($entry,'_erm_appointment_duration',(int)$s['appointment_duration']);
   update_post_meta($entry,'_erm_registration_status','confirmed');
  }finally{self::$reschedule_entry=0;$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
  $sent=self::appointment_email($entry,'rescheduled',$old);
  wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$entry.'&appointment_result='.($sent?'sent':'saved')));exit;
 }
 static function payment_review_page(){
  if(!current_user_can('manage_options'))wp_die('Not authorized',403);
  $status=sanitize_key($_GET['status']??'needs_review');
  $allowed=['needs_review','all','paid','unpaid','pending','partial','refunded'];
  if(!in_array($status,$allowed,true))$status='needs_review';
  $entries=get_posts(['post_type'=>'erm_entry','post_status'=>'private','posts_per_page'=>-1,'orderby'=>'date','order'=>'DESC']);
  $counts=['all'=>0,'needs_review'=>0,'paid'=>0,'unpaid'=>0,'pending'=>0,'partial'=>0,'refunded'=>0];
  $rows=[];
  foreach($entries as $e){
   $id=$e->ID;$state=(string)get_post_meta($id,'_erm_payment_status',true);
   if($state==='not_required')continue;
   $counts['all']++;if(isset($counts[$state]))$counts[$state]++;
   if(in_array($state,['unpaid','pending','partial'],true))$counts['needs_review']++;
   if($status!=='all'&&($status==='needs_review'?!in_array($state,['unpaid','pending','partial'],true):$state!==$status))continue;
   $rows[]=$e;
  }
  echo '<div class="wrap"><h1>Payment Review</h1><p>Review submitted payments and manually record money received. External payment links are not verified automatically.</p><p>';
  foreach(['needs_review'=>'Needs review','all'=>'All','paid'=>'Paid','unpaid'=>'Unpaid','pending'=>'Pending','partial'=>'Partial','refunded'=>'Refunded'] as $key=>$label)
   echo '<a class="button '.($status===$key?'button-primary':'').'" style="margin:0 6px 8px 0" href="'.esc_url(add_query_arg('status',$key,admin_url('edit.php?post_type=erm_event&page=erm-payment-review'))).'">'.esc_html($label).' ('.absint($counts[$key]).')</a>';
  echo '</p><table class="widefat striped"><thead><tr><th>Submission</th><th>Form</th><th>Payment method</th><th>Status</th><th>Recorded amount</th><th>Last reference</th><th>Manage</th></tr></thead><tbody>';
  foreach($rows as $e){
   $id=$e->ID;$history=(array)get_post_meta($id,'_erm_payment_history',true);$last=$history?end($history):[];
   echo '<tr><td>#'.absint($id).'</td><td>'.esc_html(get_the_title(get_post_meta($id,'_erm_event',true))).'</td><td>'.esc_html(get_post_meta($id,'_erm_method',true)).'</td><td>'.esc_html(get_post_meta($id,'_erm_payment_status',true)).'</td><td>$'.esc_html(number_format((float)get_post_meta($id,'_erm_amount_received',true),2)).'</td><td>'.esc_html($last['reference']??'—').'</td><td><a class="button" href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$id)).'">Review</a></td></tr>';
  }
  if(!$rows)echo '<tr><td colspan="7">No submissions match this filter.</td></tr>';
  echo '</tbody></table></div>';
 }
 static function record_payment(){
  if(!current_user_can('manage_options'))wp_die('Not authorized',403);
  $entry=absint($_POST['entry']??0);check_admin_referer('erm_record_payment_'.$entry);
  if(get_post_type($entry)!=='erm_entry')wp_die('Invalid submission',400);
  $amount=filter_var(wp_unslash($_POST['amount']??''),FILTER_VALIDATE_FLOAT);
  if($amount===false||!is_finite((float)$amount)||$amount<0||$amount>10000000)wp_die('Enter a valid nonnegative amount.',400);
  $status=sanitize_key($_POST['status']??'');
  if(!in_array($status,['unpaid','pending','partial','paid','refunded'],true))wp_die('Invalid payment status',400);
  $due=(float)get_post_meta($entry,'_erm_amount',true);
  if($status==='paid'&&(float)$amount+0.001<$due)wp_die('The verified amount is less than the order total. Choose Partial until the balance is received.',400);
  $reference=substr(sanitize_text_field(wp_unslash($_POST['reference']??'')),0,120);
  $note=substr(sanitize_textarea_field(wp_unslash($_POST['note']??'')),0,1000);
  $previous=(string)get_post_meta($entry,'_erm_payment_status',true);
  $history=(array)get_post_meta($entry,'_erm_payment_history',true);
  $history[]=['date'=>current_time('mysql'),'user'=>get_current_user_id(),'status'=>$status,'previous_status'=>$previous,'amount'=>(float)$amount,'reference'=>$reference,'note'=>$note,'source'=>'manual'];
  update_post_meta($entry,'_erm_payment_history',$history);
  update_post_meta($entry,'_erm_amount_received',(float)$amount);
  update_post_meta($entry,'_erm_payment_status',$status);
  wp_safe_redirect(add_query_arg('payment_saved','1',admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$entry)));exit;
 }
 static function submission_attachments($entry,$settings){
  $files=get_post_meta($entry,'_erm_files',true);
  if(!is_array($files)||!$files)return;
  $labels=[];
  foreach((array)($settings['fields']??[]) as $field)if(is_array($field)&&isset($field['id']))$labels[$field['id']]=(string)($field['label']??$field['id']);
  echo '<section style="margin:22px 0;padding:18px;background:#fff;border:1px solid #c3c4c7"><h3 style="margin-top:0">Attached files and pictures</h3>';
  $count=0;
  foreach($files as $key=>$ids){
   if(!is_array($ids))$ids=[$ids];
   $cards='';
   foreach($ids as $id){
    $id=absint($id);
    if(!$id||get_post_type($id)!=='attachment')continue;
    $url=wp_get_attachment_url($id);
    if(!$url)continue;
    $name=get_the_title($id);
    if(!$name)$name=basename((string)get_attached_file($id));
    $mime=(string)get_post_mime_type($id);
    $cards.='<div style="width:160px;max-width:100%;padding:10px;border:1px solid #dcdcde;background:#fafafa;overflow-wrap:anywhere">';
    if(strpos($mime,'image/')===0){
     $thumb=wp_get_attachment_image($id,'thumbnail',false,['style'=>'display:block;width:140px;height:110px;object-fit:contain;margin-bottom:8px','alt'=>$name]);
     if($thumb)$cards.='<a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">'.$thumb.'</a>';
    }else $cards.='<div style="padding:20px 0;text-align:center"><span class="dashicons dashicons-media-document" style="font-size:36px;width:36px;height:36px"></span></div>';
    $cards.='<div><strong>'.esc_html($name).'</strong></div><p><a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">View file</a> · <a href="'.esc_url($url).'" download>Download</a></p></div>';
    $count++;
   }
   if($cards!=='')echo '<h4>'.esc_html($labels[$key]??$key).'</h4><div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px">'.$cards.'</div>';
  }
  if(!$count)echo '<p>No accessible attachments found for this submission.</p>';
  echo '</section>';
 }
 static function entry_detail($entry){
  if(!current_user_can('manage_options')||get_post_type($entry)!=='erm_entry')return;
  self::meeting_warning($entry);
  $event=(int)get_post_meta($entry,'_erm_event',true);$data=(array)get_post_meta($entry,'_erm_data',true);$settings=self::settings($event);$email='';
  foreach($settings['fields'] as $f){if(($f['type']??'')==='email'&&is_email($data[$f['id']]??'')){$email=sanitize_email($data[$f['id']]);break;}}
  if(!$email&&is_email($data['email']??''))$email=sanitize_email($data['email']);
  echo '<div class="wrap"><h2>Submission #'.absint($entry).' — '.esc_html(get_the_title($event)).'</h2><p><a href="'.esc_url(admin_url('edit.php?post_type=erm_event&page=erm-registrations&event='.$event)).'">← Back to submissions</a></p>';
  self::delete_submission_form($entry);
  $payment_status=(string)get_post_meta($entry,'_erm_payment_status',true);
  $registration_status=(string)get_post_meta($entry,'_erm_registration_status',true);
  $due=(float)get_post_meta($entry,'_erm_amount',true);
  $received=(float)get_post_meta($entry,'_erm_amount_received',true);
  echo '<div class="notice notice-info inline" style="padding:12px 18px"><p><strong>Registration:</strong> '.esc_html(ucfirst($registration_status?:'submitted')).' &nbsp; <strong>Payment:</strong> '.esc_html(ucfirst($payment_status?:'pending')).' &nbsp; <strong>Total:</strong> $'.esc_html(number_format($due,2)).' &nbsp; <strong>Received:</strong> $'.esc_html(number_format($received,2)).'</p></div>';
  foreach($settings['fields'] as $f){$v=$data[$f['id']]??'';echo '<p><strong>'.esc_html($f['label']).':</strong> '.esc_html(is_array($v)?implode(', ',$v):$v).'</p>';}
  self::submission_attachments($entry,$settings);
  echo '<p><strong>Appointment:</strong> '.esc_html(get_post_meta($entry,'_erm_appointment',true)).'</p>';if($settings['form_type']==='appointment'){echo '<h3>Appointment management</h3><p>Confirm or cancel using the Registration status control in the inbox. Rescheduling below validates the new time against your availability settings.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_reschedule"><input type="hidden" name="entry" value="'.absint($entry).'">';wp_nonce_field('erm_reschedule_'.$entry);echo '<label>New date <input type="date" name="date" required></label> <label>New start time <input type="time" name="time" required></label> <button class="button button-primary">Reschedule and notify</button></form>';if(isset($_GET['appointment_result']))echo '<p class="notice notice-info">Appointment saved. '.($_GET['appointment_result']==='sent'?'Notification email sent.':'Email delivery was not confirmed.').'</p>';echo '<h4>Appointment history</h4>';$hist=(array)get_post_meta($entry,'_erm_appointment_history',true);if(!$hist)echo '<p>No appointment changes yet.</p>';foreach(array_reverse($hist) as $h)echo '<p>'.esc_html(($h['date']??'').' — '.($h['action']??'').' — '.($h['old']??'').' → '.($h['new']??'')).'</p>';}
  echo '<div class="fcrc-payment-panel"><h3>Payment management</h3><p>Record the amount you have verified, the external payment reference, and any notes. This does not charge the customer or verify an external transaction.</p>';
  if(isset($_GET['payment_saved']))echo '<div class="notice notice-success"><p>Payment record saved.</p></div>';
  echo '<p><strong>Method:</strong> '.esc_html(get_post_meta($entry,'_erm_method',true)).' &nbsp; <strong>Status:</strong> '.esc_html(get_post_meta($entry,'_erm_payment_status',true)).'</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_record_payment"><input type="hidden" name="entry" value="'.absint($entry).'">';
  wp_nonce_field('erm_record_payment_'.$entry);
  echo '<p><label>Verified amount received (USD)<br><input type="number" name="amount" min="0" max="10000000" step="0.01" required value="'.esc_attr(number_format((float)get_post_meta($entry,'_erm_amount_received',true),2,'.','')).'"></label></p>';
  echo '<p><label>Payment status<br><select name="status">';
  foreach(['unpaid','pending','partial','paid','refunded'] as $state)echo '<option value="'.esc_attr($state).'" '.selected(get_post_meta($entry,'_erm_payment_status',true),$state,false).'>'.esc_html(ucfirst($state)).'</option>';
  echo '</select></label></p><p><label>Transaction/reference ID (optional)<br><input class="regular-text" name="reference" maxlength="120"></label></p><p><label>Internal notes<br><textarea class="large-text" name="note" rows="3"></textarea></label></p><button class="button button-primary">Save payment record</button></form>';
  $payments=(array)get_post_meta($entry,'_erm_payment_history',true);
  echo '<h4>Payment history</h4>';
  if(!$payments)echo '<p>No manual payment records yet.</p>';
  foreach(array_reverse($payments) as $item)echo '<div class="fcrc-payment-history"><strong>'.esc_html($item['date']??'').' — '.esc_html(ucfirst($item['status']??'')).'</strong><p>Recorded amount: $'.esc_html(number_format((float)($item['amount']??0),2)).' · Reference: '.esc_html($item['reference']??'—').'</p><p>'.esc_html($item['note']??'').'</p></div>';
  echo '</div>';
  self::ticket_admin_panel($entry);
  echo '<h3>Reply history</h3>';$history=(array)get_post_meta($entry,'_erm_reply_history',true);
  if(!$history)echo '<p>No replies sent yet.</p>';foreach($history as $h)echo '<div style="padding:12px;margin:8px 0;background:#fff;border-left:3px solid #777"><strong>'.esc_html($h['date']??'').' — '.esc_html($h['to']??'').'</strong><p>'.nl2br(esc_html($h['message']??'')).'</p></div>';
  if($email){echo '<h3>Send email reply</h3><p>To: <strong>'.esc_html($email).'</strong></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_reply"><input type="hidden" name="entry" value="'.absint($entry).'">';wp_nonce_field('erm_reply_'.$entry);echo '<p><label>Subject<br><input style="width:100%;max-width:700px" name="subject" required value="'.esc_attr('Re: '.get_the_title($event).' — Submission #'.$entry).'"></label></p><p><label>Message<br><textarea style="width:100%;max-width:700px" rows="8" name="message" required></textarea></label></p><button class="button button-primary">Send Reply</button></form>';}
  else echo '<p>No valid submitter email was supplied, so email reply is unavailable.</p>';
  if(isset($_GET['reply_result']))echo '<div class="notice '.($_GET['reply_result']==='sent'?'notice-success':'notice-error').'"><p>'.($_GET['reply_result']==='sent'?'Reply sent and recorded.':'Email delivery could not be confirmed; reply not recorded.').'</p></div>';
  echo '</div>';
 }
 static function reply(){
  if(!current_user_can('manage_options'))wp_die('Not authorized');$entry=absint($_POST['entry']??0);check_admin_referer('erm_reply_'.$entry);if(get_post_type($entry)!=='erm_entry')wp_die('Invalid entry');
  self::ticket_admin_panel($entry);$event=(int)get_post_meta($entry,'_erm_event',true);$data=(array)get_post_meta($entry,'_erm_data',true);$s=self::settings($event);$email='';foreach($s['fields'] as $f)if(($f['type']??'')==='email'&&is_email($data[$f['id']]??'')){$email=sanitize_email($data[$f['id']]);break;}if(!$email&&is_email($data['email']??''))$email=sanitize_email($data['email']);if(!$email)wp_die('No valid recipient email');
  $subject=sanitize_text_field(wp_unslash($_POST['subject']??''));$message=sanitize_textarea_field(wp_unslash($_POST['message']??''));if(!$subject||!$message)wp_die('Subject and message required');
  $sent=wp_mail($email,$subject,$message);if($sent){$history=(array)get_post_meta($entry,'_erm_reply_history',true);$history[]=['date'=>current_time('mysql'),'to'=>$email,'subject'=>$subject,'message'=>$message,'user'=>get_current_user_id()];update_post_meta($entry,'_erm_reply_history',$history);}
  wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-registrations&entry='.$entry.'&reply_result='.($sent?'sent':'failed')));exit;
 }
 static function registration_confirmation_email($entry){
  $event=(int)get_post_meta($entry,'_erm_event',true);
  $settings=self::settings($event);
  $data=(array)get_post_meta($entry,'_erm_data',true);
  $email='';
  foreach((array)$settings['fields'] as $field){
   if(($field['type']??'')==='email'&&is_email($data[$field['id']]??'')){$email=sanitize_email($data[$field['id']]);break;}
  }
  if(!$email&&is_email($data['email']??''))$email=sanitize_email($data['email']);
  if(!$email)return false;
  $body="Your registration/order for ".get_the_title($event)." has been confirmed.\nReference: #".$entry."\nPayment status: ".get_post_meta($entry,'_erm_payment_status',true)."\n";
  $tickets=(array)get_post_meta($entry,'_erm_tickets',true);
  if($tickets){$body.="\nYour tickets:\n";foreach($tickets as $ticket)if(is_array($ticket)&&!empty($ticket['code']))$body.=($ticket['label']??'Ticket').': '.self::ticket_url($ticket['code'])."\n";}
  return wp_mail($email,'Registration confirmed — '.get_the_title($event).' (#'.$entry.')',$body);
 }
 static function entry_payment_verified($id){
  $method=(string)get_post_meta($id,'_erm_method',true);
  $due=(float)get_post_meta($id,'_erm_amount',true);
  if($due<=0)return true;
  return get_post_meta($id,'_erm_payment_status',true)==='paid'&&(float)get_post_meta($id,'_erm_amount_received',true)+0.001 >= $due;
 }
 static function status_form($id,$kind,$choices){$current=get_post_meta($id,'_erm_'.$kind.'_status',true);echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="erm_status"><input type="hidden" name="entry" value="'.$id.'"><input type="hidden" name="kind" value="'.esc_attr($kind).'">'.wp_nonce_field('erm_status_'.$id,'erm_nonce',true,false).'<select name="value">';foreach($choices as $c)echo '<option value="'.esc_attr($c).'" '.selected($c,$current,false).'>'.esc_html(ucwords(str_replace('_',' ',$c))).'</option>';echo '</select> <button class="button">Save</button></form>';}
 static function status(){if(!current_user_can('manage_options'))wp_die('Not authorized');$id=absint($_POST['entry']??0);check_admin_referer('erm_status_'.$id,'erm_nonce');if(get_post_type($id)!=='erm_entry')wp_die('Invalid registration');$kind=sanitize_key($_POST['kind']??'');$value=sanitize_key($_POST['value']??'');$valid=['payment'=>['not_required','unpaid','pending','partial','paid','refunded'],'registration'=>['submitted','confirmed','waitlisted','cancelled']];if(isset($valid[$kind])&&in_array($value,$valid[$kind],true)){if($kind==='payment'&&$value==='paid')wp_die('Use the Payment management form to record the verified amount and reference before marking an order paid.',403);$previous=get_post_meta($id,'_erm_'.$kind.'_status',true);if($kind==='registration'&&$value==='confirmed'&&!self::entry_payment_verified($id))wp_die('Payment has not been verified. Record full payment first, or use a no-payment form.',403);if($kind==='registration'&&$previous!==$value&&in_array($value,['confirmed','cancelled'],true)){update_post_meta($id,'_erm_'.$kind.'_status',$value);if(get_post_meta($id,'_erm_appointment',true)!=='')self::appointment_email($id,$value);elseif($value==='confirmed')self::registration_confirmation_email($id);}else {update_post_meta($id,'_erm_'.$kind.'_status',$value);if($kind==='payment'&&$previous!==$value){$history=(array)get_post_meta($id,'_erm_payment_history',true);$history[]=['date'=>current_time('mysql'),'user'=>get_current_user_id(),'status'=>$value,'previous_status'=>$previous,'amount'=>(float)get_post_meta($id,'_erm_amount_received',true),'reference'=>'','note'=>'Status updated from submissions list','source'=>'manual'];update_post_meta($id,'_erm_payment_history',$history);}}}wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-registrations'));exit;}
 static function export(){if(!current_user_can('manage_options'))wp_die('Not authorized');check_admin_referer('erm_export');$event=absint($_GET['event']??0);$args=['post_type'=>'erm_entry','post_status'=>'private','posts_per_page'=>-1];if($event)$args['meta_query']=[['key'=>'_erm_event','value'=>$event]];$entries=get_posts($args);$keys=[];foreach($entries as $e)foreach(array_keys((array)get_post_meta($e->ID,'_erm_data',true)) as $key)$keys[$key]=true;header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="event-registrations.csv"');$o=fopen('php://output','w');fputcsv($o,array_merge(['ID','Event','Date','Payment Method','Payment Status','Registration Status'],array_keys($keys)));foreach($entries as $e){$d=(array)get_post_meta($e->ID,'_erm_data',true);$row=[$e->ID,get_the_title(get_post_meta($e->ID,'_erm_event',true)),$e->post_date,get_post_meta($e->ID,'_erm_method',true),get_post_meta($e->ID,'_erm_payment_status',true),get_post_meta($e->ID,'_erm_registration_status',true)];foreach(array_keys($keys) as $k){$v=$d[$k]??'';$v=is_array($v)?implode(', ',$v):$v;if(preg_match('/^[=+\-@\t\r]/',(string)$v))$v="'".$v;$row[]=$v;}fputcsv($o,$row);}fclose($o);exit;}
 static function public_list($atts){$a=shortcode_atts(['id'=>0],$atts);$id=absint($a['id']);if(get_post_type($id)!=='erm_event'||get_post_status($id)!=='publish')return '';$s=self::settings($id);if(empty($s['public_fields']))return '<p>No public registration fields enabled.</p>';$entries=get_posts(['post_type'=>'erm_entry','post_status'=>'private','posts_per_page'=>100,'meta_query'=>[['key'=>'_erm_event','value'=>$id]]]);$out='<table><thead><tr>';foreach($s['fields'] as $f)if(in_array($f['id'],$s['public_fields'],true))$out.='<th>'.esc_html($f['label']).'</th>';$out.='</tr></thead><tbody>';foreach($entries as $e){if(get_post_meta($e->ID,'_erm_registration_status',true)==='cancelled')continue;$d=(array)get_post_meta($e->ID,'_erm_data',true);$out.='<tr>';foreach($s['fields'] as $f)if(in_array($f['id'],$s['public_fields'],true)){$v=$d[$f['id']]??'';$out.='<td>'.esc_html(is_array($v)?implode(', ',$v):$v).'</td>';}$out.='</tr>';}$out.='</tbody></table>';return $out;}
}
ERM_Plugin::init();
