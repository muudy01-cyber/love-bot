<?php
/**
 * Telegram Bot — Single File, All-in-One (Webhook + Daily Sender + Admin tools)
 * Place as: public_html/lovebot/bot.php   (یا هر مسیر دلخواه)
 *
 * 1) اگر روی هاست اشتراکی هستی:
 *    - این فایل را آپلود کن.
 *    - وبهوک:
 *      https://api.telegram.org/botYOUR_TOKEN/setWebhook?url=https://YOURDOMAIN.com/lovebot/bot.php
 *
 * 2) اگر هاست داخل ایران است و خروجی به api.telegram.org بسته است:
 *    - یک Cloudflare Worker بساز و آدرسش را در PROXY_BASE بگذار (مثال پایین).
 *
 * 3) ارسال روزانه (یکی را انتخاب کن):
 *    - دستی:   https://YOURDOMAIN.com/lovebot/bot.php?send=1
 *    - کرون:   wget -q -O - "https://YOURDOMAIN.com/lovebot/bot.php?send=1" >/dev/null 2>&1
 *    - CLI:    php bot.php send
 *
 * نکته امنیتی: بهتره BOT_TOKEN را در متغیر محیطی ست کنی (مثلاً در Render/Railway).
 */

// ========= Config =========

// ➊ توکن را از ENV بخوان؛ اگر نبود از ثابت پایین بخوان
$ENV_TOKEN  = getenv('BOT_TOKEN');
$BOT_TOKEN  = $ENV_TOKEN ?: 'YOUR_BOT_TOKEN_HERE'; // <-- اینجا توکن‌ات را بگذار اگر ENV نداری
$ADMIN_ID   = (int) getenv('ADMIN_ID');            // اختیاری: آیدی عددی ادمین برای برادکست/مدیریت
date_default_timezone_set('Asia/Tehran');

// ➋ اگر هاستت به api.telegram.org وصل نمی‌شود، یک پروکسی بگذار (مثلاً Cloudflare Worker):
//    نمونه Worker:
/*
export default {
  async fetch(req) {
    const url = new URL(req.url);
    url.hostname = 'api.telegram.org';
    return fetch(new Request(url, req));
  }
}
*/
// بعد آدرس Worker را در PROXY_BASE ست کن:
$PROXY_BASE = getenv('PROXY_BASE'); // مثلا: https://tg-proxy-yourname.workers.dev
$API_BASE   = $PROXY_BASE ?: 'https://api.telegram.org';

// مسیر فایل‌ها
$DATA_FILE  = __DIR__ . '/subscribers.json';
$LOG_FILE   = __DIR__ . '/webhook_log.txt';

// پیام‌های روزانه (می‌تونی تغییر بدی/اضافه کنی)
$DAILY = [
  "امروز همون روزیه که قراره بدرخشی ✨",
  "یه لبخند کوچیک، بزرگ‌ترین شروعه 🙂",
  "تو از اونایی هستی که حال دل رو بهتر می‌کنن 🌿",
  "یه قهوه‌ی داغ و یه خبر خوب در راهه ☕️💫",
  "اگر امروز سخت بود، تو سخت‌ترشی 💪",
  "تو قشنگیِ معمولیِ روز منی 🌞",
  "قدمای کوچیکت، تو رو به جاهای بزرگ می‌رسونه 🚶🏻‍♂️",
  "چقدر قشنگه که هستی ✨",
  "دلت آروم؛ خدا بزرگ 🌱",
  "تو فرق داری، همون فرقِ دوست‌داشتنی 💛",
  "امروز هم از خودت ممنون باش 🙏",
  "تو بلدتری دوباره شروع کنی 🔁",
  "برای تو، خبرای خوب تو راهه 📬",
  "آروم نفس بکش؛ همه‌چی رو روال میفته 🌬️",
  "گاهی فقط کافیه ادامه بدی… همین 👣",
  "تو نشد نداری، فقط زمان می‌خواد ⏳",
  "یادته تا اینجا رو چطور اومدی؟ ادامه بده 👊",
  "تو همون دلیل لبخند منی 🙂💫",
  "یه دنیا حال خوب سهمت 💙",
  "تو بهتر از چیزی هستی که فکر می‌کنی ✨",
  "دنیا با تو قشنگ‌تر می‌چرخه 🌍",
  "به خودت مهربون باش؛ تو ارزششی 💛",
  "اینجا یکی هست که همیشه هواتو داره 🤝",
  "ببین چقدر قشنگ از پسش برمیای 🌟",
  "شب میره، تو می‌مونی و روشنایی 🌤️",
  "یه موزیک خوب پلی کن؛ حالتو بسازه 🎧",
  "تو توانا و خواستنی‌ای؛ شک نکن 🌹",
  "خستگی‌هات‌و بسپار به من 💌",
  "نگران نباش؛ همه‌چی درست میشه 🤍",
  "تو دلیلِ قشنگیِ امروز منی ✨",
  "حواست به رویا‌هات باشه 🌙",
  "امروز هم بگو: «می‌تونم» و برو جلو ✅",
  "بهترینی؛ حتی وقتی نمی‌دونی 💫",
];

