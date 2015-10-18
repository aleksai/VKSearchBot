<?php

require("config.php");


$res = mysql_query_execute("SELECT * FROM chats");
while($chat = mysql_fetch_assoc($res)){
    sendMessage("vk.com was up, so in a while you'll continue to receive your subriptions. 😘", $chat['cid']);
}

//mysql_query_execute("INSERT IGNORE INTO `chats` (time, name, cid) VALUES ('2015-08-09 11:03:01', 'Dmitriy Lisov', '115123135')");

echo mysql_error();