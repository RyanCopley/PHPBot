<?php
ini_set('memory_limit', '512M');
//Reroute the STDOUT (The bots output) to a file, so it is both logged, and console-viewable.
require("settings.php");
chdir(BAWKBOTDIRECTORY);

fclose(STDOUT);$STDOUT = fopen("application.log", "a+");

require("misc/functions.php");
require("classes/bawkplugin.php");
require("classes/bawkuserdata.php");
require("classes/bawkmessages.php");
require("classes/bawkalias.php");
require("classes/bawkcommandtimer.php");

if ($mysql_statistics && 0) {
    $mysql_statistics = mysql_connect($mysql_host, $mysql_username, $mysql_password);
    mysql_select_db($mysql_db, $mysql_statistics);
}


$messageQueue = array();
$plugins      = array();

$plugins      = loadAllPlugins();
$commandTimer = new commandTimer();
$aliaser      = new aliaser();
$aliaser->loadAliases();

$bootTime      = time();
$serverThreads = array();

gc_enable();
set_time_limit(0);
ini_set('display_errors', "1");

//Load server profiles.
$servers = json_decode(file_get_contents("servers.json"), true);

foreach ($servers as $key => $connect) {
    connectToIRC($connect['host'], $connect['port'], $connect['nick'], $connect['channels'], $connect['onJoin'], $connect['authMethod']);
}


$latestTimer = time();
do {
    $commandTimer->expiredCommands();
    
    
    if (time() % 15 == 0 && abs(time() - $latestTimer) > 10) {
        $latestTimer = time();
        if ($mysql_statistics) {
            mysql_query("INSERT INTO `pingbacks` (`time`) VALUES(" . time() . ")", $mysql_statistics);
        }
    }
    
    foreach ($serverThreads as $key => $server) {
        socket_set_blocking($server['SOCKET'], false);
        if (!feof($server['SOCKET'])) {
            $server['READ_BUFFER'] = fgets($server['SOCKET'], 1024);
            if ($server['READ_BUFFER'] == "") {
                continue;
            }
            
            echo "[RECEIVE] " . str_replace("\r\n","",$server['READ_BUFFER'])."\r\n";
            
            if (substr($server['READ_BUFFER'], 0, 6) == "PING :") {
                SendCommand($server, "PONG :" . substr($server['READ_BUFFER'], 6) . "\r\n");
            } else {
    
                    $rawMessage = explode(" ", $server['READ_BUFFER'], 4);
                
                    if (count($rawMessage) < 3) {
                        continue;
                    }
                    
                    
                    $messageNick = trim(substr($rawMessage[0], 1, strpos($rawMessage[0], "!") - 1));
                    $messageMask = trim(substr($rawMessage[0], strpos($rawMessage[0], "!") + 1));
                    
                    $messageType = isset($rawMessage[1]) ? trim($rawMessage[1]) : "";
                    $messageChan = isset($rawMessage[2]) ? trim($rawMessage[2]) : "";
                    
                    if (substr($messageChan, 0, 1) == ":") {
                        $messageChan = substr($messageChan, 1, strlen($messageChan));
                    }
                    
                    //Removes beginning colon mark (:)
                    $messageCont = isset($rawMessage[3]) ? trim($rawMessage[3]) : "";
                    
                    if (isset($rawMessage[3]) && substr($rawMessage[3], 0, 1) == ":") {
                        $messageCont = trim(substr($rawMessage[3], 1));
                    }
                    
                    if (in_array($messageType, array("PART","QUIT","JOIN","NICK"))) {
                        //These actions mean that we are no longer sure if their status. We want to reauth them to make sure they're legitimate.
                        $server["USERDATA"]->addUserData("BawkBotPermissions", $messageChan, $messageNick, array("isAuthenticated" => 0));
                    }
                    
                    //User list, sent on bot join.
                    if ($messageType == "NICK") {
                        SendCommand($server, "PRIVMSG NickServ STATUS " . $messageChan . "\r\n");
                        
                        //Lets also rearrange some stuff for this particular type
                        
                        
                    }
                    
                    //Note : 352 and a /who is more efficient. change that later.
                    if ($messageType == "353") {
                        $chanUsers = explode(" ", substr($messageCont, strpos($messageCont, ":") + 1, strlen($messageCont)));
                        foreach ($chanUsers as $toAuth) {
                            if (in_array(substr($toAuth, 0, 1), array('+','%','@','&'))) {
                                $toAuth = substr($toAuth, 1, strlen($toAuth));
                            }
                            
                            SendCommand($server, "PRIVMSG NickServ STATUS " . $toAuth . "\r\n");
                        }
                    }
                    
                    if ($messageType == "JOIN") {
                        //Authenticate them.
                        if ($server['authMethod'] == "NickServ") {
                            SendCommand($server, "PRIVMSG NickServ STATUS " . $messageNick . "\r\n");
                        }
                    }
                    
                    //Catch the NickServs reply
                    if ($messageNick == "NickServ") {
                        if ($messageChan == $server["nick"]) {
                            if ($messageType == "NOTICE") {
                                $nickServMessage = explode(" ", $messageCont);
                                if ($nickServMessage[0] == "STATUS") {
                                    if ($nickServMessage[2] == "3") {
                                        $server["USERDATA"]->addUserData("BawkBotPermissions", "*", $nickServMessage[1], array(
                                            "isAuthenticated" => 1
                                        ));
                                        print "User `" . $nickServMessage[1] . "` authenticated!\r\n";
                                    } else {
                                        print "User `" . $nickServMessage[1] . "` failed to authenticate correctly!\r\n";
                                    }
                                    
                                }
                            }
                        }
                    }
                    
                    //Fixes the syntax if BawkBot receives a private message.
                    if (substr($messageChan, 0, 1) != "#") {
                        $messageChan = $messageNick;
                    }
                    
                    /*
                    $pid = pcntl_fork();
                    if ($pid == -1) {
                         die('could not fork');
                    } else if ($pid) {
                       
                         pcntl_wait($status); 
                    } else {
                       */ 
                       //Try to execute the command.
                       
                       print_r($messageChan);
                        bawkBotExecuteCommand($server, $messageNick, $messageType, $messageChan, $messageCont, $messageMask);
                        /*
                        posix_kill(getmypid(),9);
                    }*/
                    
            } //if (ping)
        } //if feof
    } //foreach serverThread
    usleep(5000);
} while (true);



