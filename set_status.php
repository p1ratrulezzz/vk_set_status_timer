<?php
header('Content-type: text/html; charset=UTF-8');

/**
 * Shared settings. Globally used for any function
 */
function &get_shared($name = NULL) {
	static $shared = array(
		'access_token' => NULL,
		'api_url' => 'https://api.vk.com/method/',
		'auth_store' => NULL, // (optional)
		'tzLocal' => 'Europe/Moscow',
	);
	
	if ($name !== NULL) {
		if (!isset($shared[$name])) {
			$shared[$name] = NULL;
		}
		
		return $shared[$name];
	}
	else {
		return $shared;
	}
}

/*************************************************** Do not edit below if not sure what you are doing ************************************************************************/

// Set default timezone to UTC
date_default_timezone_set('UTC');

// Convert shared settings string timezone to DateTimeZone
$tzLocal = &get_shared('tzLocal');
$tzLocal = new DateTimeZone($tzLocal);
unset($tzLocal);

// Set default value for auth_store if not set
if (!get_shared('auth_store')) {
	$shared = &get_shared();
	$shared['auth_store'] = __DIR__ . '/token.txt';
}

// Run authorization first
auth();

// Store access token in variable
$access_token = get_shared('access_token');
function _get($url, $get_info = FALSE)
{
  //$url = urlencode($url);
  $ch = curl_init();

  $headers = array(
      'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Charset' => 'windows-1251,utf-8;q=0.7,*;q=0.3',
      'Accept-Encoding' => 'gzip,deflate,sdch',
      'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
      'Connection' => 'keep-alive',
  );
  curl_setopt($ch, CURLOPT_URL,            $url);
  curl_setopt($ch, CURLOPT_HEADER,         $get_info);
  curl_setopt($ch, CURLOPT_HTTPGET,         TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  //curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
  //curl_setopt($ch, CURLOPT_NOBODY,         true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0; AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.79 Safari/537.1');
  curl_setopt($ch, CURLOPT_TIMEOUT,        30);

  $r = curl_exec($ch);
  $info = curl_getinfo($ch);
  // var_dump($info);
  if ($r === FALSE) {
    //return FALSE;
    var_dump(func_get_args());	 
    var_dump(curl_error($ch));
  }

  return $get_info === FALSE ? $r : $info;
}

function set_status($access_token, $text) {
  $api_url = get_shared('api_url');
  $text = urlencode($text);
  
  $sRequest = "{$api_url}status.set?v=5.60&text={$text}&access_token={$access_token}";
  //$r = _get($url);
  $r = json_decode(_get($sRequest));
  
  return ($r->response == 1);
}

function get_status($access_token) {
  $api_url = get_shared('api_url');
  $sRequest = "{$api_url}status.get?v=5.60&access_token={$access_token}";
  
  //$r = _get($url);
  $r = json_decode(_get($sRequest));
  
  return $r->response->text; 
}

function delete_messages($access_token, $mids) {
  static $buff = 100;
  $responses = array();
  $sRequest = "https://api.vkontakte.ru/method/messages.delete?access_token={$access_token}";
  reset($mids);
  $i = 0;
  $m_buff = array();
  while ((list($key, $value) = each($mids)) || TRUE) {
    if ($i++>$buff) {
      $req = $sRequest. "&mids=". urlencode(implode(',', $m_buff));
      $res = json_decode(_get($req));
      if (isset($res->response)) {
        $responses[] = $res->response;
      }
      else {
        break;
      }
      $i = 0;
      $m_buff = array();
      if (!$value) {
        break;
      }
    }
    
    if ($value) {
      $m_buff[] = $value;
    }
  }
  
  return $responses;
}

function get_history($access_token, $uid, $offset = 0, $limit = 20) {
  $sRequest = "https://api.vkontakte.ru/method/messages.getHistory?access_token={$access_token}&uid={$uid}&rev=1&offset={$offset}&count={$limit}";
  //$r = _get($url);
  $r = json_decode(_get($sRequest));
  
  return isset($r->response) ? $r->response : array();
}

/**
 * Reads line from console.
 */
function _readline( $prompt = '' ) {
    echo $prompt;
    return rtrim( fgets( STDIN ), "\n" );
}

function auth() {
  $shared = &get_shared();  
  
  $auth_store = $shared['auth_store'];
  $access_token = &$shared['access_token'];
  
  if (file_exists($auth_store) && ($_buffer = file($auth_store))) {
	  $access_token = trim($_buffer[0], "\r\n\t ");
	  
	  $url = $shared['api_url'] . "account.getProfileInfo?v=5.60&access_token={$access_token}";
	  $r = _get($url);
	  $r = json_decode($r);
	  
	  if (!isset($r->response->first_name)) {
		  die('Access token is invalid... Reauthorize app.' . PHP_EOL);
	  }
	  else {
		  // Return to continue the program
		  return;
	  }
  }
  
  $client_id = '7653152';
  $scope = 'offline+status';
  $secret = 'RMJcNGkQrxVQJ1c9YpYX';
  $suffix = "&redirect_uri=http://api.vk.com/blank.html&v=5.60";
  //$url = "http://api.vk.com/oauth/authorize?client_id={$id}&client_secret={$client_id}&response_type=token&display=page&scope={$rights}";
  
  $auth_url = 'https://oauth.vk.com/authorize';
  
  echo "Open this link in browser: " . "{$auth_url}?client_id={$client_id}&response_type=code&scope={$scope}{$suffix}" . PHP_EOL;
  $code = _readline("Then input CODE parameter here:");
  
  $code = trim($code, "\r\n\t ");
  $token_url = 'https://oauth.vk.com/access_token' . "?client_id={$client_id}&client_secret={$secret}&code={$code}{$suffix}";
  $r = _get($token_url);
  $r = json_decode($r);
  
  if (isset($r->error) && !isset($r->access_token)) {
	  die('Wrong CODE or something is not ok...' . PHP_EOL . $r->error_description . PHP_EOL);
  }
  
  if (FALSE !== file_put_contents($auth_store, $r->access_token)) {
	  chmod($auth_store, 0400); // Prohibit reading of the file for all except owner.
	  echo "Access token is written. Next run";
  }
  else {
	  echo "Can't write to file {$auth_store}. Please check permissions for writing";
  }
 
  //$r = _get($url, TRUE);
  //var_dump($r);
  //echo $r;
  exit;
}

function diff_raw($to) {
  $diff = strtotime($to) - time();
  if ($diff < 0) {
    return false;
  }
  
  $ret = array();
  $ret['raw'] = $diff;
  $ret['years'] = date('Y', $diff) - 1970;
  $ret['days'] = floor($diff / (24 * 60 * 60));
  $ret['hours'] = floor($diff / (60 * 60)) % 24;
  $ret['minutes'] = floor($diff / 60) % 60;
  $ret['seconds'] = floor($diff) % 60;
  
  return $ret;
}

function diff($to) {
  $to_date = new DateTime($to, get_shared('tzLocal'));
  $now = new DateTime('now', new DateTimeZone('UTC'));
  
  $diff = $now->diff($to_date);
  if ($diff->invert == 1) {
    return false;
  }
  
  $ret = array();
  $ret['raw'] = $diff->days;
  $ret['years'] = $diff->y;
  $ret['days'] = $diff->days;
  $ret['hours'] = $diff->h;
  $ret['minutes'] = $diff->i;
  $ret['seconds'] = $diff->s;
  
  return $ret;
}

/**
 * 
 * Enter description here ...
 * @param unknown_type $text
 * @param unknown_type $pattern
 * @param unknown_type $key
 * @param unknown_type $to
 */
function replace_callback($pattern, $text, $key, $to) {
  $str = NULL;
  if (!($diff = diff($to))) {
    return false;
  }
  
  switch ($key) {
    case 'gaga':
      $parts = array();
      $parts['10'] = $diff['days'] != 0 
        ? $diff['days']. ' '. format_plural($diff['days'], 'day') 
        : NULL;
      $parts['20'] = $diff['hours'] != 0 
        ? $diff['hours']. ' '. format_plural($diff['hours'], 'hour') 
        : NULL;
      $parts['30'] = $diff['minutes'] != 0 
        ? 'и '. $diff['minutes']. ' '. format_plural($diff['minutes'], 'minute') 
        : NULL;
        
      $parts = array_filter($parts);
      if (!empty($parts)) {
        #$parts['25'] = 'и';
        ksort($parts);
        $str = implode(' ', $parts);
      }
      else {
        $str = FALSE;
      }

      //$str = "{$diff['days']} Дней {$diff['hours']} Часов и {$diff['minutes']} минут";
      break;
    case 'days':
      $str = "\${1}{$diff['days']}";
      break;
    case 'dummy':
      $str = FALSE;
      break;
  }
  
  return ($str ? preg_replace($pattern, $str, $text) : $text);
}

function t($word) {
  static $words = array(
    'day' => 'день',
    'days' => 'дней',
    'of day' => 'дня',
    'hour' => 'час',
    'hours' => 'часов',
    'of hour' => 'часа',
    'minute' => 'минута',
    'minutes' => 'минут',
    'of minute' => 'минуты',
  );
  
  return isset($words[$word]) ? $words[$word] : $word;
}

function format_plural($count, $single, $force_plural = FALSE, $latest_decimal = TRUE) {
  static $vocabulary = array(
    'day' => array(
      '2' => 'of day',
      '3' => 'of day',
      '4' => 'of day',
      'plural' => 'days',
    ),
    'minute' => array(
      '2' => 'of minute',
      '3' => 'of minute',
      '4' => 'of minute',
      'plural' => 'minutes',
    ),
    'hour' => array(
      '2' => 'of hour',
      '3' => 'of hour',
      '4' => 'of hour',
      'plural' => 'hours',
    ),
  );
  
  if ($count == 1) {
    return t($single);
  }
  //Return only plural version without applying any other logic
  if ($force_plural) {
    return isset($vocabulary[$single]['plural']) 
      ? t($vocabulary[$single]['plural'])
      : t($single);
  }
  
  if ($count >= 10 && $count <= 20) { //No need to process this range
    return format_plural($count, $single, TRUE, $latest_decimal);
  }
  
  $count = abs($latest_decimal ? $count % 10 : $count); //Extract latest digit
  if (isset($vocabulary[$single]) && $count > 1) {
    if (isset($vocabulary[$single][$count])) {
      return t($vocabulary[$single][$count]);
    }
  }
  
  //Overwise return plural version by recursively call function itself
  //with $force_plural = TRUE
  return format_plural($count, $single, TRUE, $latest_decimal);
}

function apply_pattern(&$pattern, $real_status = FALSE) {
  global $status_vk, $access_token;
  if (!empty($pattern['status']) && !$real_status) {
    $status = $pattern['status'];
  }
  else {
    $status = $status_vk;
  }
  
  if ((!isset($pattern['callback']) || !isset($pattern['pattern'])) && isset($pattern['status'])) {
    echo $status_vk;
    echo ' --> '. PHP_EOL. $pattern['status'].PHP_EOL;
    
    set_status($access_token, $pattern['status']);
    return TRUE;
  }
  elseif (isset($pattern['pattern']) && preg_match($pattern['pattern'], $status)) {
    if (isset($pattern['callback']) && function_exists($pattern['callback'])) {
      if (empty($pattern['args'])) {
        $pattern['args'] = array();
      }
      
      $args = array(
        $pattern['pattern'],
        $status
      );
      
      $args = array_merge($args, $pattern['args']);
      if ($newstatus = call_user_func_array($pattern['callback'], $args)) {
        set_status($access_token, $newstatus);
        echo $status;
        echo ' --> '. PHP_EOL. $newstatus.PHP_EOL;
        
        //Exit if success
        return TRUE;
      }
    }
  }
  
  //False will be returned in case of failure
  return FALSE;
} 

function save_history($id) {
  global $access_token;
  $count = NULL;
  $start = 0;
  $perpage = 150;
  $all = array();
  $current_count = 0;
  do {
    $his = get_history($access_token, $id, $start, $perpage); 
    $current_count += count($his)-1;
    if (!isset($count)) {
      $count = reset($his);
    }
    
    $all[($start). '-'. ($current_count)] = $his;
    
    $start+=$perpage;
  }
  while ($his && $current_count<$count && isset($count));
  
  $all['count'] = $count;
  file_put_contents('/shares/Public/bu/his_'.$id .'.json.txt', json_encode($all));
  file_put_contents('/shares/Public/bu/his_'.$id .'.raw.txt', var_export($all, TRUE));
}

function delete_history($id) {
  global $access_token;
  $count = NULL;
  $start = 0;
  $perpage = 150;
  $all = array();
  $current_count = 0;
  $mids = array();
  do {
    $his = get_history($access_token, $id, $start, $perpage); 
    //var_dump($his);
    
    
    foreach ($his as $el) {
      if (isset($el->mid)) {
        $mids[] = $el->mid;
      }
    }
    
    $current_count += count($his)-1;
    if (!isset($count)) {
      $count = reset($his);
    }
    
    //$all[($start). '-'. ($current_count)] = delete_messages($access_token, $mids);;
    
    $start+=$perpage;
    //break;
  }
  while ($his && $current_count<$count && isset($count));
  
  $all['count'] = $count;
  
  
  $responses = delete_messages($access_token, $mids);
  var_dump($responses);
  
}
//8034008
//53598665
//155183438
//171541459



//save_history('155183438');
//delete_history('171541459');
//exit;

$status = NULL; //get_status($access_token);
$status_vk = get_status($access_token);

//Modes
define('MODE_DEFAULT', 0);
define('MODE_RANDOM', 1);
define('MODE_CONCREATE', 2);

//Choose mode
$mode = MODE_RANDOM;
$concreate_id = 2;

//Patterns
$i = 0;
/*$patterns[$i]['status'] = 'До концерта осталось {TOKEN}';
$patterns[$i]['pattern'] = '/\{TOKEN\}/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', '2012-12-12 19:00:00');
*/
/*
$patterns[$i]['status'] = 'До концерта Lindsey Stirling осталось {TOKEN}';
$patterns[$i]['pattern'] = '/\{TOKEN\}/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', '2013-05-22 20:00:00');
*/

/*$patterns[$i]['status'] = '{TOKEN} until ARTPOP!';
$patterns[$i]['pattern'] = '/\{TOKEN\}/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', '2013-11-11 23:59:59');

$i++;
$patterns[$i]['status'] = 'We could, we could belong together! ARTPOP release is in {TOKEN}';
$patterns[$i]['pattern'] = '/\{TOKEN\}/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', '2013-11-11 23:59:59');

$i++;
$patterns[$i]['status'] = 'My ARTPOP could mean ANYTHING! A N Y T H I N G! Just {TOKEN} left!';
$patterns[$i]['pattern'] = '/\{TOKEN\}/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', '2013-11-11 23:59:59');
*/

/*$patterns[$i]['status'] = 'До нового года осталось 00 Дней 00 Часов и 00 минут';
$patterns[$i]['pattern'] = '/[0-9]+ *[0-9]+ [^0-9]+[0-9]+ [^0-9]+[0-9]+ [^ ]+/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', (date('Y')+1). '-01-01 00:00:00');
*/

//$i++;
/*
$patterns[$i]['status'] = 'До концерта Justin Timberlake осталось 00 Дней 00 Часов и 00 минут';
$patterns[$i]['pattern'] = '/[0-9]+ *[0-9]+ [^0-9]+[0-9]+ [^0-9]+[0-9]+ [^ ]+/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', '2014-05-17 19:00:00');
*/

/*
$i++;
$patterns[$i]['status'] = 'Oooooh, cry me a river через 00 Дней 00 Часов и 00 минут';
$patterns[$i]['pattern'] = '/[0-9]+ *[0-9]+ [^0-9]+[0-9]+ [^0-9]+[0-9]+ [^ ]+/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', '2014-05-17 19:00:00');
*/

/*
//$i++;
$patterns[$i]['status'] = 'До концерта Lindsey Stirling осталось 00 Дней 00 Часов и 00 минут';
$patterns[$i]['pattern'] = '/[0-9]+ *[0-9]+ [^0-9]+[0-9]+ [^0-9]+[0-9]+ [^ ]+/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', '2014-09-30 20:00:00');

$i++;
$patterns[$i]['status'] = 'До концерта Lindsey Stirling осталось каких-то 00 Дней';
$patterns[$i]['pattern'] = '/[0-9]+/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('days', '2014-09-30 20:00:00');

$i++;
$patterns[$i]['status'] = '-00 Somebody make me feel alive and shatter me';
$patterns[$i]['pattern'] = '/^(\-)([0-9]+)/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('days', '2014-09-30 20:00:00'); */

/*
$i++;
$patterns[$i]['status'] = '+00 Небритябрь продолжается';
$patterns[$i]['pattern'] = '/^(\+)([0-9]+)/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('days', '2013-11-01 00:00:00');

$i++;
$patterns[$i]['status'] = 'До зимы осталось 00 Дней 00 Часов и 00 минут';
$patterns[$i]['pattern'] = '/[0-9]+ *[0-9]+ [^0-9]+[0-9]+ [^0-9]+[0-9]+ [^ ]+/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', '2013-12-01 00:00:00');*/

//2012-09-09

/*
$i++;
$patterns[$i]['status'] = '+00 Never mind I\'ll find someone like you';
$patterns[$i]['pattern'] = '/^(\+)([0-9]+)/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('days', '2012-11-11 00:00:00');*/
//2012-09-09

/*
$i++;
$patterns[$i]['status'] = '+00 Волшебные даты: 02.02.2012, 09.09.2012, 11.11.2012';
$patterns[$i]['pattern'] = '/^(\+)([0-9]+)/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('days', '2012-11-11 00:00:00');
//2012-09-09
/*
$i++;
$patterns[$i]['status'] = '+00 Let it burn while I cry cause I heard it screaming out your name';
$patterns[$i]['pattern'] = '/^(\+)([0-9]+)/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('days', '2012-11-11 00:00:00');

/*
$i++;
$patterns[$i]['status'] = 'Even that when it\'s already over I can\'t help myself from looking for you';
$patterns[$i]['pattern'] = '/^(\+)([0-9]+)/i';
//$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('dummy', '2012-09-09 00:00:00'); */

/*
$i++;
$patterns[$i]['status'] = 'I let it fall, my heart. But as it fell you rose to claim it';
$patterns[$i]['pattern'] = '/^(\+)([0-9]+)/i';
//$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('dummy', '2012-11-11 00:00:00');

/*

$i++;
$patterns[$i]['status'] = 'It\'s always harder the second time around';
$patterns[$i]['args'] = array('dummy', '2012-09-09 00:00:00');*/

/*$i++
$patterns[$i]['status'] = 'I don\'t wanna be alone forever but I can be tonight...';
$patterns[$i]['pattern'] = '/^(\+)([0-9]+)/i';
//$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('dummy', '2012-11-11 00:00:00');
*/


/*$i++;
$patterns[$i]['status'] = 'Ванильный статус';
$patterns[$i]['pattern'] = '/^(\+)([0-9]+)/i';
//$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('dummy', '2012-11-11 00:00:00');*/


//$patterns[$i]['status'] = 'Won\'t you die tonight for love';
//$patterns[$i]['args'] = array('dummy', '2012-09-09 00:00:00');

// $i++;
$patterns[$i]['status'] = 'До Нового Года осталось 00 Дней 00 Часов и 00 минут';
$patterns[$i]['pattern'] = '/[0-9]+ *[0-9]+ [^0-9]+[0-9]+ [^0-9]+[0-9]+ [^ ]+/i';
$patterns[$i]['callback'] = 'replace_callback';
$patterns[$i]['args'] = array('gaga', (new Datetime('31 December this year 23:59:59', new DateTimeZone('Europe/Moscow')))->format('Y-m-d H:i:s'));

// // Западный мир
// $patterns[$i]['status'] = 'До второго сезона "Мира дикого запада" 00 Дней 00 Часов и 00 минут';
// $patterns[$i]['pattern'] = '/[0-9]+ *[0-9]+ [^0-9]+[0-9]+ [^0-9]+[0-9]+ [^ ]+/i';
// $patterns[$i]['callback'] = 'replace_callback';
// $patterns[$i]['args'] = array('gaga', '1 October this year');

//var_dump($patterns);
// set_time_limit(-1);
while (true) {
  switch ($mode) {
    default:
      foreach ($patterns as $pattern) {
        if (apply_pattern($pattern, TRUE)) {
          //Exit on success
          break;
        }
      }
      break;
    case MODE_RANDOM:
      $pattern_count = count($patterns);
      $pattern_id = floor(mt_rand(0, 30 * 100) / 30) % $pattern_count;
      //echo $pattern_id;
      
      apply_pattern($patterns[$pattern_id]);
      break;
    case MODE_CONCREATE:
      if (isset($patterns[$concreate_id])) {
		echo "Pattern to apply is: " . PHP_EOL . var_export($patterns[$concreate_id], TRUE) . PHP_EOL;
        apply_pattern($patterns[$concreate_id]);
      }
      break;
  }
  
  //Break always for now
  //@todo: Control run type: either infinite cycling or just one time execution controller from external environment.
  break;
}




//var_dump($_REQUEST);


?>
