<?php

class pastebin{
	private $name = "Undefined";
	private $title = "N/A";
	private $text = "No text set";
	private $lang = "text";

	public function setPasteName($name){$this->name = $name; return $this;}
	public function setTitle($title){$this->title = $title; return $this;}
	public function setText($text){$this->text = $text; return $this;}
	public function setLanguage($language){$this->lang = $language; return $this;}

	public function execute(){
		$url = "http://bilberry.speedycloud.co.uk/p/upload/";
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		$fields = array(
			'name'=>($this->name),
			'title'=>($this->title),
            'content'=>($this->text),
            'filetype'=>($this->lang),
            'password'=>'password',
            'submit'=>'submit'
        );

curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

		curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

		$data = curl_exec($ch);

		$finalURL = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);//explode("/",curl_getinfo($ch,CURLINFO_EFFECTIVE_URL));

print $finalURL;
		curl_close($ch);

		return $data;// array_pop($finalURL);
	}
}
