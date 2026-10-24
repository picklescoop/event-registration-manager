<?php
if(!defined('ABSPATH'))exit;
/**
 * Microsoft 365 calendar availability using Microsoft Graph application permissions.
 * Admin configures an Entra application with Calendars.Read (application) and admin consent.
 */
final class ERM_Microsoft_Calendar{
 static function init(){add_action('admin_post_erm_ms_connect',[__CLASS__,'connect']);add_action('admin_post_erm_ms_callback',[__CLASS__,'callback']);add_action('admin_post_erm_ms_disconnect',[__CLASS__,'disconnect']);}
 static function redirect_uri(){return admin_url('admin-post.php?action=erm_ms_callback');}
 static function back(){return admin_url('edit.php?post_type=erm_event&page=erm-appointment-integrations');}
 static function connect(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('erm_ms_connect');$c=self::config();if(empty($c['client_id'])||empty($c['client_secret']))wp_die('Save Microsoft application credentials before connecting.');
  $state=wp_generate_password(40,false,false);set_transient('erm_ms_state_'.get_current_user_id(),$state,10*MINUTE_IN_SECONDS);
  $args=['client_id'=>$c['client_id'],'response_type'=>'code','redirect_uri'=>self::redirect_uri(),'response_mode'=>'query','scope'=>'offline_access User.Read Calendars.Read','state'=>$state,'prompt'=>'select_account'];
  wp_redirect(add_query_arg($args,'https://login.microsoftonline.com/common/oauth2/v2.0/authorize'));exit;
 }
 static function callback(){
  if(!current_user_can('manage_options'))wp_die('Forbidden',403);$state=sanitize_text_field(wp_unslash($_GET['state']??''));$expected=get_transient('erm_ms_state_'.get_current_user_id());delete_transient('erm_ms_state_'.get_current_user_id());
  if(!$expected||!hash_equals($expected,$state)||empty($_GET['code']))wp_die('Microsoft authorization was cancelled or expired.',403);
  $c=self::config();$response=wp_safe_remote_post('https://login.microsoftonline.com/common/oauth2/v2.0/token',['timeout'=>15,'body'=>['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'code'=>sanitize_text_field(wp_unslash($_GET['code'])),'redirect_uri'=>self::redirect_uri(),'grant_type'=>'authorization_code','scope'=>'offline_access User.Read Calendars.Read']]);
  if(is_wp_error($response))wp_die(esc_html($response->get_error_message()));$data=json_decode(wp_remote_retrieve_body($response),true);
  if(empty($data['access_token'])||empty($data['refresh_token']))wp_die('Microsoft did not return the tokens required for calendar access. Check app account types, permissions and redirect URI.');
  $c['refresh_token']=$data['refresh_token'];$c['access_token']=$data['access_token'];$c['expires_at']=time()+max(0,(int)($data['expires_in']??3600)-120);$c['enabled']=true;$saved=ERM_Secret_Store::write('erm_microsoft_calendar',$c,['client_secret','refresh_token','access_token']);if(is_wp_error($saved))return $saved;wp_safe_redirect(self::back());exit;
 }
 static function disconnect(){if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('erm_ms_disconnect');$c=self::config();unset($c['refresh_token'],$c['access_token'],$c['expires_at']);$c['enabled']=false;$saved=ERM_Secret_Store::write('erm_microsoft_calendar',$c,['client_secret','refresh_token','access_token']);if(is_wp_error($saved))return $saved;wp_safe_redirect(self::back());exit;}

 static function config(){return ERM_Secret_Store::read('erm_microsoft_calendar',['client_secret','refresh_token','access_token']);}
 static function enabled(){ $c=self::config();return !empty($c['enabled'])&&!empty($c['client_id'])&&!empty($c['client_secret'])&&(!empty($c['refresh_token'])||(!empty($c['tenant_id'])&&!empty($c['user_id'])));}
 static function token(){
  $c=self::config();if(!self::enabled())return new WP_Error('ms_not_connected','Microsoft calendar connection is not configured.');
  if(!empty($c['refresh_token'])){
   if(!empty($c['access_token'])&&($c['expires_at']??0)>time())return $c['access_token'];
   $response=wp_safe_remote_post('https://login.microsoftonline.com/common/oauth2/v2.0/token',['timeout'=>15,'body'=>['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'refresh_token'=>$c['refresh_token'],'grant_type'=>'refresh_token','scope'=>'offline_access User.Read Calendars.Read']]);
   if(is_wp_error($response))return $response;$data=json_decode(wp_remote_retrieve_body($response),true);if(empty($data['access_token']))return new WP_Error('ms_refresh_failed','Reconnect Microsoft calendar: authorization has expired.');
   $c['access_token']=$data['access_token'];if(!empty($data['refresh_token']))$c['refresh_token']=$data['refresh_token'];$c['expires_at']=time()+max(0,(int)($data['expires_in']??3600)-120);$saved=ERM_Secret_Store::write('erm_microsoft_calendar',$c,['client_secret','refresh_token','access_token']);if(is_wp_error($saved))return $saved;return $c['access_token'];
  }
  $cache='erm_ms_token_'.md5($c['tenant_id'].'|'.$c['client_id']);$cached=get_transient($cache);if(is_string($cached)&&$cached!=='')return $cached;
  $url='https://login.microsoftonline.com/'.rawurlencode($c['tenant_id']).'/oauth2/v2.0/token';
  $response=wp_safe_remote_post($url,['timeout'=>15,'body'=>['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'scope'=>'https://graph.microsoft.com/.default','grant_type'=>'client_credentials']]);
  if(is_wp_error($response))return $response;
  $data=json_decode(wp_remote_retrieve_body($response),true);
  if(wp_remote_retrieve_response_code($response)!==200||empty($data['access_token']))return new WP_Error('ms_token_failed','Microsoft Graph authentication failed. Check Entra application permissions and credentials.');
  set_transient($cache,$data['access_token'],max(60,(int)($data['expires_in']??3600)-120));return $data['access_token'];
 }
 static function events($date){
  $c=self::config();$token=self::token();if(is_wp_error($token))return $token;
  try{$day=new DateTimeImmutable($date.' 00:00:00',wp_timezone());}catch(Exception $e){return new WP_Error('bad_date','Invalid date');}
  $start=$day->format('Y-m-d\TH:i:sP');$end=$day->modify('+1 day')->format('Y-m-d\TH:i:sP');
  $calendar_path=!empty($c['refresh_token'])?'me':'users/'.rawurlencode($c['user_id']);
  $url='https://graph.microsoft.com/v1.0/'.$calendar_path.'/calendarView?'.http_build_query(['startDateTime'=>$start,'endDateTime'=>$end,'$top'=>100],'','&',PHP_QUERY_RFC3986);
  $items=[];$pages=0;
  do{
   $response=wp_safe_remote_get($url,['timeout'=>15,'headers'=>['Authorization'=>'Bearer '.$token,'Prefer'=>'outlook.timezone="UTC"']]);
   if(is_wp_error($response))return $response;
   $body=json_decode(wp_remote_retrieve_body($response),true);
   if(wp_remote_retrieve_response_code($response)!==200||!is_array($body))return new WP_Error('ms_calendar_error','Unable to retrieve Microsoft calendar events.');
   $items=array_merge($items,(array)($body['value']??[]));$url=(string)($body['@odata.nextLink']??'');
   if($url!==''&&strpos($url,'https://graph.microsoft.com/v1.0/')!==0)return new WP_Error('ms_pagination_error','Unexpected Microsoft Graph pagination URL.');
  }while($url!==''&&++$pages<10);
  return $items;
 }
 static function ranges($date,$keyword=''){
  $events=self::events($date);if(is_wp_error($events))return $events;$ranges=[];
  foreach($events as $item){
   if(!empty($item['isCancelled'])||!empty($item['isAllDay']))continue;
   if($keyword===''&&($item['showAs']??'busy')==='free')continue;
   if($keyword!==''&&stripos((string)($item['subject']??''),$keyword)===false)continue;
   $a=$item['start']['dateTime']??'';$b=$item['end']['dateTime']??'';
   if(!$a||!$b)continue;
   // Prefer: outlook.timezone="UTC" requests UTC event values.
   try{$from=(new DateTimeImmutable($a,new DateTimeZone('UTC')))->getTimestamp();$to=(new DateTimeImmutable($b,new DateTimeZone('UTC')))->getTimestamp();if($to>$from)$ranges[]=[$from,$to];}catch(Exception $e){}
  }
  return $ranges;
 }
}
