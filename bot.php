<?php
// ===== Telegram Bot: single-file =====
date_default_timezone_set('Asia/Tehran');

// ---- config (با توکن خودت) ----
$BOT_TOKEN = '8238050380:AAFKijTnI4w04XTexjWhJWNgDoVP4ucrDt8';
$API_BASE  = getenv('PROXY_BASE') ?: 'https://api.telegram.org'; // اگه هاست ایران بود، PROXY_BASE بده
$DATA_FILE = dirname(__FILE__).'/subscribers.json';
$LOG_FILE  = dirname(__FILE__).'/webhook_log.txt';

// ---- msgs ----
$DAILY = [
 "امروز همون روزیه که قراره بدرخشی ✨","یه لبخند کوچیک، بزرگ‌ترین شروعه 🙂",
 "تو از اونایی هستی که حال دل رو بهتر می‌کنن 🌿","یه قهوه‌ی داغ و یه خبر خوب در راهه ☕️💫",
 "اگر امروز سخت بود، تو سخت‌ترشی 💪","تو قشنگیِ معمولیِ روز منی 🌞",
 "قدمای کوچیکت، تو رو به جاهای بزرگ می‌رسونه 🚶🏻‍♂️","چقدر قشنگه که هستی ✨",
 "دلت آروم؛ خدا بزرگ 🌱","تو فرق داری، همون فرقِ دوست‌داشتنی 💛",
 "امروز هم از خودت ممنون باش 🙏","تو بلدتری دوباره شروع کنی 🔁",
 "برای تو، خبرای خوب تو راهه 📬","آروم نفس بکش؛ همه‌چی رو روال میفته 🌬️",
 "گاهی فقط کافیه ادامه بدی… همین 👣","تو نشد نداری، فقط زمان می‌خواد ⏳",
 "یادته تا اینجا رو چطور اومدی؟ ادامه بده 👊","تو همون دلیل لبخند منی 🙂💫",
 "یه دنیا حال خوب سهمت 💙","تو بهتر از چیزی هستی که فکر می‌کنی ✨",
 "دنیا با تو قشنگ‌تر می‌چرخه 🌍","به خودت مهربون باش؛ تو ارزششی 💛",
 "اینجا یکی هست که همیشه هواتو داره 🤝","ببین چقدر قشنگ از پسش برمیای 🌟",
 "شب میره، تو می‌مونی و روشنایی 🌤️","یه موزیک خوب پلی کن؛ حالتو بسازه 🎧",
 "تو توانا و خواستنی‌ای؛ شک نکن 🌹","خستگی‌هات‌و بسپار به من 💌",
 "نگران نباش؛ همه‌چی درست میشه 🤍","تو دلیلِ قشنگیِ امروز منی ✨",
 "حواست به رویا‌هات باشه 🌙","امروز هم بگو: «می‌تونم» و برو جلو ✅",
 "بهترینی؛ حتی وقتی نمی‌دونی 💫",
];

