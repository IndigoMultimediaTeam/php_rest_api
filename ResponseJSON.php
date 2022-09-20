<?php
/* import { http_response_code } */require_once 'libs/http_response_code.php';
/**
 * Prepares the API response. For basic usage, there are two public methods available `error`/`success`.
 * `error` instantly exists with given HTTP status code and Error JSON. Similarly for `succes`.
 * Success response data can be created granually using `dataSet`.
 * */
class Response{
	/** @var str Will be used in error response. */
	private $phase;
	/** @var array Will be merged in `success` method. */
	private $data= array();

	/**
	 * Exits when request header is not `response-type: application/json`.
	 * @param \Request $request
	 * */
	public function __construct($request){
		$this->phase= '[0] Request validation';
		$this->method= $request->method;
		$response_type= $request->headerItem('Accept');
		if($response_type && !preg_match('/(\*|application)\/(\*|json)/', $response_type))
			return $this->error(new Exception('Only "application/json" is supported as "Accept" in HTTP header.', 1000), 406);
		if($request->error)
			return $this->error($request->error);
		$this->phase= '[0] Unknown';
	}
	/** @param str $phase */
	public function phase($phase){ $this->phase= $phase; }
	/**
	 * Prepare data array – set given `$key` to `$value.
	 * @param str $key
	 * @param mixed $value
	 * @return $this
	 * */
	public function dataSet($key, $value){
		$this->data[$key]= $value;
		return $this;
	}
	/**
	 * Exits with JSON ('error=0').
	 * ```json
	 * {
	 *	"timestamp": "(string) SQL TIMESTAMP",
	 *	"error": "(0) No error",
	 *	"data": "(array) Given data array"
	 * }
	 * ```
	 * @param int $http_code
	 * @param \Exception $exception
	 * */
	public function success($data){
		if(is_array($data))
			$data= array_merge($this->data, $data);
		if($this->method==="post") http_response_code(201);
		return $this->exitWith(array(
			'error'=> 0,
			'data'=> $data
		));
	}
	/**
	 * Exits with JSON and HTTP status code.
	 * ```json
	 * {
	 *	"timestamp": "(string) SQL TIMESTAMP",
	 *	"error": "(integer) Error code (≠0)",
	 *	"message": "(string) Error message",
	 *	"phase": "(string) API phase, when error occurs"
	 * }
	 * ```
	 * @param int $http_code Or used `$exception->getCode`.
	 * @param \Exception $exception
	 * */
	public function error($exception, $http_code= false){
		http_response_code($http_code!==false ? $http_code : $exception->getCode());
		return $this->exitWith(array(
			'error'=> $exception->getCode() ? $exception->getCode() : 1000,
			'message'=> $exception->getMessage(),
			'phase'=> $this->phase
		));
	}
	private function exitWith($response){
		if(!isset($response['timestamp'])) $response['timestamp']= date('Y-m-d H:i:s');
		header('Content-Type: application/json; charset=utf-8');
		exit(json_encode($response));
	}
}
