<?php

class aliaser
{
    private $aliases;
    
    public function addAlias($alias, $command)
    {
        $this->aliases[$alias] = $command;
        
        $this->saveAliases();
    }
    
    public function removeAlias($alias)
    {
        if (isset($this->aliases[$alias])) {
            unset($this->aliases[$alias]);
        }
        $this->saveAliases();
    }
    
    public function aliasExists($alias)
    {
        return isset($this->aliases[$alias]);
    }
    
    public function performAlias($command)
    {
        if (array_key_exists($command[0], $this->aliases)) {
            $newCommand = str_replace("\[","<~~~~~~~~~BAWKBOTCOMMAND------>",$this->aliases[$command[0]]);
            $i          = 0;
            
            //Searches for square brackets, and returns them as an array.
            if (preg_match_all("/\[([^\]]*)\]/", $newCommand, $matches)) {
                $groupText = "";
                foreach ($matches[1] as $k => $group) {
                    //$group = "x-x"
                    $range = explode("-", $group);
                    if (in_array("in", $range) == false) {
                        if ($range[1] == "*") {
                            $range[1] = 250; //Safe number, I guess.	
                        }
                        
                        if (count($range) == 1) {
                            $groupText = trim($command[$range[0]]);
                        } else {
                            //Assumed two.
                            $groupText = "";
                            for ($a = $range[0]; $a < $range[1]; $a++) {
                                if (isset($command[$a])) {
                                    $groupText .= trim($command[$a]) . " ";
                                } else {
                                    break;
                                }
                            }
                        }
                        
                        $newCommand = str_replace("[" . $group . "]", trim($groupText), trim($newCommand));
                        
                    }
                    
                    
                }
                  
                $i++;
            }
            
            $command = parse_delimiters($newCommand, " ");
        }
        return str_replace("<~~~~~~~~~BAWKBOTCOMMAND------>","[",$command);
    }
    
    public function saveAliases()
    {
        $f = fopen("userdata/aliases.egg", "w");
        fwrite($f, pretty_json(json_encode($this->aliases)));
        fclose($f);
    }
    public function loadAliases()
    {
        $f             = file_get_contents("userdata/aliases.egg");
        $this->aliases = json_decode($f, true);
    }
    

}

?>