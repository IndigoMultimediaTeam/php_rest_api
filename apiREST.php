<?php
/**
 * @param \AbstractConfig $config
 * @param \Request $request
 * */
function api($config, $request){
	extract($config->vars_shared);
	foreach($config->requires_once as $path_req)
		require_once($path_req);

	$folder= $request->targetPath();
	$path= realpath($folder);
	if($path===false||!is_dir($path)) throw new \Exception("Endpoint '$folder' doesn’t exist.", 404);
	$file= $path.'/'.$request->method.'.php';
	if(file_exists($file)) return require_once $file;
	
	$methods= array();
	foreach(glob($path.'/{get,post,put,patch,delete}.php', GLOB_BRACE) as $method)
		$methods[]= strtoupper(substr(basename($method), 0, -4));

	header("Allow: ".implode(', ', $methods));
	throw new \Exception("Endpoint '$folder' doesn’t support '$request->method' method.", 405);
}
