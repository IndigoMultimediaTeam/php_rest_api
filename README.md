[![LTS+sub-branches](https://img.shields.io/badge/submodule-LTS+sub--branches-informational?style=flat-square&logo=git)](https://github.com/IndigoMultimediaTeam/lts-driven-git-submodules)
# PHP REST API
Actually, this repository provides a generic way to implement API (so not only REST API). *Tested/used in PHP <5.3*.
The expected directory structure is:
```
api/
	this_repo/
	_config.php … see below
	index.php … or any other entry php file
	.htaccess
```
The API entry is splitted into several steps:
1. config … see [AbstractConfig.php](./AbstractConfig.php)
1. request processing … see for exmaple [RequestHTTP.php](./RequestHTTP.php), [RequestServer.php](./RequestServer.php)
1. special cases … see for exmaple [AuthorizationJWT.php](./AuthorizationJWT.php), [HelpREST](./HelpREST.php)
1. the actual processing of the request … see [apiREST.php](./apiREST.php)
1. response … see for example [ResponseJSON](./ResponseJSON.php)

## Use in your repo
**Use**:
```bash
cd TARGET_PATH
git submodule add -b main --depth=1 git@github.com:IndigoMultimediaTeam/php_rest_api _internal
```
… more info [`git submodule`](https://gist.github.com/jaandrle/b4836d72b63a3eefc6126d94c683e5b3).

*Target folder (`_internal`) schouldn’t be accessible from outside of the web!*

## Minimal REST API example whith authentication
<details>
<summary><b>&lowbar;config.php</b></summary>

```php
<?php
/* import { AbstractConfig } from */require_once '_internal/AbstractConfig.php';
class config extends AbstractConfig{
	public $secret= 'secret for authentication/authorization';
	/** Result of authentication */
	public $client;
	public $versions= array(
		'warty-warthog',
		'hoary-hedgehog',
		'dapper-drake'
	);
}
```
</details>
<details>
<summary><b>index.php</b></summary>

```php
<?php
require_once '../../kernel/kernel.php';//fix path
require_once '../utils/inc.db_utils.php';//fix path
/* import { Config } from */require_once '_config.php';
$config= new config();
$config->api_url= 'https://'.$_SERVER['HTTP_HOST'].'/api/rest';
/* import { Request } */require_once '_internal/RequestHTTP.php';
$request= new Request($config);
// api logging if needed, see later
/* import { Response } */require_once '_internal/ResponseJSON.php';
$response= new Response($request);
// special help endpoints, see later
/* import { Authorization } */require_once '_internal/AuthorizationJWT.php';
$auth= new Authorization($config, $request, $response);
$config->client= $auth->jwt_playload;

/* import { api } */require_once '_internal/apiREST.php';
$response->phase('[2] Request processing');
					 try{	 return $response->success(api($config, $request)); }
catch(\Exception $error){	 return $response->error($error); }
```
</details>

… for authorization via [AuthorizationJWT.php](./AuthorizationJWT.php) you need to provide *auth* folder (see `AbstractConfig->$auth_path`):

```
api/
	this_repo/
	…
	auth/
		token/
			post.php
		authorize/
			post.php
		update/
			post.php
```
… this is similar to REST API handlering via [apiREST](./apiREST.php).

The folder structure follows the requested URL:
```
https://api_url/api_version/Folder/…

folder:
api/
	this_repo/
	Folder/…
```
…request method indicates which file to use:
```
curl -X POST https://api_url/api_version/Folder

file:
folder:
api/
	this_repo/
	Folder/post.php
```

For more extended version visits [DHLC-Internet-Networking/web/api/rest at dev/php_rest_api · jaandrle/DHLC-Internet-Networking](https://github.com/jaandrle/DHLC-Internet-Networking/tree/dev/php_rest_api/web/api/rest).


## Minimal version of making API accessible from inside the server
```php
<?php
/**
 * @param string $get Target URL `version/target`
 * @param "get"|"delete"|"put"|"post" $method
 * @param array<string, mixed> $body
 * */
function api($get, $method= 'get', $body= array()){
	$cwd= $GLOBALS['__dROOT'].'api/rest/';
	require_once $cwd.'_internal/libs/globals.php';
	/* import { Config } from */require_once $cwd.'_config.php';
	$config= new config();
	$config->api_url= 'https://'.$_SERVER['HTTP_HOST'].'/api/rest';
	/* import { Request } */require_once $cwd.'_internal/RequestServer.php';
	$request= new Request($config, $get, $method, $body);
	extract($config->vars_shared);
	
	$folder= $cwd.$request->targetPath();
	$path= realpath($folder);
	if($path===false||!is_dir($path)) throw new \Exception("Endpoint '$folder' doesn’t exist.", 404);
	$file= $path.'/'.$request->method.'.php';
	if(file_exists($file)) return require_once $file;
}
```
