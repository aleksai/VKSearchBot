<?php

require("config.php");

set_time_limit(0);
$file = "offset";

while(true){

	// date for logs
    $nowUtc = new \DateTime('now',  new \DateTimeZone('UTC'));
    $date = $nowUtc->format('Y-m-d h:i:s');

    // last telegram update offset read
    $f1 = fopen($file, "r");
    $offset = fgets($f1);
    fclose($f1);

    // get telegram updates
    $tel = @file_get_contents($telegram.'getUpdates?offset='.$offset);
    if($tel){

	    $updates = json_decode($tel);
	    $result = $updates->result;

	    // save offset for next update
	    if(count($result)){
	        $new_offset = $result[count($result)-1]->update_id + 1;
	        $f1 = fopen($file, "w");
	        $output = $new_offset . PHP_EOL;
	        fwrite($f1, $output);
	        fclose($f1);
	    }

	    // handle updates
	    foreach ($result as $update) {
	        $message = $update->message;

	        $cid = clean($message->chat->id);
	        if(isset($message->text)) {
	            $text = clean($message->text);
	        }else{
	            echo "[".$date."] Unrecognized update:\n";
	            var_dump($message);
	        }

	        $name = "";
	        if(!isset($message->chat->title)){
	            $fn = clean($message->chat->first_name);
	            $ln = "";
	            if(isset($message->chat->last_name)) $ln = " ".clean($message->chat->last_name);
	            $name = "$fn$ln";
	        }else{
	            $name = clean($message->chat->title);
	        }

	        // check user language
	        $lang = 0;
	        $res = mysql_query_execute("SELECT * FROM chats WHERE cid = '$cid'");
	        if($res){

	        	$user = mysql_fetch_assoc($res);
	        	if($user["lang"] == "1") $lang = 1;

	        	// update names (temporary procedure to get names of unsaved users)
	            $num = mysql_num_rows($res);
	            if($num){
	                mysql_query_execute("UPDATE chats SET name = '$name' WHERE cid = '$cid'");
	            }
	        }

	        if($text){

	            switch($text){

	            	// COMMANDS

	                case '/version':
	                case '/help':
	                    if($lang == 1) sendMessage("Поисковый бот для ВКонтакте, версия 0.9.8\n\n\nВ 1.0 будет:\n\nЧерный список для поиска по каждой фразе\nЗаглушить фразу на время\n\nОставайтесь с нами.", $cid);
	                	else sendMessage("VK Search Bot, ver. 0.9.8\n\n\nTo be in 1.0:\n\nStoplists for phrases\nMute a phrase for period\n\nSo, stay tuned.", $cid);
	                break;

	                case '/start':
	                    echo "[".$date."] user ".$name." installed, cid = $cid\n";

	                    mysql_query_execute("INSERT IGNORE INTO `chats` (cid, name) VALUES ('$cid', '$name')");
	                    
	                    if($lang == 1) sendMessage("Привет, ".$name.". Самое время подписаться на что-нибудь с помощью команды /subscribe", $cid);
	                    else sendMessage("Hi, ".$name.". Time for subscribe to something with /subscribe", $cid);
	                break;

	                case '/stop':
	                    echo "[".$date."] user ".$name." stopped, cid = $cid\n";

	                    mysql_query_execute("DELETE FROM `chats` WHERE cid = '$cid'");
	                    mysql_query_execute("DELETE FROM `phrases` WHERE cid = '$cid'");
	                break;

	                case '/lang':
	                    echo "[".$date."] $name($cid) lang change started\n";

	                    if($lang == 1) sendMessage("Выберите язык..", $cid, "&reply_markup=".json_encode(array("keyboard" => array(array('English', 'Русский')))));
	                    else sendMessage("Choose language...", $cid, "&reply_markup=".json_encode(array("keyboard" => array(array('English', 'Русский')))));
	                    
	                    mysql_query_execute("INSERT INTO `pending` (`cid`, `type`) VALUES ('".$cid."', '3')");
	                break;

	                case '/fuck':
	                    echo "[".$date."] $name($cid) fuck delivered\n";

	                    if($lang == 1) sendMessage("Идите нахуй.", $cid);
	                    else sendMessage("Fuck you.", $cid);
	                break;

	                case '/subscribe':
	                    echo "[".$date."] $name($cid) subscribe started\n";

	                    if($lang == 1) sendMessage("Дайте фразу для подписки...", $cid);
	                    else sendMessage("Text me the phrase or hasttags...", $cid);
	                    
	                    mysql_query_execute("INSERT INTO `pending` (`cid`, `type`) VALUES ('".$cid."', '1')");
	                break;

	                case '/cancel':
	                    if($lang == 1) sendMessage("Готово.", $cid, "&reply_markup=".json_encode(array("hide_keyboard" => true)));
	                    else sendMessage("Done.", $cid, "&reply_markup=".json_encode(array("hide_keyboard" => true)));
	                    mysql_query_execute("DELETE FROM `pending` WHERE cid = '$cid'");
	                break;

	                case '/unsubscribe':
	                    echo "[".$date."] $name($cid) unsubscribe started\n";

	                    $keyboard = array();
	                    $temp_keyboard = array();
	                    $res = mysql_query_execute("SELECT * FROM `phrases` WHERE cid = '$cid'");
	                    if($res){
	                        $i = 0;
	                        $c = mysql_num_rows($res);

	                        if(!$c){

	                            if($lang == 1) sendMessage("Вы ни на что не подписаны. Сделайте это с помощью команды /subscribe", $cid);
	                            else sendMessage("You don't have any phrase. Subscribe with /subscribe", $cid);

	                        }else{

	                            while($phrase = mysql_fetch_assoc($res)) {
	                                $i++;
	                                $c--;
	                                array_push($temp_keyboard, urlencode($phrase["phrase"]));
	                                if($i > 1) {
	                                    $i = 0;
	                                    array_push($keyboard, $temp_keyboard);
	                                    $temp_keyboard = array();
	                                }else{
	                                    if($c===0){
	                                        array_push($keyboard, $temp_keyboard);
	                                    }
	                                }
	                            };

	                            if($lang == 1) sendMessage("Выберите фразу...", $cid, "&reply_markup=".json_encode(array("keyboard" => $keyboard)));
	                            else sendMessage("Choose a phrase...", $cid, "&reply_markup=".json_encode(array("keyboard" => $keyboard)));
	                            mysql_query_execute("INSERT INTO `pending` (`cid`, `type`) VALUES ('".$cid."', '2')");

	                        }
	                    }

	                break;

	                default:

	                	// subscribe/unsubscribe messages handling
	                    $res = mysql_query_execute("SELECT * FROM `pending` WHERE cid = '$cid' ORDER BY time DESC LIMIT 1");

	                    if($res){
	                        $type = mysql_fetch_assoc($res)['type'];
	                        switch($type){
	                            case '1':
	                                $text = str_replace('/subscribe', '', $text);
	                                echo "[".$date."] subscribe cid=$cid text=$text\n";

	                                mysql_query_execute("INSERT INTO `phrases` (`cid`, `phrase`) VALUES ('".$cid."', '".$text."')");

	                                if($lang == 1) sendMessage("Вы подписаны на \"$text\". Первые сообщения скоро начнут приходить. В зависимости от популярности сначала вы можете получать очень много сообщений.", $cid);
	                                else sendMessage("Thank you. You've subscribed to \"$text\". First messages will be in a moment.", $cid);
	                            break;
	                            case '2':
	                                echo "[".$date."] unsubscribe cid=$cid text=$text\n";

	                                mysql_query_execute("DELETE FROM `phrases` WHERE cid = '$cid' AND phrase = '$text'");

	                                if($lang == 1) sendMessage("Вы отписаны от \"$text\".", $cid, "&reply_markup=".json_encode(array("hide_keyboard" => true)));
	                                else sendMessage("You've unsubscribed from \"$text\".", $cid, "&reply_markup=".json_encode(array("hide_keyboard" => true)));
	                            break;
	                            case '3':
	                            	if($text == "Русский") $lang = 1;
	                                echo "[".$date."] $cid lang changed: $lang\n";

	                                mysql_query_execute("UPDATE `chats` SET lang = '$lang' WHERE cid = '$cid'");

	                                if($lang == 1) sendMessage("Язык установлен.", $cid, "&reply_markup=".json_encode(array("hide_keyboard" => true)));
	                                else sendMessage("Language is set.", $cid, "&reply_markup=".json_encode(array("hide_keyboard" => true)));
	                            break;
	                        }
	                        mysql_query_execute("DELETE FROM `pending` WHERE cid = '$cid'");
	                    }else{
		                	// send unknown message for me :)
		                	sendMessage("$name($cid):\n$text", "3855828");
	                    }
	            }

	        }
	    }
	}else{
		echo "[".$date."] telegram connection timeout\n";
	}

sleep($sleep);
}