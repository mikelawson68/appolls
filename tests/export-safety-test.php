<?php
$entry=$args[0]??'';if(!$entry||!is_file($entry))throw new Exception('Missing plugin entry');require $entry;
wp_set_current_user(1);if(!current_user_can('manage_options'))throw new Exception('QA admin unavailable');global $wpdb;
if($wpdb->query('START TRANSACTION')===false)throw new Exception('Transaction unavailable');$old=$wpdb->suppress_errors(true);
add_filter('wp_die_handler',static fn()=>static function($message,$title,$args){throw new RuntimeException($message.'|'.($args['response']??0));});
try {
 foreach(['=1+1',"\t@SUM(A1)",'+danger','-danger','  =danger',"\rnormal"] as $value)if(APPolls_Admin::csv_safe_cell($value)!=="'".$value)throw new Exception('Unsafe formula field');
 foreach(['Alice','name@example.invalid','2026-10-30'] as $value)if(APPolls_Admin::csv_safe_cell($value)!==$value)throw new Exception('Safe text changed');
 $p=APPolls_Lifecycle::create_poll(['name'=>'Export failure QA','poll_key'=>'export_'.strtolower(wp_generate_password(8,false,false))]);$id=(int)($p['poll_id']??0);if(!$id)throw new Exception('Poll fixture failed');
 $rows=APPolls_Lifecycle::export_rows($id);if($rows!==[])throw new Exception('Empty export not empty');
 $_GET=['poll_id'=>$id,'_wpnonce'=>wp_create_nonce('appolls_export')];$_REQUEST=$_GET;
 $blocked=false;$filter=static function($q)use(&$blocked){if(stripos(ltrim($q),'SELECT r.response_key')===0){$blocked=true;return 'SELECT * FROM `appolls_intentionally_missing_test_table`';}return $q;};add_filter('query',$filter);
 $result='';ob_start();try{APPolls_Admin::instance()->export();}catch(RuntimeException $e){$result=$e->getMessage();}finally{$output=ob_get_clean();remove_filter('query',$filter);}
 if(!$blocked||strpos($result,'Could not prepare poll export')===false||!str_ends_with($result,'|500')||$output!=='')throw new Exception('Failed query produced misleading export: '.$result);
 echo "PASS formula cells neutralized, ordinary values preserved, failed export returns error before CSV\n";
}finally{$_GET=[];$_REQUEST=[];$wpdb->query('ROLLBACK');$wpdb->suppress_errors($old);wp_cache_flush();}
