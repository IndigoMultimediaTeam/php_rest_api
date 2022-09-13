<?php
/**
 * Helper class wrapping the request info such as request method, target path query (`/…/path/ → ?__target=…/path` via `.htaccess`), ….
 * The `__target` is connected with `$config->path_key`.
 * Primary focused for REST API.
 * Fisrt item in path is used as `$version`.
 * Numeric folder(s) are replaced with {@link \AbstractConfig->path_id} and saved into {@link \Request->$targetId}.
 * The path cannot contains folder(s)/endpoint(s) with '_' prefix – use for your internal purposes.
 * */
class Request{
	/**
	 * If this is accessed from web.
	 * */
	public $public= true;
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
	 * */
	public function __construct($config){
		$target= explode('/', $_GET[$config->path_key]);
		$this->method= strtolower($_SERVER['REQUEST_METHOD']);
		$this->version= array_shift($target);
		if(array_search($this->version, $config->versions)===false)
			$this->error= new Exception('Requested not existing version.', 400);
		
		$path_test= realpath('');
		foreach($target as $target_nth_id=> $target_nth){
			if($target_nth[0]==='_'){
				$this->error= new Exception('It is forbidden to request endpoints starting "_".', 403);
				break;
			}
			$path_test.= '/'.$target_nth;
			if(is_dir($path_test)) continue;
			
			$this->targetId[]= mysql_escape_string($target_nth);
			$target[$target_nth_id]= $config->path_id;
		}
		
		$this->target= $target;
		$request_type= $this->headerItem('Content-Type');
		if($request_type&&$request_type!=='application/json')
			$this->error= new Exception('Only "application/json" is supported as "Content-Type" in HTTP header.', 400);
		
		if($this->method==='get'){
			$this->body= $_GET;
			return $this;
		}
		try{
			$body= \file_get_contents('php://input');
			$this->body= \json_decode($body, true);
			if($body!=='' && ( !$this->body || $this->body==$body )) throw new Exception('Wrong format – accepts empty or JSON object');
		}
		catch(\Exception $error){ $this->error= new Exception("Request body is not valid (parsing with error '{$error->getMessage()}')", 400); }
		return $this;
	}
	/** @param str $needle */
	public function targetContains($needle){ return array_search($needle, $this->target); }
	public function targetPath(){ return implode('/', $this->target); }

	public function bodyItem($key){ return $this->body[$key]; }
	public function bodyItems($keys){ return array_map(array($this, 'bodyItem'), $keys); }
	/**
	 * @param string[] $header - Header items wanted to log
	 * @return array<string, mixed> - Array representation of the class.
	 * */
	public function toArray($header){
		$out= get_object_vars($this);
		$header= array_map('Request::headerItem', $header);
		$out['header']= $header;
		return $out;
	}
	/**
	 * @param string[] $header - Header items wanted to log
	 * @return string - JSON representation of the class.
	 * */
	public function toJSON($header){ return json_encode($this->toArray($header)); }
	/**
	 * Returns headers item based on given `$name`.
	 * @param "Authorization"||"Accept" $name
	 * */
	static function headerItem($name, $default= NULL){
		$key= strtoupper(str_replace('-', '_', $name));
		if($key!=='CONTENT_TYPE') $key= "HTTP_".$key;
		return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
	}
}
