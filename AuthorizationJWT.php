<?php
/* import { JWT, ExceptionJWT } */require_once 'libs/JWT.php';
/** This class provides simple authentication similar to OAuth using JWT. */
class Authorization{
	static $phases= array(
		'[1] Authentication',
		'[1.1] Authorize',
		'[1.2] Authorize > JSON WEB Token',
		'[1.3] Authorize > Scope'
	);
	static $errors= array(
		'header_no_authorization'=> 'Missing "Authorization" key in header.',
		'no_access_token'=> 'Wrong JWT type, for access REST API use `access_token`.',
		'not_implemented'=> 'Not implemented.',
		'wrong_path'=> 'For generating token use "token" endpoint. For authentication purposes use "authorize" endpoint.',
		'wrong_method'=> 'For "/auth" endpoint only POST method is supported.'
		//+ see JWT
	);
	static $token_types= array(
		'access_token'=> 'access',
		'refresh_token'=> 'refresh'
	);
	/** String representation of path (for \Request->target[0]), which schould understand as "login part". */
	static $auth_key= 'auth';
	/** @var \JWT */
	private $jwt;
	/** @var int Expiration time in seconds for JWT */
	public $expiration= 3600;
	/**
	 * Client authentication process.
	 * @param \AbstractConfig $config
	 * @param \Request $request
	 * @param \Response $response
	 * */
	private function authorize($config, $request, $response){
		$response->phase('[2] Authorization request processing');
		$path= $config->auth_path.'/';
		$method= $request->method;
		if($method!=='post')
			return $response->error(new \Exception(self::$errors['wrong_method'], 405));
		switch($request->target[1]){
			case 'token': $path.= 'token/'; break;
			case 'authorize': $path.= 'authorize/'; break;
			case 'update': $path.= 'update/'; break;
			default: return $response->error(new \Exception(self::$errors['wrong_path']), 400);
		}
		$path.= $method.'.php';
		if(!file_exists($path)) return $response->error(new \Exception(self::$errors['not_implemented'], 500));
		try{
			$dsbvkjbk= $path;
			foreach($config->requires_once as $path_req)
				require_once($path_req);
			$response->success(require_once $dsbvkjbk);
		}
		catch(\Exception $error){ $response->error($error); }
	}
	/**
	 * Validate API request authentication or invoke authentication process (@see authorize) and end request (via `$response`).
	 * @param \AbstractConfig $config
	 * @param \Request $request
	 * @param \Response $response
	 * @return array
	 * */
	public function __construct($config, $request, $response){
		$this->expiration= $config->expiration;
		$this->jwt= new JWT($config->secret, 'HS384', $config->expiration, 3);
		if($request->target[0]===self::$auth_key)
			return $this->authorize($config, $request, $response);

		$Authorization= $request->headerItem("Authorization");
		if($Authorization===NUll){
			self::phase($response, 1);
			return $response->error(new \Exception(self::$errors['header_no_authorization']), 401);
		}
		$Authorization_arr= explode(" ", $Authorization);
		try{
			$jwt_playload= $this->jwt->decode($Authorization_arr[1]);
			if($jwt_playload['type']!==self::$token_types['access_token']){
				self::phase($response, 2);
				return $response->error(new \Exception(self::$errors['no_access_token']), 401);
			}
			if($jwt_playload['scope']!==$request->version){
				self::phase($response, 3);
				return $response->error(new \Exception("Given access token is not allowed to use '$request->version' version", 403));
			}
			$this->jwt_playload= $jwt_playload;
			return $this;
		} catch(ExceptionJWT $error){
			self::phase($response, 2);
			return $response->error($error, 401);
		} catch(\Exception $error){
			self::phase($response, 0);
			return $response->error($error, 500);
		}
	}
	/**
	 * @param "access_token"||"refresh_token" $type
	 * @param array<string, mixed> $playload
	 * @return string URL safe JWT token.
	 * @throws \ExceptionJWT
	 * */
	private function jwtEncode($type, $playload= array()){ return $this->jwt->encode(array_merge($playload, array( 'type'=> self::$token_types[$type] ))); }
	/**
	 * @param string $token
	 * @param "access_token"||"refresh_token" $type
	 * @throws \Exception
	 * */
	private function jwtDecode($candidate, $type){
		try{
			$playload= $this->jwt->decode($candidate);
		} catch(\Exception $error){
			throw new \Exception('Invalid refresh token: '.$error->getMessage(), 401);
		}
		if($playload['type']!==self::$token_types[$type])
			throw new \Exception("Invalid JWT token type, use '$type'.", 401);
		return $playload;
	}
	/** @param \Response $response */
	static private function phase($response, $index){ $response->phase(self::$phases[$index]); }
}