// ========= Helpers =========
function log_it($x) {
  global $LOG_FILE;
  @file_put_contents($LOG_FILE, '['.date('Y-m-d H:i:s').'] '.(is_string($x)?$x:json_encode($x,JSON_UNESCAPED_UNICODE)).PHP_EOL, FILE_APPEND);
}
function ensure_data() {
  global $DATA_FILE;
  if (!file_exists($DATA_FILE)) {
    @file_put_contents($DATA_FILE, '[]');
    @chmod($DATA_FILE, 0666);
  }
}
function subs_load() {
  global $DATA_FILE;
  ensure_data();
  $j = @file_get_contents($DATA_FILE);
  $arr = @json_decode($j, true);
  return is_array($arr) ? $arr : [];
}
function subs_save($arr) {
  global $DATA_FILE;
  $list = array_values(array_unique(array_map('intval', $arr)));
  @file_put_contents($DATA_FILE, json_encode($list, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  @chmod($DATA_FILE, 0666);
}
function api($method, $params = [], $json = false) {
  global $API_BASE, $BOT_TOKEN;
  $url = rtrim($API_BASE,'/').'/bot'.$BOT_TOKEN.'/'.$method;

  $ch = curl_init($url);
  $options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
  ];
  if ($json) {
    $options[CURLOPT_HTTPHEADER]   = ['Content-Type: application/json; charset=utf-8'];
    $options[CURLOPT_POST]         = true;
    $options[CURLOPT_POSTFIELDS]   = json_encode($params, JSON_UNESCAPED_UNICODE);
  } else {
    $options[CURLOPT_POST]         = true;
    $options[CURLOPT_POSTFIELDS]   = $params;
  }
  // IPv4 اجباری (بعضی هاست‌ها با IPv6 مشکل دارن)
  $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;

  curl_setopt_array($ch, $options);
  $res = curl_exec($ch);
  if ($res === false) {
    log_it('CURL_ERR '.curl_error($ch));
  }
  curl_close($ch);
  return $res ? json_decode($res, true) : null;
}
function send_text($chat_id, $text, $reply_markup=null) {
  $p = ['chat_id'=>$chat_id, 'text'=>$text, 'parse_mode'=>'HTML'];
  if ($reply_markup) $p['reply_markup'] = is_string($reply_markup) ? $reply_markup : json_encode($reply_markup, JSON_UNESCAPED_UNICODE);
  return api('sendMessage', $p, true);
}
function today_note() {
  global $DAILY;
  $idx = (int)date('z') % count($DAILY);
  return $DAILY[$idx];
}
function main_keyboard() {
  return [
    'keyboard' => [
      [['text'=>'Subscribe ✅'], ['text'=>'Unsubscribe ❌']],
      [['text'=>'❤️ Note Today'], ['text'=>'ℹ️ Help']]
    ],
    'resize_keyboard' => true,
    'one_time_keyboard' => false,
  ];
}

// ========= Quick Endpoints (GET/CLI) =========
// سلامت/پینگ
if (isset($_GET['ping'])) { echo 'OK '.date('c'); exit; }
// وضعیت
if (isset($_GET['status'])) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'subs' => count(subs_load()),
    'php' => PHP_VERSION,
    'proxy' => (bool) getenv('PROXY_BASE')
  ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  exit;
}
// ارسال گروهی دستی (GET ?send=1) یا CLI: php bot.php send
$runSend = (isset($_GET['send']) && $_GET['send']=='1') ||
           (php_sapi_name()==='cli' && isset($argv[1]) && strtolower($argv[1])==='send');
