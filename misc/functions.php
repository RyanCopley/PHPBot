<?php

////////////////////////////////////////////////////////////////////////////////////////////////////////////
//bawkplugins.php functions                                                                               //
////////////////////////////////////////////////////////////////////////////////////////////////////////////

/*System functions-- Do not utilize in your plugins as these methods are not guaranteed to stay the same!*/
function loadAllPlugins()
{
    $pluginSet = array();
    /*Loads all plugins, does basic PHP check to ensure that there are no fatal PHP errors.*/
    if ($handle = opendir('plugins')) {
        /* This is the correct way to loop over the directory. */
        while (false !== ($entry = readdir($handle))) {
            $plugin = loadPlugin($entry);
            if ($plugin != null) {
                $pluginSet[] = $plugin;
            }
        }
        closedir($handle);
    }
    return $pluginSet;
}

function loadPlugin($entry)
{
    global $plugins;
    
    foreach ($plugins as $key => $check) {
        if ($check["filename"] == $entry) {
            print "plugin already loaded!";
            return null;
        }
    }
    
    if (substr($entry, -4, 4) == "bawk") {
        $pluginHash = md5(file_get_contents("plugins/" . $entry));
        if ($pluginHash == file_get_contents("plugins/pluginSignatures/" . substr($entry, 0, strlen($entry) - 5) . ".feed") || chechFileSyntax("plugins/" . $entry)) {
            print "Loading Plugin: $entry\r\n       >";
            
            
            //Our temporary name.
            $pluginClassName = substr($entry, 0, strlen($entry) - 5) . time();
            
            //The REAL name. Only used for references.
            $pluginClass = substr($entry, 0, -5);
            
            //Get the plugin data. This is the master copy of it.
            $pluginData = file_get_contents("plugins/" . $entry);
            
            //Swap out the class name.
            $pluginData = preg_replace("/class " . $pluginClass . "/i", "class " . $pluginClassName, $pluginData, 1);
            
            if (substr_count($pluginData,$pluginClassName)>0){
                
                $log = fopen("plugins/tmp/" . $pluginClassName . ".bawk", "w+");
                fwrite($log, $pluginData);
                fclose($log);
                
                //Include it into the runtime environment
                include_once("plugins/tmp/" . $pluginClassName . ".bawk");
                //Delete it from storage. It is no longer neccessary.
                unlink("plugins/tmp/" . $pluginClassName . ".bawk");
                
                //Update the signature.
                $log = fopen("plugins/pluginSignatures/" . substr($entry, 0, strlen($entry) - 5) . ".feed", "w+");
                fwrite($log, $pluginHash);
                fclose($log);
                
                return array(
                    "object" => new $pluginClassName,
                    "filename" => $entry,
                    "name" => $pluginClass
                );
            }else{
            
            print "[RED]BROKEN PLUGIN: $entry - Class name is incorrect! Should be: ".$pluginClass."\r\n";
            return null;
            }
        } else {
            print "[RED]BROKEN PLUGIN: $entry - Invalid file syntax (PHP ERROR)\r\n";
            return null;
        }
    }
}


function unloadPlugin($entry)
{
    global $plugins;
    
    foreach ($plugins as $key => $plugin) {
        if ($plugin["filename"] == $entry . ".bawk") {
            unset($plugin["object"]);
            unset($plugins[$key]);
            print "Unloaded: " . $entry . "\r\n";
            return true;
        }
    }
    return false;
}


function chechFileSyntax($fileName)
{
    if (file_exists($fileName) && is_readable($fileName)) {
        $checkSyntax = shell_exec('php -l ' . escapeshellarg($fileName));
        if (preg_match("/No/", $checkSyntax)) {
            print "Plugin is valid code.\r\n";
            return true; // No error
        } else {
            print "Plugin has a syntax error.\r\n";
            return false; // Contain error_get_last()
        }
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////

function str_replace_outside_quotes($replace, $with, $string)
{
    $result  = "";
    $outside = preg_split('/("[^"]*"|\'[^\']*\')/', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
    while ($outside)
        $result .= str_replace($replace, $with, array_shift($outside)) . array_shift($outside);
    return $result;
}

//Does not include the quotes.
function parse_delimiters($input, $delim)
{
    $retArr = array();
    $split  = str_split($input);
    
    $quoteType = "";
    $inQuote   = false;
    $buffer    = "";
    foreach ($split as $value) {
        if (($value == '"' || $value == "'") && ($quoteType == "" || ($quoteType == $value))) {
            $quoteType = $value;
            $inQuote   = !$inQuote;
            
            if (!$inQuote) {
                $quoteType = "";
            }
        }
        if (!$inQuote && $value == $delim) {
            $retArr[] = trim($buffer);
        }
        $buffer .= $value;
        
        if (!$inQuote && $value == $delim) {
            $buffer = "";
        }
    }
    
    $retArr[] = trim($buffer);
    
    return $retArr;
    
}

//Includes the quotes.
function parse_command_delimiters($input, $delim)
{
    $retArr = array();
    $split  = str_split($input);
    
    $quoteType = "";
    $inQuote   = false;
    $buffer    = "";
    foreach ($split as $value) {
        if (($value == '"' || $value == "'") && ($quoteType == "" || ($quoteType == $value))) {
            $quoteType = $value;
            $inQuote   = !$inQuote;
            
            if (!$inQuote) {
                $quoteType = "";
            }
        }
        if (!$inQuote && $value == $delim) {
            $retArr[] = $buffer;
        }
        if ((!$inQuote && ($value != '"' && $value != "'")) || ($inQuote && $value != $quoteType)) {
            $buffer .= $value;
        }
        
        if (!$inQuote && $value == $delim) {
            $buffer = "";
        }
    }
    
    $retArr[] = trim($buffer);
    
    return $retArr;
    
}


function pretty_json($json)
{
    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;
    
    for ($i = 0; $i <= $strLen; $i++) {
        // Grab the next character in the string.
        $char = substr($json, $i, 1);
        
        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;
            
            // If this character is the end of an element, 
            // output a new line and indent the next line.
        } else if (($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos--;
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        // Add the character to the result string.
        $result .= $char;
        
        // If the last character was the beginning of an element, 
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos++;
            }
            
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        $prevChar = $char;
    }
    
    return $result;
}

?>