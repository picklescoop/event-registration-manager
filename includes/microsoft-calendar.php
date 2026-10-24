<?php
if(!defined('ABSPATH'))exit;
/**
 * Microsoft 365 calendar availability using Microsoft Graph application permissions.
 * Admin configures an Entra application with Calendars.Read (application) and admin consent.
 */
final class ERM_Microsoft_Calendar{
 static function config(){return (array)get_option('erm_microsoft_calendar',[]);}
 static function enabled(){ $c=self::config();return !empty($c['enabled'])&&!empty($c['tenant_id'])&&!empty($c['client_id'])&&!empty($c['client_secret'])&&!empty($c['user_id']);}
 static function token(){
  $c=self::config();if(!self::enabled())return new WP_Error('ms_not_connected','Microsoft calendar connection is not configured.');
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
  $url='https://graph.microsoft.com/v1.0/users/'.rawurlencode($c['user_id']).'/calendarView?'.http_build_query(['startDateTime'=>$start,'endDateTime'=>$end,'$top'=>100],'','&',PHP_QUERY_RFC3986);
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
