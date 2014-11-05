<?php
/* Simple message abstraction class.*/
class ircMesssage
{
    private $server;
    private $channel;
    private $nick;
    private $message;
    private $type;
    private $hostmask;
    
    public function __construct($_server, $_channel, $_nick, $_message, $_type = "PRIVMSG", $_hostmask = "")
    {
        $this->server   = $_server;
        $this->channel  = $_channel;
        $this->nick     = $_nick;
        $this->message  = $_message;
        $this->type     = $_type;
        $this->hostmask = $_hostmask;
    }
    public function getServer()
    {
        return $this->server;
    }
    public function getChannel()
    {
        return $this->channel;
    }
    public function getNick()
    {
        return $this->nick;
    }
    public function getMessage()
    {
        return $this->message;
    }
    public function getMessageType()
    {
        return $this->type;
    }
    public function getHostMask()
    {
        return $this->hostmask;
    }
    
}

?>