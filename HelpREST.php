<?php
/**
 * Mostly dev version. Connects rest api special endpoint (by default '--help') and files 'help.php' in file system.
 * ```
 * api_main
 * ··this_file_folder/
 * ··auth/
 * ····help.php
 * ····token/
 * ······help.php
 * ······post.php
 * ··endpoint/
 * ····help.php
 * ····get.php
 * ```
 * */
class Help{
	/**
	 * @param str [$help_endpoint='--help']
	 * @param \Request $request
	 * @param \Response $response
	 * @param \Config $config
	 * */
	public function __construct($request, $response, $config= null, $help_endpoint= '--help'){
		$this->endpoint= $help_endpoint;
		$response->phase('[0] Help processing');
		$help_index= $request->targetContains($help_endpoint);
		if($help_index===false) return $this;
		$folder= implode('/', array_slice($request->target, 0, $help_index));
		$path= realpath($folder).'/';
		$this->target= $config->api_url.'/'.$request->version.'/'.$folder;
		$this->depth= $folder ? count(explode('/', $folder)) : 0;
		if(!$folder) $folder= '/';
		if($path===false||!is_dir($path)) $response->error(new \Exception("Endpoint '$folder' doesn’t exist.", 404));
		if($request->method!=='get') $response->error(new \Exception("For getting help, only GET method is supported.", 405));
		$path.= 'help.php';
		if(!file_exists($path)) $response->error(new \Exception("Endpoint '$folder' doesn’t support help.", 404));

		try{
			$result= require_once $path;
			if(strpos($request->headerItem('Accept'), 'text/html')!==0)
				return $response->success($result);
			$this->toHTML($result);
		}
		catch(\Exception $error){ $response->error($error); }
	}
	public function toHTML($result){
		header('Content-Type: text/html; charset=utf-8');
		$body= $this->arrayToHtml($result['texts']);
		$up_link= !$this->depth ? '' : '<a href="../--help">↖ Up</a> | ';
		echo <<<HTML
<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<style>
		/* https://github.com/jensimmons/cssremedy/blob/master/css/remedy.css */
		*, ::before, ::after { box-sizing: border-box; }
		html { line-sizing: normal; }
		body { margin: 0; }
		[hidden] { display: none; }
		h1 { font-size: 2rem; } h2 { font-size: 1.5rem; } h3 { font-size: 1.17rem; } h4 { font-size: 1.00rem; } h5 { font-size: 0.83rem; } h6 { font-size: 0.67rem; }
		h1 { margin: 0.67em 0; }
		h1:target, h2:target, h3:target, h4:target, h5:target, h6:target { background: lightgray; }
		pre { white-space: pre-wrap; }
		hr { border-style: solid; border-width: 1px 0 0; color: inherit; height: 0; overflow: visible; }
		img, svg, video, canvas, audio, iframe, embed, object { display: block; vertical-align: middle; max-width: 100%; }
		audio:not([controls]) { display:none; }
		picture { display: contents; }
		source { display: none; }
		img, svg, video, canvas { height: auto; }
		audio { width: 100%; }
		img { border-style: none; }
		svg { overflow: hidden; }
		article, aside, details, figcaption, figure, footer, header, hgroup, main, nav, section { display: block; }
		[type='checkbox'], [type='radio'] { box-sizing: border-box; padding: 0; }
		/* custom */
		body{ width: calc(100% - 4ch); max-width: 65ch; text-align: block; margin: 0 auto; }
		pre{ outline: 3px dotted #700080; padding: 1ch; overflow: scroll; }
		pre::before{ content: attr(syntax); display: block; padding-bottom: 2ch; color: #700080; font-weight: bold; text-transform: uppercase; }
	</style>
	<title>REST API Doc → {$result['title']}</title>
</head>
<body>
	<p>$up_link<code>$this->target</code></p>
	<h1>{$result['title']}</h1>
	{$body}
</body>
</html>
HTML;
		exit;
	}
	public function arrayToHtml($arr, $level= 2){
		$out= '';
		foreach($arr as $key=> $texts){
			if(strpos($key, 'code_')===0){
				$code= substr($key, 5);
				$code= preg_replace('/\d+$/', '', $code);
				$json= $this->codeToHTML($texts);
				$out.= "	<pre syntax='$code'>$json</pre>";
				continue;
			}
			else if(!is_numeric($key)) $out.= "	<h{$level} id=\"".urlencode($key)."\">$key</h{$level}>";
			if(is_array($texts)) $out.= self::arrayToHtml($texts, $level+1);
			else {
				$texts= preg_replace_callback('/\{@link ([^\}]+)\}/', array($this, 'linkToHtml'), $texts);
				$out.= "	<p>$texts</p>";
			}
		}
		return $out;
	}
	private function linkToHtml($found){
		list( $link, $target_candidate )= $found;
		$query= isset($_SERVER['REDIRECT_URL']) 
			? explode('/', str_replace($this->endpoint, '', $this->target))
			: explode('/', str_replace($this->endpoint, '', '?'.$_SERVER['QUERY_STRING']));
		$target_arr= array();
		foreach(explode('/', $target_candidate) as $target_nth){
			if('.'===$target_nth) continue;
			if('..'===$target_nth){ array_pop($query); continue; }
			$target_arr[]= $target_nth;
		}
		$target= implode('/', $target_arr);
		$query= implode('/', $query);
		if(substr($query, -1)!=='/') $query.= '/';
		return "<a href='$query$target'>$link</a>";
	}
	static function codeToHTML($arr){
		if(is_array($arr)&&array_keys($arr)===range(0, count($arr)-1))
			return implode("\n", $arr);
		$json= json_encode($arr);
		$json= preg_replace_callback('/\\\\u\S{4}/', 'Help::toUnicode', str_replace('\/', '/', $json));
		
		$result = '';
		$level = 0;
		$in_quotes = false;
		$in_escape = false;
		$ends_line_level = NULL;
		$json_length = strlen( $json );

		for( $i = 0; $i < $json_length; $i++ ) {
			$char = $json[$i];
			$new_line_level = NULL;
			$post = "";
			if( $ends_line_level !== NULL ) {
				$new_line_level = $ends_line_level;
				$ends_line_level = NULL;
			}
			if ( $in_escape ) {
				$in_escape = false;
			} else if( $char === '"' ) {
				$in_quotes = !$in_quotes;
			} else if( ! $in_quotes ) {
				switch( $char ) {
					case '}': case ']':
						$level--;
						$ends_line_level = NULL;
						$new_line_level = $level;
						break;

					case '{': case '[':
						$level++;
					case ',':
						$ends_line_level = $level;
						break;

					case ':':
						$post = " ";
						break;

					case " ": case "\t": case "\n": case "\r":
						$char = "";
						$ends_line_level = $new_line_level;
						$new_line_level = NULL;
						break;
				}
			} else if ( $char === '\\' ) {
				$in_escape = true;
			}
			if( $new_line_level !== NULL ) {
				$result .= "\n".str_repeat('  ', $new_line_level );
			}
			$result .= $char.$post;
		}

		return $result;
	}
	static function toUnicode($found){
		return json_decode('"'.$found[0].'"');
	}
}
