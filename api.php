<?php

require_once (dirname(__FILE__) . '/rdf.php');
require_once (dirname(__FILE__) . '/lsid.php');


// API to resolve LSID

//--------------------------------------------------------------------------------------------------
function api_output($obj, $callback)
{
	$status = 404;
	
	// $obj may be array (e.g., for citeproc)
	if (is_array($obj))
	{
		if (isset($obj['status']))
		{
			$status = $obj['status'];
		}
	}
	
	// $obj may be object
	if (is_object($obj))
	{
		if (isset($obj->status))
		{
			$status = $obj->status;
		}
	}

	/*
	switch ($status)
	{
		case 303:
			header('HTTP/1.1 404 See Other');
			break;

		case 404:
			header('HTTP/1.1 404 Not Found');
			break;
			
		case 410:
			header('HTTP/1.1 410 Gone');
			break;
			
		case 500:
			header('HTTP/1.1 500 Internal Server Error');
			break;
			 			
		default:
			break;
	}
	*/
	
	header("Content-type: text/plain");
	header("Cache-control: max-age=3600");
	
	if ($callback != '')
	{
		echo $callback . '(';
	}
	//echo json_encode($obj, JSON_PRETTY_PRINT);	
	echo json_encode($obj);
	if ($callback != '')
	{
		echo ')';
	}
}

$header = $_SERVER['HTTP_ACCEPT'];

// simple matching

//echo "$header\n";


//----------------------------------------------------------------------------------------
function default_display()
{
	echo 'hi';
}

//----------------------------------------------------------------------------------------
function display_lsid($lsid, $format = 'xml', $callback = '')
{
	$response = ResolveLSID($lsid);
	
	if ($response->status == 200)
	{
		rdftojsonld($response);
	}
	
	switch ($format)
	{
		case 'xml':
			header("Content-type: text/plain; charset=utf-8");
			//header("Content-type: application/rdf+xml");
			echo $response->rdf;
			break;

		case 'nt':
			header("Content-type: text/plain; charset=utf-8");
			//header("Content-type: 'application/ntriples; charset=utf-8");
			echo $response->ntriples;
			break;

		case 'json':
			header("Content-type: text/plain; charset=utf-8");
			//header("Content-type: application/ld+json; charset=utf-8");
			echo json_format(json_encode($response->jsonld));
			break;
			
		default:
			api_output($response, $callback);
			break;
	}
}

//----------------------------------------------------------------------------------------
function main()
{
	$callback = '';
	
	/*
	// If no query parameters 
	if (count($_GET) == 0)
	{
		default_display();
		exit(0);
	}
	*/
	
	if (isset($_GET['callback']))
	{	
		$callback = $_GET['callback'];
	}
	
	$format = '';
	if (isset($_GET['format']))
	{	
		$format = $_GET['format'];
	}
	
	if (isset($_GET['lsid']))
	{
		$lsid = $_GET['lsid'];
		display_lsid($lsid, $format, $callback);
		exit();
	}


}

main();

?>