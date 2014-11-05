<?php

class commandTimer{
	private $commandQueue;
	
	public function __construct(){
		$this->commandQueue = array();
	}
	public function addTimer($server, $messageNick, $messageChan, $command, $time){
		$newCommand = array("Server"=>$server,"Nick"=>$messageNick,"Channel"=>$messageChan,"Command"=>$command,"ExecuteTime"=>$time);
		$commandKey = md5(serialize($newCommand));
		$this->commandQueue[$commandKey] = $newCommand;
		return $commandKey;
		
	}
	
	public function removeTimer($timer){
		if (isset($this->commandQueue[$timer])){
			unset($this->commandQueue[$timer]);
		}
	}
	
	public function expiredCommands(){
		foreach ($this->commandQueue as $key=>$command){
			if (time() >= $command['ExecuteTime']){
				//Execute it.
				bawkBotExecuteCommand($command['Server'], $command['Nick'], "PRIVMSG", $command['Channel'], $command['Command'],"",true);
				//Since it's expired, lets remove it.
				unset($this->commandQueue[$key]);
			}
		}
	}	
}

?>