/*Bawkbot Parser & Commander, the bread and butter of the bot.*/
function bawkBotExecuteCommand($server, $messageNick, $messageType, $messageChan, $messageCont, $messageMask = "", $choke = false)
{
    global $plugins, $aliaser, $messageQueue, $mysql_statistics, $mysql_statistics;
    
    $isCommand = (substr($messageCont, 0, 1) == COMMANDSYMBOL);
    
    $preAliaser = $messageCont;
    if ($isCommand) {
        do {
            $pipeCount       = substr_count($messageCont, "|");
            $messageCommands = parse_delimiters($messageCont, "|");
            foreach ($messageCommands as &$tmpMsg) {
                $tmp    = parse_delimiters(substr($tmpMsg, 1), " ");
                $tmpMsg = COMMANDSYMBOL . implode(" ", $aliaser->performAlias($tmp));
            }
            
            $messageCont = implode(" | ", $messageCommands);
            
        } while ($pipeCount != substr_count($messageCont, "|"));
        
        $messageCommands = parse_delimiters($messageCont, "|");
        
        
    } else {
        $messageCommands = array(
            $messageCont
        );
    }
    
    
    
    $pipeCommandCounter = 1;
    $stdin              = array(); //The output of a message if it is piped. If it returns multiple lines, they are concatenated
    //together with a space in between.
    
    
    
    if ($isCommand) {
    
        if ($mysql_statistics) {
            $queryString = sprintf("INSERT INTO `commands` (`time`,`server`,`nick`,`hostmask`,`type`,`channel`,`prealiser`,`message`,`choked`) VALUES ('%d','%s','%s','%s','%s','%s','%s','%s','%d')", time(), addslashes($server['host']), addslashes($messageNick), addslashes($messageMask), addslashes($messageType), addslashes($messageChan),addslashes($preAliaser), addslashes($messageCont), addslashes($choke));
            mysql_query($queryString, $mysql_statistics);
        }
        
        $stdinCnt = 1;
        foreach ($messageCommands as $message) {
            $counter = 1;
            foreach ($stdin as $stdinval) {
                $message = str_replace_outside_quotes("[in-" . $counter . "]", '"' . trim($stdin[$counter]) . '"', $message);
                $counter++;
            }
            
            if (isset($stdin[$stdinCnt - 1])) {
                $message = str_replace_outside_quotes("[in]", '"' . trim($stdin[$stdinCnt - 1]) . '"', $message);
            }
            
            $stdin[$stdinCnt] = "";
            $command          = parse_delimiters(substr($message, 1, strlen($message)), ' ');
            
            $message = COMMANDSYMBOL . implode($command, " ");
            
            /*End aliasing. Output is the final (valid) command.*/
            
            /*Plugin messenger / pipe output manager*/
            foreach ($plugins as $key => $plugin) {
                if (in_array(trim($messageType . "." . strtolower(trim($command[0]))), $plugin['object']->getHooks())) {
                    //Get the permission level for the plugin, and the user.
                    if ($server["USERDATA"]->existsUserData("BawkBotPermissions", $messageChan, $messageNick, "PermissionLevel") && $server["USERDATA"]->existsUserData("BawkBotPermissions", $messageChan, $messageNick, "isAuthenticated")) {
                        $userPermissionsData = $server["USERDATA"]->getUserData("BawkBotPermissions", $messageChan, $messageNick);
                        
                        $userPermissionsLevel = $userPermissionsData['isAuthenticated'] ? $userPermissionsData["PermissionLevel"] : 0;
                    } else {
                        $userPermissionsLevel = 0;
                        $server["USERDATA"]->addUserData("BawkBotPermissions", $messageChan, $messageNick, array("PermissionLevel" => 0,"isAuthenticated" => 0));
                    }
                    
                    $pluginPermissionsLevel = $plugin['object']->getUserPermissions($server, $messageChan, $messageNick, $message);
                    
                    //Make sure we have enough permissions, make sure the permissions on the plugin is set, and make sure we're not ignored.
                    if ((int) $userPermissionsLevel >= (int) $pluginPermissionsLevel && (int) $pluginPermissionsLevel <= 9000 && (int) $userPermissionsLevel >= 0) {
                        $finalMsg = $message;
                        
                        if (substr(trim($message), -1, 1) == "&") {
                            $finalMsg = substr($finalMsg, 0, strlen($finalMsg) - 1);
                        }
                        
                        
                        //Push the data to the plugin, signal for it to process, and receive output.
                        $plugin['object']->pushMessage($server, $messageChan, $messageNick, $finalMsg, $messageType, $messageMask);
                        $plugin['object']->pluginLogic();
                        $output = $plugin['object']->receiveOutput();
                        
                        foreach ($output as $msgKey => $toPrint) {
                            //If we have an ampersand at the end of our command, make sure we output the message no matter what.
                            if (substr(trim($message), -1, 1) == "&") {
                                $stdin[$stdinCnt] .= trim($toPrint->getMessage()) . " " . chr(3);
                            }
                            
                            if ($pipeCommandCounter >= count($messageCommands) || substr(trim($message), -1, 1) == "&") {
                                //Print outputs of the plugin
                                if (trim($toPrint->getMessage()) != "") {
                                    $outMessages = str_split($toPrint->getMessage(), 400);
                                    foreach ($outMessages as $outMessage) {
                                        SendMessage($toPrint->getServer(), $toPrint->getChannel(), $outMessage);
                                    }
                                }
                            } else {
                                //Prepare it for the next pipe down the line...
                                
                                /*chr(3) is used because it is neutral to the client (invisible), 
                                doesn't break IRC, and is splitable.*/
                                $stdin[$stdinCnt] .= $toPrint->getMessage() . " " . chr(3);
                            }
                        }
                        $stdinCnt++;
                    } else {
                        if ($userPermissionsLevel >= 0) { //Silence ignored people.
                            
                            SendCommand($server, "PRIVMSG NickServ STATUS " . $messageNick . "\r\n");
                            SendMessage($server, $messageChan, "Permission Denied. To do this, you need level: " . $pluginPermissionsLevel . " for `" . strtolower(trim($command[0])) . "`, you have: " . $userPermissionsLevel);
                        }
                    }
                }
            }
            
            $pipeCommandCounter++;
            
        } //foreach (piping loop)
    }
    
    //This sends off the chat to plugins that request it. Note that a chr(4) is appended to the beginning of the message.
    if (!$choke) {
        foreach ($plugins as $key => $plugin) {
            if (in_array("BawkBot.logMsg", $plugin['object']->getHooks())) {
                $plugin['object']->pushMessage($server, $messageChan, $messageNick, chr(4) . $messageCont, $messageType, $messageMask);
                $plugin['object']->pluginLogic();
                $output = $plugin['object']->receiveOutput();
                
                foreach ($output as $idk => $toPrint) {
                    //Print outputs of the plugin
                    if (trim($toPrint->getMessage()) != "") {
                        $outMessages = str_split($toPrint->getMessage(), 400);
                        foreach ($outMessages as $outMessage) {
                            SendMessage($toPrint->getServer(), $toPrint->getChannel(), $outMessage);
                        }
                    }
                }
            }
        }
    }
    
    
    //Sends chat responses that bawkbot sends out. 
    foreach ($plugins as $key => $plugin) {
        if (in_array("BawkBot.Messages", $plugin['object']->getHooks()) || in_array("BawkBot.logMsg", $plugin['object']->getHooks())) {
            foreach ($messageQueue as $outMessage) {
                $plugin['object']->pushMessage($server, $messageChan, "BawkBot", chr(4) . $outMessage, $messageType, $messageMask);
                $plugin['object']->pluginLogic();
            }
            
        }
    }
    
    $messageQueue = array();
    
}


