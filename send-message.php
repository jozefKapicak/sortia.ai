<?php
// JSON only, no notices to output
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors','0');

function clip($s,$n){ return function_exists('mb_substr') ? mb_substr($s,0,$n) : substr($s,0,$n); }
function client_ip(){
  foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) { $v=$_SERVER[$k]; if (strpos($v,',')!==false) $v=trim(explode(',',$v)[0]); return $v; }
  }
  return '';
}

try {
  $pdo = new PDO(
    'mysql:host=dbs-mysql-1;port=3306;dbname=petasy_homepage;charset=utf8mb4',
    'admin','Jojo140.',
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ]
  );
} catch (Throwable $e) {
  http_response_code(200);
  echo json_encode(['ok'=>false,'success'=>false,'status'=>'error','result'=>'error','error'=>'db_connect']); exit;
}

$ct  = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$raw = file_get_contents('php://input');
$in  = (strpos($ct,'application/json')!==false) ? (json_decode($raw,true) ?: []) : ($_POST ?: []);
if (!$in && $_GET) $in = $_GET;

$name    = trim($in['name']    ?? '');
$email   = trim($in['email']   ?? '');
$phone   = trim($in['phone']   ?? '');
$message = trim($in['message'] ?? ($in['msg'] ?? ''));
$lang    = trim($in['lang']    ?? '');
$copy_me = (isset($in['copy_me']) && ($in['copy_me']==='on' || $in['copy_me']=='1' || $in['copy_me']===1 || $in['copy_me']===true)) ? 1 : 0;

if ($name==='' || $email==='' || $message==='') {
  echo json_encode(['ok'=>false,'success'=>false,'status'=>'error','result'=>'error','error'=>'missing_fields']); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok'=>false,'success'=>false,'status'=>'error','result'=>'error','error'=>'invalid_email']); exit;
}

try {
  $cols = array_column($pdo->query('SHOW COLUMNS FROM contact_submissions')->fetchAll(), 'Field');
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'success'=>false,'status'=>'error','result'=>'error','error'=>'schema']); exit;
}
$has = fn($c)=>in_array($c,$cols,true);

$insert = ['name','email','message'];
$params = [
  ':name'    => clip($name,255),
  ':email'   => clip($email,255),
  ':message' => clip($message,4000),
];
if ($has('phone'))       { $insert[]='phone';       $params[':phone'] = clip($phone,50); }
if ($has('send_copy'))   { $insert[]='send_copy';   $params[':send_copy'] = (int)$copy_me; }
if ($has('lang'))        { $insert[]='lang';        $params[':lang']  = clip($lang,10); }
if ($has('ip'))          { $insert[]='ip';          $params[':ip']    = clip(client_ip(),64); }
if ($has('user_agent'))  { $insert[]='user_agent';  $params[':ua']    = clip($_SERVER['HTTP_USER_AGENT'] ?? '',512); }

$timeCol = $has('submission_time') ? 'submission_time' : ($has('created_at') ? 'created_at' : null);

// placeholders aligned to column list
$ph = [];
foreach ($insert as $c) {
  $ph[] = match($c){
    'phone'=>':phone','send_copy'=>':send_copy','lang'=>':lang','ip'=>':ip','user_agent'=>':ua',
    default => ':'.$c
  };
}

$sql = "INSERT INTO contact_submissions (".implode(',',$insert).($timeCol?','.$timeCol:'').") ".
       "VALUES (".implode(',',$ph).($timeCol?',NOW()':'').")";

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  // Return a superset of keys to satisfy any frontend checks
  echo json_encode([
    'ok'=>true, 'success'=>true, 'status'=>'ok', 'result'=>'success', 'message'=>'OK'
  ]);
} catch (Throwable $e) {
  error_log('[contact] '.$e->getMessage());
  echo json_encode(['ok'=>false,'success'=>false,'status'=>'error','result'=>'error','error'=>'db_insert']);
}