if ($runSend) {
  $subs = subs_load();
  if (!$subs) { echo "NO SUBSCRIBERS\n"; exit; }
  $txt = "💌 ".today_note();
  $n=0; foreach ($subs as $cid) { send_text($cid, $txt); usleep(200000); $n++; }
  echo "SENT $n\n"; exit;
}

// ========= Webhook Handler =========
$raw = file_get_contents('php://input');
if (!$raw) { echo 'OK'; exit; } // برای هلت‌چک

log_it(['RAW'=>$raw]);
$upd = json_decode($raw, true);

// Callback query (اگر بعداً دکمه‌های inline داشتی)
if (isset($upd['callback_query'])) {
  $cq = $upd['callback_query'];
  $cid = $cq['message']['chat']['id'];
  $data= trim($cq['data']);
  if ($data==='note') {
    send_text($cid, '💌 '.today_note());
  } else {
    send_text($cid, 'خب متوجه نشدم، از /help استفاده کن.');
  }
  echo 'OK'; exit;
}

// Message
$msg = $upd['message'] ?? null;
if (!$msg) { echo 'OK'; exit; }

$cid   = $msg['chat']['id'] ?? null;
$text  = trim($msg['text'] ?? '');
$name  = $msg['from']['first_name'] ?? 'دوست خوبم';

if (!$cid) { echo 'OK'; exit; }

// اوامر
switch (true) {
  case (stripos($text, '/start')===0):
    // Auto-subscribe: می‌تونی اگر خواستی فقط معرفی کنی و با دکمه عضو کنه
    $subs = subs_load();
    if (!in_array($cid, $subs)) { $subs[]=$cid; subs_save($subs); }
    send_text($cid,
      "سلام {$name} 👋\nاز امروز هر روز یه پیام انرژی‌بخش برات میاد.\n\n" .
      "دستورات:\n/today پیام امروز\n/stop لغو پیام‌های روزانه\n/help راهنما",
      main_keyboard()
    );
    break;

  case ($text==='/today' || $text==='❤️ Note Today'):
    send_text($cid, '💌 '.today_note());
    break;

  case ($text==='/help' || $text==='ℹ️ Help'):
    send_text($cid,
      "راهنما:\n" .
      "/start شروع و عضویت خودکار\n" .
      "/today پیام امروز\n" .
      "/stop لغو پیام‌ها\n\n" .
      "دکمه‌ها هم پایین هستن؛ هر وقت خواستی از همونا استفاده کن."
    );
    break;

  case ($text==='/stop' || $text==='Unsubscribe ❌'):
    $subs = subs_load();
    $subs = array_values(array_diff($subs, [$cid]));
    subs_save($subs);
    send_text($cid, "باشه؛ ارسال روزانه متوقف شد. هر وقت خواستی با /start برگرد 🌱", main_keyboard());
    break;

  case ($text==='Subscribe ✅'):
    $subs = subs_load();
    if (!in_array($cid, $subs)) { $subs[]=$cid; subs_save($subs); }
    send_text($cid, "ثبت شد ✅ از همین امروز پیام‌ها رو می‌گیری.", main_keyboard());
    break;

  // اوامر ادمین (اختیاری)
  case (stripos($text, '/broadcast ')===0 && $ADMIN_ID && $cid==$ADMIN_ID):
    $msg = trim(substr($text, 11));
    $subs = subs_load();
    $n=0; foreach ($subs as $x) { send_text($x, "📢 ".$msg); usleep(200000); $n++; }
    send_text($cid, "Broadcast sent to $n users ✅");
    break;

  case ($text==='/subs' && $ADMIN_ID && $cid==$ADMIN_ID):
    send_text($cid, "Subscribers: ".count(subs_load()));
    break;

  case ($text==='/me'):
    send_text($cid, "Chat ID: <code>{$cid}</code>");
    break;

  default:
    // پیش‌فرض
    send_text($cid, "دستورات: /start /today /stop /help", main_keyboard());
}

// پاسخ فوری به وبهوک
http_response_code(200);
echo 'OK';