/*IRC Engine is below.*/

function connectToIRC($host, $port, $nick, $channels, $onJoin, $authMethod)
{
    global $serverThreads;
    
    $thisServer             = array();
    $thisServer["SOCKET"]   = @fsockopen($host, $port, $errno, $errstr, 2);
    $thisServer["USERDATA"] = new ircUsersData($host);
    
    $thisServer["USERDATA"]->addUserData("BawkBotPermissions", "*", "*", array("isAuthenticated" => 0));
    
    $thisServer["host"]       = $host;
    $thisServer["authMethod"] = $authMethod;
    $thisServer["nick"]       = $nick;
    $thisServer["channels"]   = array();
    
    if ($thisServer['SOCKET']) {
        SendCommand($thisServer, "PASS NOPASS\r\n");
        SendCommand($thisServer, "NICK $nick\r\n");
        SendCommand($thisServer, "USER nick USING PHP IRC\r\n");
        $authed = 0;
        while (!feof($thisServer['SOCKET']) && $authed != 2) {
            $thisServer['READ_BUFFER'] = fgets($thisServer['SOCKET'], 1024);
            echo "[RECEIVE] " . str_replace("\r\n","",$thisServer['READ_BUFFER'])."\r\n";
            if (strpos($thisServer['READ_BUFFER'], "376")) {
                foreach ($channels as $key => $chan) {
                    SendCommand($thisServer, "JOIN $chan\r\n");
                    $thisServer["channels"][] = $chan;
                }
                SendCommand($thisServer, $onJoin . "\r\n");
                $authed++;
            }
            
            if (substr($thisServer['READ_BUFFER'], 0, 6) == "PING :") {
                SendCommand($thisServer, "PONG :" . substr($thisServer['READ_BUFFER'], 6) . "\r\n");
                $authed++;
            }
        }
        
        $serverThreads[] = $thisServer;
    } else {
        print "Failed connection.";
    }
    
}


function SendCommand($server, $cmd)
{
    @fwrite($server['SOCKET'], $cmd, strlen($cmd)); //sends the command to the server 
    $cmd = str_replace("\r\n", "", $cmd); // Force no extra newlines.
    echo "[SEND] $cmd \r\n"; //displays it on the screen 
}


function SendMessage($server, $channel, $message)
{
    global $messageQueue;
    $cmd = "PRIVMSG " . $channel . " :" . $message . "\r\n";
    @fwrite($server['SOCKET'], $cmd, strlen($cmd)); //sends the command to the server 
    $cmd = str_replace("\r\n", "", $cmd); // Force no extra newlines.
    echo "[SENDMSG] " . $cmd . "\r\n"; //displays it on the screen 
    
    $messageQueue[] = $message;
}

?>
