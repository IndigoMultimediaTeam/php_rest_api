<?php

/**
 * Helper function to quickly get global variables.
 * ```php
 * list( $__DB, $table )= globals('__DB', '__DB_table');
 * list( $__DB, $table )= globals(array('__DB', '__DB_table'));
 * $g= globals('__DB', '__DB_table');
 * $g[0];//= $__DB
 * $g[1];//= $__DB_table
 * ```
 * @param string[] ...$vars Variable names
 * @return any[]
 * */
function globals($vars){
	if(!is_array($vars)) $vars= func_get_args();
	$out= array();
	foreach($vars as $var) $out[]= $GLOBALS[$var];
	return $out;
}
