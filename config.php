<?php

error_reporting(-1);

$database_pass = ""; // define a password
$database_name = ""; // define a database name
$telegram_token_string = ""; // define telegram token string

$database_host = "localhost"; 
$database_user = "root";
$telegram = "https://api.telegram.org/bot".$telegram_token_string."/";

$sleep = 3;

$nowUtc = new \DateTime( 'now',  new \DateTimeZone( 'UTC' ) );
$date = $nowUtc->format('Y-m-d h:i:s');

$database_link = mysql_connect( $database_host, $database_user, $database_pass );
if ( ! $database_link ) {
    die( "Requires a Mysql database. <hr><b>Mysql error</b>: " . mysql_error() );
}
$db_selected = mysql_select_db( $database_name );
if ( ! $db_selected ) {
    die( "Requires a Mysql database. <hr><b>Mysql error</b>: " . mysql_error() );
}
mysql_query("SET NAMES 'utf8mb4'");

function mysql_query_execute($sql) { 
    $result = mysql_query($sql);

    if (!$result) {
        return false;
    }

    return $result;
    return "";
}

function sendMessage($message, $cid, $keyboard = "") {
	global $telegram;
	file_get_contents($telegram.'sendMessage?disable_web_page_preview=true&chat_id='
    .$cid.'&text='
    .urlencode($message).$keyboard);
}

function clean($str) {
	if(is_array($str)){
		for ($i=0; $i < count($str); $i++) { 
			$str[$i] = mb_convert_encoding($str[$i], 'UTF-8', 'UTF-8');
			$str[$i] = htmlentities($str[$i], ENT_QUOTES, 'UTF-8');
		}
	}else{
		if(isset($str)){
			$str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
			$str = htmlentities($str, ENT_QUOTES, 'UTF-8');
		}else{
			$str = '';
		}	
	}

	return $str;
}

function rutime($ru, $rus, $index) {
    return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
     -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
}

function consolelog($output) {
	$f1 = fopen("/var/www/html/log", "a");
	fwrite($f1, $output);
	fclose($f1);
}



class VKPublicAPI {
    public $version = '5.35';

	protected function _responseHandler($resp) {
        $resp = json_decode($resp);

        if (isset($resp->error)) {
            throw new \Exception($resp->error->error_msg, $resp->error->error_code);
        }

        return $resp->response;
    }

    public function callPublicMethod($method, array $params=array()) {
        //echo $this->getApiUrl($method, $params)."\n";
        return $this->_responseHandler(
            file_get_contents( $this->getApiUrl($method, $params) )
        );
    }

    public function getApiUrl($method, array $params=array()) {
        $params['v'] = $this->version;
        return 'https://api.vk.com/method/' . $method . '?' . http_build_query($params);
    }
}

class VKNewsSearchBot extends VKPublicAPI {
    
    public $hashTag;

	public function __construct($hashTag) {
        $this->hashTag = $hashTag;
    }

    static public function getInstance($hashTag) {
        return new self($hashTag);
    }

    public function updateFeed($pid) {
        $resp = $this->callPublicMethod('newsfeed.search', array(
            'q' => $this->hashTag,
            'count' => 90
        ));

        $count = 0;
        $oldid = 0;

        foreach ($resp->items as $news) {
            $info = (object) array();

            $info->id = $news->id;
            $info->owner = str_replace("-", "", $news->owner_id);
            $info->from = str_replace("-", "", $news->from_id);
            $info->type = addslashes($news->post_type);
            $info->text = addslashes($news->text);

            if(strlen($info->text) < 500) {
                mysql_query_execute("INSERT IGNORE INTO `posts` (`vkid`, `owner`, `from`, `type`, `text`, `pid`) VALUES ('".$info->id."', '".$info->owner."', '".$info->from."', '".$info->type."', '".$info->text."', '$pid')");
                if(mysql_error()) echo mysql_error()."\n";
                $postid = mysql_insert_id();
                if($postid !== $oldid){
                	$oldid = $postid;
                	$count++;
                }
            }
        }

        return [$count, count($resp->items)];
    }

}