// ---- utils ----
function log_it($x){ global $LOG_FILE; @file_put_contents($LOG_FILE,'['.date('c').'] '.(is_string($x)?$x:json_encode($x,JSON_UNESCAPED_UNICODE)).PHP_EOL,FILE_APPEND); }
function ensure_data(){ global $DATA_FILE; if(!file_exists($DATA_FILE)){ @file_put_contents($DATA_FILE,'[]'); @chmod($DATA_FILE,0666);} }
function subs_load(){ global $DATA_FILE; ensure_data(); $j=@file_get_contents($DATA_FILE); $a=@json_decode($j,true); return is_array($a)?$a:[]; }
function subs_save($arr){ global $DATA_FILE; $arr=array_values(array_unique(array_map('intval',$arr))); @file_put_contents($DATA_FILE,json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); @chmod($DATA_FILE,0666); }
function api($method,$params=[],$json=false){
  global $API_BASE,$BOT_TOKEN;
  $url=rtrim($API_BASE,'/').'/bot'.$BOT_TOKEN.'/'.$method;
  $ch=curl_init($url);
  $opt=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4];
  if($json){ $opt[CURLOPT_HTTPHEADER]=['Content-Type: application/json; charset=utf-8']; $opt[CURLOPT_POST]=true; $opt[CURLOPT_POSTFIELDS]=json_encode($params,JSON_UNESCAPED_UNICODE);}
  else{ $opt[CURLOPT_POST]=true; $opt[CURLOPT_POSTFIELDS]=$params; }
  curl_setopt_array($ch,$opt); $res=curl_exec($ch);
  if($res===false){ log_it('CURL_ERR '.curl_error($ch)); }
  curl_close($ch); return $res?json_decode($res,true):null;
}
function send_text($chat_id,$text,$kb=null){ $p=['chat_id'=>$chat_id,'text'=>$text,'parse_mode'=>'HTML']; if($kb)$p['reply_markup']=is_string($kb)?$kb:json_encode($kb,JSON_UNESCAPED_UNICODE); return api('sendMessage',$p,true); }
function kb(){ return ['keyboard'=>[[['text'=>'Subscribe ✅'],['text'=>'Unsubscribe ❌']],[['text'=>'❤️ Note Today'],['text'=>'ℹ️ Help']]],'resize_keyboard'=>true]; }
function today_note(){ global $DAILY; $i=(int)date('z')%count($DAILY); return $DAILY[$i]; }

// ---- quick endpoints ----
if(isset($_GET['ping'])){ echo 'OK '.date('c'); exit; }
if(isset($_GET['status'])){ header('Content-Type:application/json'); echo json_encode(['ok'=>true,'subs'=>count(subs_load()),'php'=>PHP_VERSION],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit; }
$runSend=(isset($_GET['send'])&&$_GET['send']=='1')||(php_sapi_name()==='cli'&&isset($argv[1])&&strtolower($argv[1])==='send');
if($runSend){ $subs=subs_load(); if(!$subs){echo"NO SUBS\n";exit;} $txt="💌 ".today_note(); $n=0; foreach($subs as $cid){ send_text($cid,$txt); usleep(200000); $n++; } echo "SENT $n\n"; exit; }

// ---- webhook ----
$raw=@file_get_contents('php://input'); if(!$raw){ echo 'OK'; exit; }
log_it(['RAW'=>$raw]); $u=json_decode($raw,true); $m=$u['message']??null; if(!$m){ echo 'OK'; exit; }
$cid=$m['chat']['id']??null; $txt=trim($m['text']??''); $name=$m['from']['first_name']??'دوست خوبم'; if(!$cid){ echo 'OK'; exit; }

switch(true){
  case (stripos($txt,'/start')===0):
    $subs=subs_load(); if(!in_array($cid,$subs)){ $subs[]=$cid; subs_save($subs); }
    send_text($cid,"سلام {$name} 👋\nاز امروز هر روز یه پیام انرژی‌بخش برات میاد.\n\nدستورات:\n/today پیام امروز\n/stop لغو پیام‌ها\n/help راهنما", kb());
    break;
  case ($txt==='/today' || $txt==='❤️ Note Today'):
    send_text($cid,'💌 '.today_note()); break;
  case ($txt==='/help' || $txt==='ℹ️ Help'):
    send_text($cid,"راهنما:\n/start شروع و عضویت خودکار\n/today پیام امروز\n/stop لغو پیام‌ها"); break;
  case ($txt==='/stop' || $txt==='Unsubscribe ❌'):
    $subs=subs_load(); $subs=array_values(array_diff($subs,[$cid])); subs_save($subs);
    send_text($cid,"باشه؛ ارسال روزانه متوقف شد. هر وقت خواستی با /start برگرد 🌱", kb()); break;
  case ($txt==='Subscribe ✅'):
    $subs=subs_load(); if(!in_array($cid,$subs)){ $subs[]=$cid; subs_save($subs); }
    send_text($cid,"ثبت شد ✅ از همین امروز پیام‌ها رو می‌گیری.", kb()); break;
  default: send_text($cid,"دستورات: /start /today /stop /help", kb());
}
http_response_code(200); echo 'OK';
