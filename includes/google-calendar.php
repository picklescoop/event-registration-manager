<?php
if(!defined('ABSPATH'))exit;
final class ERM_Google_Calendar{
 const OPTION='erm_google_oauth';
 static function init(){
  add_action('admin_menu',[__CLASS__,'menu']);
  add_action('admin_post_erm_google_save',[__CLASS__,'save']);
  add_action('admin_post_erm_google_start',[__CLASS__,'start']);
  add_action('admin_post_erm_google_callback',[__CLASS__,'callback']);
  add_action('admin_post_erm_google_disconnect',[__CLASS__,'disconnect']);
  add_action('erm_google_retry_entry',[__CLASS__,'retry_entry'],10,1);
  add_action('admin_post_erm_google_retry',[__CLASS__,'manual_retry']);
 }
 static function menu(){add_submenu_page('edit.php?post_type=erm_event','Google Calendar','Google Calendar','manage_options','erm-google-calendar',[__CLASS__,'page']);}
 static function config(){return (array)get_option(self::OPTION,[]);}
 static function redirect_uri(){return admin_url('admin-post.php?action=erm_google_callback');}
 static function page(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);
  $o=self::config();
  echo '<div class="wrap"><h1>Google Calendar connection</h1><p>Connect a Google account to check busy times and synchronize confirmed appointments. Create a Google Cloud OAuth client (Web application), enable Google Calendar API, and add this redirect URI:</p><code>'.esc_html(self::redirect_uri()).'</code>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_google_save">';
  wp_nonce_field('erm_google_save');
  echo '<p><label>OAuth client ID<br><input class="regular-text" name="client_id" value="'.esc_attr($o['client_id']??'').'"></label></p><p><label>OAuth client secret<br><input class="regular-text" type="password" name="client_secret" autocomplete="new-password" placeholder="'.(!empty($o['client_secret'])?'Saved; leave blank to keep':'Enter secret').'"></label></p><button class="button button-primary">Save OAuth credentials</button></form>';
  if(!empty($o['refresh_token']))echo '<p><strong>Connected.</strong> A refresh token is saved in WordPress options.</p>';
  elseif(!empty($o['client_id']))echo '<p>Credentials saved. Connect the Google account next.</p>';
  if(!empty($o['client_id']))echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=erm_google_start'),'erm_google_start')).'">Connect / Reauthorize Google</a></p>';
  if(!empty($o['refresh_token']))echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=erm_google_disconnect'),'erm_google_disconnect')).'">Disconnect</a></p>';
  $entries=get_posts(['post_type'=>'erm_entry','post_status'=>'private','numberposts'=>100,'orderby'=>'modified','order'=>'DESC','meta_query'=>[['key'=>'_erm_google_sync','compare'=>'EXISTS']]]);
  echo '<h2>Recent appointment synchronization</h2><p>Errors can be retried after correcting credentials or calendar permissions. Automatic retries use WordPress scheduled tasks (WP-Cron).</p><table class="widefat striped"><thead><tr><th>Booking</th><th>Status</th><th>Last attempt</th><th>Details</th><th>Action</th></tr></thead><tbody>';
  foreach($entries as $entry){
   $sync=get_post_meta($entry->ID,'_erm_google_sync',true);if(!is_array($sync))continue;
   $retry=(int)get_post_meta($entry->ID,'_erm_google_retry_count',true);
   echo '<tr><td>#'.absint($entry->ID).'</td><td>'.esc_html($sync['status']??'unknown').'</td><td>'.esc_html($sync['time']??'—').'</td><td>'.esc_html($sync['message']??'—').' (retries: '.absint($retry).')</td><td>';
   echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="erm_google_retry"><input type="hidden" name="entry" value="'.absint($entry->ID).'">';
   wp_nonce_field('erm_google_retry_'.$entry->ID);
   echo '<button class="button">Retry sync</button></form></td></tr>';
  }
  if(!$entries)echo '<tr><td colspan="5">No synchronization attempts recorded yet.</td></tr>';
  echo '</tbody></table>';
  if(isset($_GET['retried']))echo '<div class="notice notice-info"><p>Synchronization retried. Review the updated status below.</p></div>';
  echo '<p>Use a calendar ID such as <code>primary</code> in each appointment form. Confirmed appointments sync to the selected calendar. A failed Google API call must be resolved before assuming an appointment is on the calendar.</p></div>';
 }
 static function save(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('erm_google_save');
  $o=self::config();$o['client_id']=sanitize_text_field(wp_unslash($_POST['client_id']??''));
  $secret=trim((string)wp_unslash($_POST['client_secret']??''));if($secret!=='')$o['client_secret']=$secret;
  update_option(self::OPTION,$o,false);
  wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-google-calendar'));exit;
 }
 static function start(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('erm_google_start');
  $o=self::config();if(empty($o['client_id'])||empty($o['client_secret']))wp_die('Configure OAuth credentials first.');
  $state=wp_generate_password(40,false,false);set_transient('erm_google_state_'.get_current_user_id(),$state,10*MINUTE_IN_SECONDS);
  $args=['client_id'=>$o['client_id'],'redirect_uri'=>self::redirect_uri(),'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.freebusy','access_type'=>'offline','prompt'=>'consent','state'=>$state];
  wp_redirect(add_query_arg($args,'https://accounts.google.com/o/oauth2/v2/auth'));exit;
 }
 static function callback(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);
  $state=sanitize_text_field(wp_unslash($_GET['state']??''));$expected=get_transient('erm_google_state_'.get_current_user_id());
  delete_transient('erm_google_state_'.get_current_user_id());
  if(!$expected||!hash_equals($expected,$state)||empty($_GET['code']))wp_die('Google authorization failed or expired.',403);
  $o=self::config();$response=wp_safe_remote_post('https://oauth2.googleapis.com/token',['timeout'=>15,'body'=>['code'=>sanitize_text_field(wp_unslash($_GET['code'])),'client_id'=>$o['client_id'],'client_secret'=>$o['client_secret'],'redirect_uri'=>self::redirect_uri(),'grant_type'=>'authorization_code']]);
  if(is_wp_error($response))wp_die(esc_html($response->get_error_message()));
  $data=json_decode(wp_remote_retrieve_body($response),true);
  if(empty($data['refresh_token']))wp_die('Google did not return a refresh token. Reconnect with consent.');
  $o['refresh_token']=$data['refresh_token'];$o['access_token']=$data['access_token']??'';$o['expires_at']=time()+max(0,(int)($data['expires_in']??3600)-90);
  update_option(self::OPTION,$o,false);
  wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-google-calendar'));exit;
 }
 static function disconnect(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('erm_google_disconnect');
  $o=self::config();unset($o['refresh_token'],$o['access_token'],$o['expires_at']);update_option(self::OPTION,$o,false);
  wp_safe_redirect(admin_url('edit.php?post_type=erm_event&page=erm-google-calendar'));exit;
 }
 static function token(){
  $o=self::config();if(empty($o['refresh_token']))return new WP_Error('google_not_connected','Google Calendar is not connected.');
  if(!empty($o['access_token'])&&($o['expires_at']??0)>time())return $o['access_token'];
  $response=wp_safe_remote_post('https://oauth2.googleapis.com/token',['timeout'=>12,'body'=>['client_id'=>$o['client_id'],'client_secret'=>$o['client_secret'],'refresh_token'=>$o['refresh_token'],'grant_type'=>'refresh_token']]);
  if(is_wp_error($response))return $response;
  $data=json_decode(wp_remote_retrieve_body($response),true);
  if(empty($data['access_token']))return new WP_Error('google_refresh_failed','Unable to refresh Google access token.');
  $o['access_token']=$data['access_token'];$o['expires_at']=time()+max(0,(int)($data['expires_in']??3600)-90);update_option(self::OPTION,$o,false);
  return $o['access_token'];
 }
 static function api($method,$path,$body=null){
  $token=self::token();if(is_wp_error($token))return $token;
  $args=['method'=>$method,'timeout'=>12,'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'],'redirection'=>0];
  if($body!==null)$args['body']=wp_json_encode($body);
  $response=wp_safe_remote_request('https://www.googleapis.com/calendar/v3/'.$path,$args);
  if(is_wp_error($response))return $response;
  $status=wp_remote_retrieve_response_code($response);$data=json_decode(wp_remote_retrieve_body($response),true);
  if($status<200||$status>=300)return new WP_Error('google_api_error','Google Calendar API error HTTP '.$status,['status'=>$status,'details'=>$data]);
  return is_array($data)?$data:[];
 }
 static function retry_entry($entry){
  $entry=absint($entry);if(get_post_type($entry)!=='erm_entry')return;
  $event=absint(get_post_meta($entry,'_erm_event',true));if(!$event)return;
  $settings=ERM_Plugin::settings($event);if(($settings['form_type']??'')!=='appointment')return;
  $state=(string)get_post_meta($entry,'_erm_appointment_status',true);
  if(!in_array($state,['confirmed','cancelled'],true))return;
  self::sync($entry,$state==='cancelled'?'cancelled':'confirmed');
 }
 static function manual_retry(){
  if(!current_user_can('manage_options'))wp_die('Not authorized',403);
  $entry=absint($_POST['entry']??0);check_admin_referer('erm_google_retry_'.$entry);
  if(get_post_type($entry)!=='erm_entry')wp_die('Invalid booking',400);
  delete_post_meta($entry,'_erm_google_retry_count');
  self::retry_entry($entry);
  wp_safe_redirect(add_query_arg('retried','1',admin_url('edit.php?post_type=erm_event&page=erm-google-calendar')));exit;
 }
 static function calendar_id($settings){return rawurlencode($settings['google_calendar_id']??'primary');}
 static function busy($event,$date,$settings){
  if(empty(self::config()['refresh_token']))return [];
  $tz=wp_timezone();$day=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$tz);
  if(!$day)return new WP_Error('bad_date','Invalid date');
  $configured=get_option('erm_appointment_integrations',[]);
  $ids=is_array($configured)&&!empty($configured['calendar_ids'])?(array)$configured['calendar_ids']:[];
  if(!$ids)$ids=[(string)($settings['google_calendar_id']??'primary')];
  $ids=array_values(array_unique(array_filter(array_map('trim',$ids),'strlen')));
  if(!$ids)return [];
  $response=self::api('POST','freeBusy',['timeMin'=>$day->format(DateTimeInterface::RFC3339),'timeMax'=>$day->modify('+1 day')->format(DateTimeInterface::RFC3339),'timeZone'=>wp_timezone_string(),'items'=>array_map(static function($id){return ['id'=>$id];},$ids)]);
  if(is_wp_error($response))return $response;
  $ranges=[];
  foreach($ids as $id){
   $cal=$response['calendars'][$id]??null;
   if(!$cal||!empty($cal['errors']))return new WP_Error('calendar_unavailable','Google Calendar availability unavailable for one or more selected calendars.');
   foreach($cal['busy']??[] as $item){
    try{$ranges[]=[(new DateTimeImmutable($item['start']))->getTimestamp(),(new DateTimeImmutable($item['end']))->getTimestamp()];}catch(Exception $e){}
   }
  }
  return $ranges;
 }
 static function sync($entry,$kind){
  $event=(int)get_post_meta($entry,'_erm_event',true);$settings=ERM_Plugin::settings($event);
  if($settings['form_type']!=='appointment')return;
  if(empty(self::config()['refresh_token'])){$result=new WP_Error('google_not_connected','Google Calendar is not connected.');self::record_sync($entry,$result,$kind);return;}
  $id=(string)get_post_meta($entry,'_erm_google_event_id',true);
  $calendar=self::calendar_id($settings);$path='calendars/'.$calendar.'/events';
  if($kind==='cancelled'){
   if(!$id){self::record_sync($entry,[], $kind);return;}
   $result=self::api('DELETE',$path.'/'.rawurlencode($id));
   if(!is_wp_error($result))delete_post_meta($entry,'_erm_google_event_id');
  }else{
   $date=(string)get_post_meta($entry,'_erm_appointment',true);
   try{$start=new DateTimeImmutable($date,wp_timezone());}catch(Exception $e){self::record_sync($entry,new WP_Error('bad_appointment_date','Invalid appointment date'),$kind);return;}
   $duration=max(1,(int)get_post_meta($entry,'_erm_appointment_duration',true));
   $payload=['summary'=>get_the_title($event).' — Appointment #'.$entry,'description'=>'Booking reference #'.$entry,'start'=>['dateTime'=>$start->format(DateTimeInterface::RFC3339)],'end'=>['dateTime'=>$start->modify('+'.$duration.' minutes')->format(DateTimeInterface::RFC3339)]];
   $result=self::api($id?'PATCH':'POST',$id?$path.'/'.rawurlencode($id):$path,$payload);
   if(!is_wp_error($result)&&!empty($result['id']))update_post_meta($entry,'_erm_google_event_id',$result['id']);
  }
  self::record_sync($entry,$result,$kind);
 }
 static function record_sync($entry,$result,$kind){
  $args=[$entry];$next=wp_next_scheduled('erm_google_retry_entry',$args);
  if(is_wp_error($result)){
   $count=(int)get_post_meta($entry,'_erm_google_retry_count',true)+1;
   update_post_meta($entry,'_erm_google_retry_count',$count);
   $error=['status'=>'error','message'=>$result->get_error_message(),'time'=>current_time('mysql'),'attempts'=>$count];
   update_post_meta($entry,'_erm_google_sync',$error);
   // Stop after five retries; allow administrators to restart manually.
   if($count<=5&&!$next){
    $delays=[300,900,3600,10800,21600];
    wp_schedule_single_event(time()+$delays[min($count-1,4)],'erm_google_retry_entry',$args);
   }
  }else{
   update_post_meta($entry,'_erm_google_retry_count',0);
   update_post_meta($entry,'_erm_google_sync',['status'=>'ok','time'=>current_time('mysql'),'operation'=>$kind]);
   if($next)wp_clear_scheduled_hook('erm_google_retry_entry',$args);
  }
 }
}
