<?php
class ircUsersData
{
    private $userData;
    private $fileName;
    
    public function __construct($_host)
    {
    	$this->loadUserData($_host);
    }
    
    public function getUserData($plugin, $channel, $nick = "")
    {
        $channel = strtoupper($channel);
        $nick = strtoupper($nick);
        if ($nick == "") {
            return (isset($this->userData[$plugin][$channel])) ? $this->userData[$plugin][$channel] : array(); //Returns all data from the nick perspective.
        } else {
            return (isset($this->userData[$plugin][$channel][$nick])) ? $this->userData[$plugin][$channel][$nick] : array(); //Returns all data for a specific nick.
        }
    }
    
    
    
    public function existsUserData($plugin, $channel, $nick, $key)
    {
        $channel = strtoupper($channel);
        $nick = strtoupper($nick);
        if (!isset($this->userData[$plugin])) {
            return false;
        }
        
        if (!isset($this->userData[$plugin][$channel])) {
            return false;
        }
        
        if (!isset($this->userData[$plugin][$channel][$nick])) {
            return false;
        }
        
        if (!isset($this->userData[$plugin][$channel][$nick][$key])) {
            return false;
        }
        return true;
        
    }
    
    
    
    public function addUserData($plugin, $channel, $nick, $value)
    {
        $channel = strtoupper($channel);
        $nick = strtoupper($nick);
        if (!isset($this->userData[$plugin])) {
            $this->userData[$plugin] = array();
        }
        if ($channel != "*") {
            if (!isset($this->userData[$plugin][$channel])) {
                $this->userData[$plugin][$channel] = array();
            }
            if ($nick != "*") {
                if (!isset($this->userData[$plugin][$channel][$nick])) {
                    $this->userData[$plugin][$channel][$nick] = array();
                }
            }
        }
        
        
        foreach ($this->userData[$plugin] as $ckey => &$chan) {
            if ($ckey == $channel || $channel == "*") {
                foreach ($chan as $nkey => &$unick) {
                    if ($nkey == $nick || $nick == "*") {
                        $unick = array_unique(array_merge($unick, $value));
                        
                    }
                    
                }
            }
            
        }
        
        $this->saveUserData();
    }
    
    
    public function removeUserData($plugin, $channel, $nick, $key)
    {
        $channel = strtoupper($channel);
        $nick = strtoupper($nick);
        if (!isset($this->userData[$plugin])) {
            $this->userData[$plugin] = array();
        }
        if (!isset($this->userData[$plugin][$channel])) {
            $this->userData[$plugin][$channel] = array();
        }
        if (!isset($this->userData[$plugin][$channel][$nick])) {
            $this->userData[$plugin][$channel][$nick] = array();
        }
        if (isset($this->userData[$plugin][$channel][$nick][$key])) {
            unset($this->userData[$plugin][$channel][$nick][$key]);
        }
        $this->saveUserData();
    }
    
    
    private function saveUserData()
    {
        $log = fopen($this->fileName, "w+");
        fwrite($log, pretty_json(json_encode($this->userData)));
        fclose($log);
    }
    
    public function loadUserData($_host){
	    $this->fileName = "userdata/" . $_host . ".egg";
        if (file_exists($this->fileName)) {
            $this->userData = json_decode(file_get_contents($this->fileName), true);
        } else {
            $this->userData = array(); //Create a blank document for user preferences.
        }
    }
    
}

?>