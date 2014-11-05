<?php

class plugin
{
    private $hooks;
    private $messages;
    private $outMessages;
    
    public function __construct()
    {
        echo $this->pluginName() . " Plugin v" . $this->pluginVersion() . " Loaded\r\n";
        $this->messages    = array();
        $this->hooks       = array();
        $this->outMessages = array();
    }
    
    public function __destruct()
    {
        echo "Destroying plugin!";
    }
    
    //Used for both outputting the name of the plugin, and for plugin data retrieval.
    public function pluginName()
    {
        return "Undefined";
    }
    
    //For you know... reference.
    public function pluginContact()
    {
        return "Undefined";
    }
    
    //Versioning information.
    public function pluginVersion()
    {
        return "Undefined";
    }
    
    public function pluginHelp($man, $message)
    {
        // Flag for un-overridden MAN pages. MAN plugin will use this to complain.
        return "NOMAN";
    }
    
    public function getUserPermissions($server, $channel, $nick, $command)
    {
        return 9001;
    }
    
    
    public function pluginLogic()
    {
        print "Performing plugin without any logic :(.";
    }
    
    
    
    
    /*
    * The following methods are non-overridable as they are part of the core functionality of BawkBot.
    * You use these commands IN your plugin to interface with the bot.
    */
    
    
    final public function getUserDataSingleton($_server)
    {
        return $_server["USERDATA"];
    }
    
    final public function getCommandTimerSingleton()
    {
        global $commandTimer;
        return $commandTimer;
    }
    
    final public function isCommand($command, $name)
    {
        return (strtolower($command[0]) == COMMANDSYMBOL . strtolower($name));
    }
    
    final public function parseCommands($commands, $split)
    {
        $tmp = parse_command_delimiters($commands, " ");
        $ret = array();
        
        $split--;
        $killflag = false;
        
        for ($i = 0; $i < $split; $i++) {
            if (isset($tmp[$i])) {
                $ret[] = $tmp[$i];
            } else {
                $killflag = true;
            }
        }
        
        if ($killflag == false) {
            $tmp2 = "";
            for ($i = $split; $i < count($tmp); $i++) {
                $tmp2 .= $tmp[$i] . ' ';
            }
            if ($tmp2 != "") {
                $ret[] = rtrim($tmp2);
            }
        }
        return $ret;
    }
    
    final public function parseSeperateCommands($commands)
    {
        return explode(" " . chr(3), $commands);
    }
    
    
    final public function deconstructSeperateCommands($commands)
    {
        return str_replace(chr(3), "", $commands);
    }
    
    
    final public function isMultiMessage($commands)
    {
        return substr_count($commands, chr(3)) > 0;
    }
    
    //For the plugin to call to receive a message from its stack.
    final public function receiveMessage()
    {
        return array_pop($this->messages);
    }
    
    
    final public function messageCount()
    {
        return count($this->messages);
    }
    
    //For the bot, to push messages to the stack.
    final public function pushMessage($server, $channel, $nick, $message, $type, $mask)
    {
        $this->messages[] = new ircMesssage($server, $channel, $nick, $message, $type, $mask);
    }
    
    //For the bot, to pull the outputs the bot gave.
    final public function receiveOutput()
    {
        $tmp               = $this->outMessages;
        $this->outMessages = array();
        return $tmp;
    }
    
    //For the plugin, to push messages to the output stack.
    final public function sendMessage($server, $channel, $message)
    {
        $this->outMessages[] = new ircMesssage($server, $channel, "BawkBot", $message);
    }
    
    final public function getHooks()
    {
        return $this->hooks;
    }
    
    final public function addHook($hook)
    {
        $this->hooks[] = $hook;
    }
    
}

?>