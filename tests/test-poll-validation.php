<?php
if (!defined('DOING_AJAX')) define('DOING_AJAX',true);
add_filter('wp_die_ajax_handler',static function(){ return static function(){throw new RuntimeException('json_sent');};});
$case=$args[0]??'required_invalid';$suffix=wp_generate_password(8,false,false);
$p=APPolls_Lifecycle::create_poll(['name'=>'Acceptance '.$suffix,'poll_key'=>'accept'.strtolower($suffix),'status'=>'active']);$pid=$p['poll_id'];
$q1=APPolls_Lifecycle::add_question($pid,['question_key'=>'required','question_text'=>'Required','required'=>1]);$qid=$q1['question_id'];
$c1=APPolls_Lifecycle::add_choice($qid,['choice_key'=>'one','label'=>'One']);$c2=APPolls_Lifecycle::add_choice($qid,['choice_key'=>'two','label'=>'Two']);
$q2=APPolls_Lifecycle::add_question($pid,['question_key'=>'optional','question_text'=>'Optional','question_type'=>'open_text']);
$answers=[$qid=>(string)$c1['choice_id']];
if($case==='required_invalid')$answers=[$qid=>'99999999',$q2['question_id']=>'valid optional'];
if($case==='single_multiple')$answers=[$qid=>[$c1['choice_id'],$c2['choice_id']]];
if($case==='required_whitespace'){$q3=APPolls_Lifecycle::add_question($pid,['question_key'=>'text','question_text'=>'Required text','question_type'=>'open_text','required'=>1]);$answers[$q3['question_id']]='   ';}
if($case==='draft')APPolls_Lifecycle::update_poll($pid,['status'=>'draft']);
$_POST=['poll_key'=>'accept'.strtolower($suffix),'nonce'=>wp_create_nonce('appolls_vote'),'answers'=>$answers];$_REQUEST=$_POST;$_COOKIE['appolls_visitor']=wp_generate_uuid4();
ob_start();try{APPolls_Plugin::instance()->ajax_vote();}catch(RuntimeException $e){}$json=json_decode(ob_get_clean(),true);
$expected=$case==='valid';$ok=is_array($json)&&(bool)($json['success']??false)===$expected;
echo json_encode(['case'=>$case,'expected_success'=>$expected,'actual_success'=>$json['success']??null,'pass'=>$ok])."\n";
if(!$ok)exit(1);
