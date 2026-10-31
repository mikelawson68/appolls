<?php
$file=$args[0]??'';$active=$args[1]??'';if(!is_file($file)||!$active)throw new Exception('Fixture missing');
if (($args[2]??'')==='network') { if(!is_multisite())throw new Exception('Expected multisite'); add_filter('pre_option_active_plugins',static fn()=>[]);add_filter('pre_site_option_active_sitewide_plugins',static fn()=>[$active=>time()]); } else {add_filter('pre_option_active_plugins',static fn()=>[$active]);}
add_filter('wp_die_handler',static fn()=>static function($message){throw new RuntimeException($message);});
require $file;
if(class_exists('APPolls_Lifecycle',false))throw new Exception('Conflicting edition loaded code');
$got='';try{do_action('activate_'.plugin_basename($file));}catch(RuntimeException $e){$got=$e->getMessage();}
if(stripos($got,'deactivate')===false||stripos($got,'retained')===false)throw new Exception('Activation was not safely blocked: '.$got);
echo "PASS conflicting edition is blocked before loading shared code\n";
