<?php
/**
 * Configuration to be used across other parts.
 * ```php
 * //usage
 * class config extends AbstractConfig{
 *	static $secret= 'SECRET'; // required
 *	//similary custom data, if needed.
 * }
 * $config= new config(); // throws error if not `$secret`, see above
 * ```
 * You can also edit odthers main keys (e. g. `$expiration`).
 * */
abstract class AbstractConfig{
	/** @var str Main api URL access point */
	public $api_url= '../api';
	/** @var str Secret argument for JWT */
	public $secret;
	/** @var int Expiration time in seconds for JWT */
	public $expiration= 3600;
	/**
	 * Use following `.htaccess`:
	 * ```text
	 *  main_folder/
	 *   index.php //main API file
	 *   .htaccess
	 *   this_file_folder/
	 *     
	 *  .htaccess
	 *  RewriteEngine on
	 *  RewriteRule ^(.*[^/\?])/?(\??.*) index.php?__target=$1&$2 [QSA,L]
	 * ```
	 * @var str Name of the GET key where the requested uri is stored (`/…/path/…` → `?__target=…/path/…` via `.htaccess`)
	 * */
	public $path_key= '__target';
	/** @var string The name of the folder for processing requests with record ID. */
	public $path_id= ':id';
	/** @var str Endpoint for Authentication based on your Authorization class. */
	public $auth_path= 'auth';
	/** @var str Information for auth documentation, where to get typically `client_secret`. */
	public $auth_emails= 'info@indigo.cz';
	/** @var str[] Supported API versions and their order. */
	public $versions= array();

	public function sqlSelectId($column= 'id', $as= 'id'){ return "$column*8+5 AS $as"; }
	public function sqlWhereId($column= 'id'){ return "$column*8+5"; }
	
	public function __construct(){
		if(!$this->secret) throw new \InvalidArgumentException('Please set `Config::$secret`!');
		return $this;
	}
}
