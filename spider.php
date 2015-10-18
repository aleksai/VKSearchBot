<?php

// Script start
$rustart = getrusage();
$time_start = microtime(true);

require("config.php");

// update

$res = mysql_query_execute("SELECT * FROM phrases");
$count = 0;
$countall = 0;

while($phrase = mysql_fetch_assoc($res)){
    $text = $phrase['phrase'];
    $pid = $phrase['id'];
    $feed = VKNewsSearchBot::getInstance($text)->updateFeed($pid);
    $count += $feed[0];
    $countall += $feed[1];
}
$countph = mysql_num_rows($res);

// deliver

$res = mysql_query_execute("SELECT * FROM phrases");
$countd = 0;

while($phrase = mysql_fetch_assoc($res)){
    $pid = $phrase['id'];
    $chat = $phrase['cid'];

    $pos = mysql_query_execute("SELECT * FROM posts WHERE pid = '$pid'");
    $del = mysql_query_execute("SELECT * FROM delivered WHERE cid = '$chat'");
    $d = array();

    while($dell = mysql_fetch_assoc($del)) {
        array_push($d, $dell["pid"]);
    }

    while($re = mysql_fetch_assoc($pos)) {
        $id = $re["id"];
        $s = array_search($id, $d);
        if(!$s && $s !== 0){
            $countd++;

            // generate all possible links
            // TODO: filter links and rework all above
            $link = "http://vk.com/feed?w=wall".$re["from"]."_".$re["vkid"]."\n";
            $host_link = "http://vk.com/id".$re["from"]."\n";
            $link2 = "http://vk.com/feed?w=wall".$re["owner"]."_".$re["vkid"]."\n";
            if($link === $link2) $link2 = "";
            $host_link2 = "http://vk.com/id".$re["owner"]."\n";
            if($host_link === $host_link2) $host_link2 = "";
            $publ_link = "http://vk.com/public".$re["owner"]."\n";
            $publ_link2 = "http://vk.com/public".$re["vkid"]."\n";
            if($publ_link === $publ_link2) $publ_link2 = "";

            $text = $re["text"];

            sendMessage("$text\n\n$link$link2$host_link$host_link2$publ_link$publ_link2", $chat);
            mysql_query_execute("INSERT INTO `delivered` (`cid`, `pid`) VALUES ('".$chat."', '".$id."')");
        }
    }
}

$countd = $countd ? " ".$countd." dlvrd." : "";

// Script end
$ru = getrusage();
$time_end = microtime(true);
$execution_time = $time_end - $time_start;
if($execution_time>300) sendMessage("$execution_time sec", "3855828");
echo "[$date] $execution_time s, used ".rutime($ru, $rustart, "utime")." ms comps, ".rutime($ru, $rustart, "stime")." ms syscalls. $countph phrs. Founded $countall, added ".$count.".$countd".PHP_EOL;