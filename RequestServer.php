<?php
/** */
class Request{
	/**
	 * If this is accessed from web.
	 * */
	public $public= false;
	/**
	 * Requested API version
	 * @type string
	 * */
	public $version;
	/**
	 * Requested path as array.
	 * @type string[]
	 * @example array( 'rest_api', … )
	 * */
	public $target;
	/**
	 * The class replaces any number in path with the defined string (e. g. `:id`) and these IDs keeps in this array.
	 * @type int[]
	 * */
	public $targetId= array();
	/**
	 * Request method.
	 * @type "get"|"post"|"delete"|"put"|"patch"
	 * */
	public $method;
	/** @var null|\Exception Will be thrown by \Response */
	public $error= null;
	private $body= array();
	/**
	 * @param \AbstractConfig $config
	 * @param string $get Target URL `version/target`
	 * @param "get"|"delete"|"put"|"post" $method
	 * @param array<string, mixed> $body
	 * */
	public function __construct($config, $get, $method= 'get', $body= array()){
		$target= explode('/', $get);
		$this->body= $body;
		$this->method= $method;
		$this->version= array_shift($target);
		if(array_search($this->version, $config->versions)===false)
			$this->error= new Exception('Requested not existing version.', 400);
		
		$path_test= realpath('');
		foreach($target as $target_nth_id=> $target_nth){
			if($target_nth[0]==='_'){
				$this->error= new Exception('It is forbidden to request endpoints starting "_".', 403);
				break;
			}
			if($target_nth[0]==='-') continue;
			
			$path_test.= '/'.$target_nth;
			if(is_dir($path_test)) continue;
			
			$this->targetId[]= mysql_escape_string($target_nth);
			$target[$target_nth_id]= $config->path_id;
		}
		
		$this->target= $target;
		return $this;
	}
	/** @param str $needle */
	public function targetContains($needle){ return array_search($needle, $this->target); }
	public function targetPath(){ return implode('/', $this->target); }

	public function bodyItem($key){ return $this->body[$key]; }
	public function bodyItems($keys){ return array_map(array($this, 'bodyItem'), $keys); }
	/**
	 * @return string - JSON representation of the class.
	 * */
	public function toJSON(){ return json_encode(get_object_vars($this)); }
	/**
	 * Returns headers item based on given `$name`.
	 * @param "Authorization"||"Accept" $name
	 * */
	static function headerItem($name, $default= NULL){
		$key= "HTTP_".strtoupper(str_replace('-', '_', $name));
		return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
	}
}
