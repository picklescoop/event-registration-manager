<?php
if(!defined('ABSPATH'))exit;
/** Local encryption for WordPress options; key derives from site-specific wp-config.php salts. */
final class ERM_Secret_Store{
 const PREFIX='ermenc:v1:';
 static function key(){
  if(!function_exists('openssl_encrypt')||!function_exists('openssl_decrypt')||!function_exists('random_bytes'))return false;
  if(!defined('AUTH_KEY')||!defined('SECURE_AUTH_KEY')||!defined('LOGGED_IN_KEY')||!defined('NONCE_KEY'))return false;
  $material=AUTH_KEY.SECURE_AUTH_KEY.LOGGED_IN_KEY.NONCE_KEY;
  if(strlen($material)<64)return false;
  return hash_hmac('sha256','super-forms-local-secret-store-v1',$material,true);
 }
 static function encrypt($value){
  if(!is_string($value)||$value==='')return $value;
  if(strpos($value,self::PREFIX)===0)return $value;
  $key=self::key();if($key===false)return new WP_Error('erm_encryption_unavailable','Local secret encryption unavailable. Check OpenSSL and WordPress security salts.');
  try{$iv=random_bytes(12);}catch(Exception $e){return new WP_Error('erm_encryption_unavailable','Unable to generate encryption nonce.');}
  $tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'erm-secret-v1',16);
  if($cipher===false)return new WP_Error('erm_encryption_failed','Could not encrypt local integration secret.');
  return self::PREFIX.base64_encode($iv.$tag.$cipher);
 }
 static function decrypt($value){
  if(!is_string($value)||strpos($value,self::PREFIX)!==0)return $value;
  $key=self::key();$raw=base64_decode(substr($value,strlen(self::PREFIX)),true);
  if($key===false||$raw===false||strlen($raw)<29)return '';
  $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'erm-secret-v1');
  return $plain===false?'':$plain;
 }
 static function read($option,$fields){
  $stored=get_option($option,[]);$stored=is_array($stored)?$stored:[];
  $changed=false;
  foreach($fields as $field){
   $path=explode('.',$field);$ref=&$stored;
   foreach($path as $part){if(!is_array($ref)||!array_key_exists($part,$ref)){unset($ref);continue 2;}$ref=&$ref[$part];}
   if(is_string($ref)&&$ref!==''&&strpos($ref,self::PREFIX)!==0){$encrypted=self::encrypt($ref);if(!is_wp_error($encrypted)){$ref=$encrypted;$changed=true;}}
   unset($ref);
  }
  if($changed)update_option($option,$stored,false);
  foreach($fields as $field){
   $path=explode('.',$field);$ref=&$stored;
   foreach($path as $part){if(!is_array($ref)||!array_key_exists($part,$ref)){unset($ref);continue 2;}$ref=&$ref[$part];}
   $ref=self::decrypt($ref);unset($ref);
  }
  return $stored;
 }
 static function write($option,$data,$fields){
  foreach($fields as $field){
   $path=explode('.',$field);$ref=&$data;
   foreach($path as $part){if(!is_array($ref)||!array_key_exists($part,$ref)){unset($ref);continue 2;}$ref=&$ref[$part];}
   $encrypted=self::encrypt($ref);if(is_wp_error($encrypted))return $encrypted;
   $ref=$encrypted;unset($ref);
  }
  update_option($option,$data,false);return true;
 }
}
