<?php

// $Id: $

/**
 * @file class_lsid.php
 *
 * @brief Basic LSID client
 *
 */
 
require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/vendor/Net/DNS.php');


//----------------------------------------------------------------------------------------
/**
 *@brief Encapsulate a LSID
 *
 */

class LSID {

	var $lsid;
	var $authority;
	var $namespace;
	var $object;
	var $revision;
	
	
	function LSID ($urn)
	{
		$this->lsid = $urn;
		if ($this->isValid())
		{
			$this->components();
		}
		
		
	}
	
	function asString ()
	{
		return $this->lsid;
	}

	/**
	 * @brief Test whether LSID is syntactically correct.
	 *
	 * Uses a regular expression taken from IBM's Perl stack.
	 *
	 * @return True if LSID is valid. 
	 *
	 */
	function isValid()
	{
		return preg_match ("/^[uU][rR][nN]:[lL][sS][iI][dD]:[A-Za-z0-9][\w\(\)\+\,\-\.\=\@\;\$\"\!\*\']*:[A-Za-z0-9][\w\(\)\+\,\-\.\=\@\;\$\"\!\*\']*:[A-Za-z0-9][\w\(\)\+\,\-\.\=\@\;\$\"\!\*\']*(:[A-Za-z0-9][\w\(\)\+\,\-\.\=\@\;\$\"\!\*\']*)?$/", $this->lsid);
	}
	
	/**
	 * @brief Extract component parts of LSID.
	 *
	 */
	function components()
	{
		$components = split(':', $this->lsid);
		$this->authority = $components[2];
		$this->namespace = $components[3];
		$this->object = $components[4];
		if (isset($components[5]))
		{
			$this->revision = $components[5];
		}
		else
		{
			$this->revision = '';
		}
	}
	
	function getAuthority()
	{
		return $this->authority;
	}

}

//----------------------------------------------------------------------------------------
/**
 *@brief Encapsulate a LSID authority
 *
 */
class Authority {

	var $server;
	var $port;
	var $wsdl;
	var $service_wsdl;
	var $httpBinding;
	var $httpMetadataBinding;
	var $http_code;
	var $lsid_code;
	var $curl_code;
	var $http_proxy;
	var $report;
	var $debug;
	var $lsid;
	var $lsid_error_code;
	var $header_counter;
	var $stored_wsdl;

	function Authority ($proxy = '')
	{
		$this->http_proxy = $proxy;
		
		$this->server 	= '';
		$this->port 	= '';
		
		$this->debug = false;
		
		$this->lsid_error_code = 0;	
	}
	
	/**
	 * @brief Resolve a LSID using the DNS
	 *
	 * 
	 */
	function Resolve ($lsid_to_resolve)
	{
		$result = true;
		
		$this->lsid = new LSID($lsid_to_resolve);
		
		$ndr = new Net_DNS_Resolver();
		$answer = $ndr->search("_lsid._tcp." . $this->lsid->getAuthority(), "SRV");
		
		if ($answer == false)
		{
			$result = false;
			
		}
		else
		{
			if ($this->debug)
			{
				echo "<pre>";
				print_r ($answer->answer);
				echo "</pre>";
			}
		}
				
		$this->server = $answer->answer[0]->target;
		
		
		if (preg_match('/urn:lsid:ipni.org/', $lsid_to_resolve))
		{
			$this->server = 'http://www.ipni.org';
		}
		
		$this->port = $answer->answer[0]->port;
		
		if ($this->report)
		{
			echo "<p>";
			echo "Authority for <strong>$lsid_to_resolve</strong> ";
			if ($result)
			{
				echo "is located at: ", $this->server, ":", $this->port;
			}
			else
			{
				echo "not found";
			}
			echo "</p>";
		}
		
		return $result;
	}
	
	function GetLocation ()
	{
		return $this->server . ":" . $this->port;
	}
	
	//--------------------------------------------------------------------------
	/**
	 * @brief Test whether HTTP code is valid
	 *
	 * HTTP codes 200 and 302 are OK.
	 *
	 * @param HTTP code
	 *
	 * @result True if HTTP code is valid
	 */

	function HttpCodeValid($http_code)
	{
		if ( ($http_code == '200') || ($http_code == '302') ){
			return true;
		}
		else{
			return false;
		}
	}
	
	//--------------------------------------------------------------------------
	/**
	 * @brief Get a WSDL using HTTP GET
	 *
	 * @param url the URL for the WSDL
	 *
	 * @result If successful returns the WSDSL, otherwise empty string
	 */
	function GetWSDL ($url)
	{
		$result = '';
		$this->lsid_error_code = 0;
		
		$wsdl = '';
		$ch = curl_init(); 
		curl_setopt ($ch, CURLOPT_URL, $url); 
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 	1); 
		curl_setopt ($ch, CURLOPT_HEADER,			1); 
		curl_setopt ($ch, CURLOPT_FOLLOWLOCATION,	1); 
		
		
		if ($this->http_proxy != '')
		{
			curl_setopt ($ch, CURLOPT_PROXY, $this->http_proxy);
		}
		
		//echo "<b>Proxy: ", $this->http_proxy, "</b>";
		
		
		$wsdl=curl_exec ($ch); 
		
		if( curl_errno ($ch) != 0 )
		{
			$curl_code = curl_errno ($ch);
		}
		else
		{
			 $info = curl_getinfo($ch);
			 
			 $header = substr($wsdl, 0, $info['header_size']);
			 
			 $this->http_code = $info['http_code'];

			 if ($this->HttpCodeValid ($this->http_code))
			 {
			 	// Everything seems OK
			 
			 }
			 else
			 {
			 	
			 	// Extract LSID error code, if any
			 	
				$rows = split ("\n", $header);
				foreach ($rows as $row)
				{
					$parts = split (":", $row, 2);
					if (count($parts) == 2)
					{
						if (preg_match("/LSID-Error-Code/", $parts[0]))
						{
							$this->lsid_error_code = $parts[1];
						}
					}
				}

			 }
			 if (($this->HttpCodeValid ($this->http_code)) && ($this->lsid_error_code == 0))
			 {
				$wsdl = substr ($wsdl,$info['header_size']);
				$result = $wsdl;
			}
		}
		curl_close ($ch); 
		
		return $result;
	}
	
	//--------------------------------------------------------------------------
	/**
	 * @brief Store a WSDL in the disk cache
	 *
	 * @param name Filename for the WSDL
	 *
	 */
	function StoreWSDLInCache ($name)
	{
		global $config;
		$cache_authority = $config['cache_dir']. "/" . $this->lsid->authority;
		$cache_filename = $cache_authority . "/" . $name;
				
		// Ensure cache subfolder exists for this authority
		if (!file_exists($cache_authority))
		{
			$oldumask = umask(0); 
			mkdir($cache_authority, 0777);
			umask($oldumask);
		}
		
		// Store data in cache
		$cache_file = @fopen($cache_filename, "w+") or die("could't open file --\"$cache_filename\"");
		
		if ($name == 'authority.wsdl')
		{
			@fwrite($cache_file, $this->wsdl);
		}
		else
		{
			@fwrite($cache_file, $this->service_wsdl);
		}
		fclose($cache_file);
	}
	
	
	//--------------------------------------------------------------------------
	/**
	 * @brief Get WSDL from the disk cache
	 *
	 * @param name Filename for the WSDL
	 *
	 * @result If WSDL name is in cache return WSDL, otherwise empty string
	 *
	 */
	function GetWSDLFromCache ($name)
	{
		global $config;
		
		$wsdl = '';
		
		$cache_authority = $config['cache_dir']. "/" . $this->lsid->authority;
		$cache_filename = $cache_authority . "/" .$name;
				
		// Does cache subfolder exist for this authority?
		if (file_exists($cache_authority))
		{
			if (file_exists($cache_filename))
			{
				// How old is it?
				$Diff = time() - filemtime ($cache_filename);
				//echo $Diff, "<br/>";
				
				if ($Diff > $config['cache_time'])
				{
					// Cached file is now too old so delete it
					unlink($cache_filename);
				}
				else
				{
					// Load data from cache
					$cache_file = @fopen($cache_filename, "r") or die("could't open file \"$cache_filename\"");
					$wsdl = @fread($cache_file, filesize ($cache_filename));
					fclose($cache_file);
				}
	
			}
		}
		return $wsdl;		
	}


	//--------------------------------------------------------------------------
	/**
	 * @brief Get authority WSDL
	 *
	 * We first look up WSDL in the cache, if it's not there we go to the
	 * authority itself.
	 *
	 * @return True of successful
	 */
	function GetAuthorityWSDL ()
	{
		global $config;
		$result = true;
				
		if ($config['cache_time'] > 0)
		{
			if ($this->debug)
			{
				echo "Trying cache...";
			}
		
		
			// Try the cache first
			$this->wsdl = $this->GetWSDLFromCache('authority.wsdl');
			
			if ($this->debug)
			{
				if ($this->wsdl == '')
				{
					echo "not found";
				}
				else
				{
					echo "found";
				}
				echo "<br/>";
			
			} 
		
		}
		
		if ($this->wsdl == '')
		{
			// Get live copy
			$url = $this->server . ":" .  $this->port . "/authority/";
			
			$this->wsdl = $this->GetWSDL ($url);
			
			//echo $this->wsdl;
			
			$result = ($this->wsdl != '');
			
			$this->StoreWSDLInCache('authority.wsdl');
			
			if ($this->debug)
			{
				echo "Authority WSDL retrieved from ", $url, "<br/>";
			}
		}		

		if ($this->debug)
		{
			echo "Authority WSDL:<br/>";
			echo "<pre>", htmlspecialchars($this->wsdl), "</pre>";
		}

		return $result;
	
	}
	
	//--------------------------------------------------------------------------
	/**
	 * @brief Get WSDL describing services
	 *
	 * @param l LSID
	 *
	 * @result True if successful
	 *
	 */
	function GetServiceWSDL ($l)
	{
		global $config;
		$result = true;
		
		if ($config['cache_time'] > 0)
		{
			if ($this->debug)
			{
				echo "Trying cache...";
			}
		
		
			// Try the cache first
			$this->service_wsdl = $this->GetWSDLFromCache('service.wsdl');
			
			$result = ($this->service_wsdl != '');

			
			if ($this->debug)
			{
				if ($this->service_wsdl == '')
				{
					echo "not found";
				}
				else
				{
					echo "found";
				}
				echo "<br/>";
			
			} 
			
		}
		
		if ($this->service_wsdl == '')
		{
			// Get live copy
			$url = $this->httpBinding . "/authority/?lsid=" . $l;
						
			$this->service_wsdl = $this->GetWSDL ($url);
			
			$result = ($this->service_wsdl != '');
			
			$this->StoreWSDLInCache('service.wsdl');
			
			if ($this->debug)
			{
				echo "Service WSDL retrieved from ", $url, "<br/>";
			}
		}		

		if ($this->debug)
		{
			echo "Service WSDL:<br/>";
			echo "<pre>", htmlspecialchars($this->service_wsdl), "</pre>";
		}
		
		return $result;

	}


	//--------------------------------------------------------------------------
	/**
	 * @brief Get HTTP binding for LSID authority
	 *
	 * Why oh why do we use regular expressions to parse XML? Because the WSDL files may or may not
	 * have namespaces, and the obvious XPath solution (using local-name()) doesn't work
	 * in XPath.class, which means PHP 4 can't use XPath. Hence, we do this.
	 *
	 * Idea comes from Jack Herrington's article "Reading and writing the XML DOM with PHP"
	 * http://www-128.ibm.com/developerworks/library/os-xmldomphp/
	 *
	 */
	function GetHTTPBinding ()
	{
		$this->httpBinding = '';
		preg_match_all( "/\<[A-Za-z]*[:]?service(.*?)\<\/[A-Za-z]*[:]?service\>/s", $this->wsdl, $services);
		
		//echo __LINE__ . "\n";
		//print_r($services);
				
		foreach( $services[1] as $service )
		{
			preg_match_all( "/\<[A-Za-z]*[:]?port (.*?)\<\/[A-Za-z]*[:]?port\>/s", $service, $ports );

			foreach ($ports[1] as $port)
			{
				preg_match_all ("/LSIDAuthorityHTTPBinding/", $port, $binding);

				if (isset($binding[0][0]))
				{
					preg_match_all( "/\<[A-Za-z]*[:]?address\s+location=\"(.*?)\"\s*\/\>/", $port, $location );
					
					$binding =  $location[1][0];


					// Deal with any extra "authority" address
					//$binding = str_replace ("/authority", '',  $binding);

					// Strip off any trailing '/'
					rtrim($binding, "/");
					
					// Handle Algaebase weirdness
					$binding = preg_replace('/\/authority\/index.lasso/', '', $binding);
					
					$this->httpBinding = $binding; 
					
					if ($this->debug)
					{
						echo $binding;
					}
					
					
				}
			}
		}
		
		//echo __LINE__ . ' ' . $this->httpBinding . "\n";
		//exit();
		
		$result = ($this->httpBinding != '');
		return $result;
/*	
		echo __LINE__ . "\n";
		//echo $this->wsdl;
		$this->httpBinding = '';
	
		$dom= new DOMDocument;
		$dom->loadXML($this->wsdl);
		
		$wsdl_namespace 	 = $dom->lookupPrefix('http://schemas.xmlsoap.org/wsdl/');
		$authority_namespace = $dom->lookupPrefix('http://www.omg.org/LSID/2003/AuthorityServiceHTTPBindings');
		
		echo "wsdl=$wsdl_namespace\n";
		echo "authority_namespace=$authority_namespace\n";
		
		$xpath = new DOMXPath($dom);
		
		$xpath_query = "//$wsdl_namespace:service/$wsdl_namespace:port/$authority_namespace:address/@location";
		
		echo $xpath_query . "\n";
		
		$nodeCollection = $xpath->query ($xpath_query);
		foreach($nodeCollection as $node)
		{
			$this->httpBinding = $node->firstChild->nodeValue;
			$this->httpBinding = rtrim($this->httpBinding, "/");
		}	

		echo "HTTP binding = " . $this->httpBinding . "\n";

		$result = ($this->httpBinding != '');
		return $result;
		*/
	}
	
	//--------------------------------------------------------------------------
	/*
	 * @brief Check that HTTP binding for authority is a server address (i.e., nothing after http://my.web.com[:80]/)
	 *
	 * @return True if HTTP binding is server address.
	 *
	 */
	function bindingIsServerAddress ()
	{
		return 	preg_match ("/^https?:\/\/([a-z0-9\-]*[a-z0-9]?\.)+(?:com|edu|biz|org|gov|int|info|mil|net|name|museum|coop|aero|[a-z][a-z])(:[0-9]{2,4})?(\/?)$/",  $this->httpBinding);
	}

	//--------------------------------------------------------------------------
	/*
	 * @brief Get HTTP access point 
	 *
	 * @return True successful.
	 *
	 */
	function GetMetadataHTTPLocation()
	{
		preg_match_all( "/\<[A-Za-z]*[:]?service(.*?)\<\/[A-Za-z]*[:]?service\>/s", $this->service_wsdl, $services);
		
		//echo $this->service_wsdl;
		//print_r($services);
		
		foreach( $services[1] as $service )
		{
			preg_match_all( "/\<[A-Za-z]*[:]?port (.*?)\<\/[A-Za-z]*[:]?port\>/s", $service, $ports );
			foreach ($ports[1] as $port)
			{
				preg_match_all ("/LSIDMetadataHTTPBinding\"/", $port, $binding);
				if (isset($binding[0][0]))
				{
					preg_match_all( "/\<[A-Za-z]*[:]?address\s+location=\"(.*?)\"\s*\/\>/", $port, $location );
					
					$this->httpMetadataBinding = $location[1][0];
					$this->httpMetadataBinding = rtrim($this->httpMetadataBinding, "/");
				}
			}
		}
		
		//echo __LINE__ . ' ' . $this->httpMetadataBinding;
		//exit();
		
		$result = ($this->httpMetadataBinding != '');
		return $result;
/*	
		echo __LINE__ . "\n";
	
		echo $this->service_wsdl;
		
		$this->httpMetadataBinding = '';
		
		$wsdl = $this->service_wsdl;
		
		$wsdl = str_replace('xmlns:tns="http://www.example.org/SampleDataServices"', '', $wsdl);
		$wsdl = str_replace('targetNamespace="http://www.example.org/SampleDataServices"', '', $wsdl);
		$wsdl = str_replace('xmlns:xsd="http://www.w3.org/2001/XMLSchema"', '', $wsdl);
		echo $wsdl;
	
		$dom= new DOMDocument;
		$dom->loadXML($wsdl);
		
		$wsdl_namespace	   = $dom->lookupPrefix('http://schemas.xmlsoap.org/wsdl/');
		$http_namespace    = $dom->lookupPrefix('http://schemas.xmlsoap.org/wsdl/http/');
		$binding_namespace = $dom->lookupPrefix('http://www.omg.org/LSID/2003/DataServiceHTTPBindings');
		
		echo "http=$http_namespace\n";
		echo "wsdl=$wsdl_namespace\n";
		echo "binding_namespace=$binding_namespace\n";
		
		$xpath = new DOMXPath($dom);
		
		//$xpath_query = "//$wsdl_namespace:service/$wsdl_namespace:port/$authority_namespace:address/@location";

		$xpath_query = "//$wsdl_namespace:service/$wsdl_namespace:port[@binding=\"$binding_namespace:LSIDMetadataHTTPBinding\"]/$http_namespace:address/@location";
		//$xpath_query = "//$wsdl_namespace:service/$wsdl_namespace:port/$http_namespace:address@location";
		
		//$xpath_query = '//service/port/http:address/@location';
		echo $xpath_query . "\n";

		$nodeCollection = $xpath->query ($xpath_query);
		foreach($nodeCollection as $node)
		{
			$this->httpMetadataBinding = $node->firstChild->nodeValue;
			$this->httpMetadataBinding = rtrim($this->httpMetadataBinding, "/");

			echo $node->firstChild->nodeValue . "\n";
		}	

		echo "HTTP metadata binding = " . $this->httpMetadataBinding . "\n";

		$result = ($this->httpMetadataBinding != '');
		return $result;
*/	
	}


	//--------------------------------------------------------------------------
	/*
	 * @brief Get HTTP metadata access point
	 *
	 * @return Metadata if successful, otherwise return empty string
	 *
	 */
	function GetHTTPMetadata ($l)
	{
		$metadata = '';
		$this->lsid_error_code = 0;

		$url = $this->httpMetadataBinding . "?lsid=" . $l;
				
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $url); 
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 	1); 
		curl_setopt ($ch, CURLOPT_HEADER,		  	1); // debugging, show header
		curl_setopt ($ch, CURLOPT_FOLLOWLOCATION,	1); 
		if ($this->http_proxy != '')
		{
			curl_setopt ($ch, CURLOPT_PROXY, $this->http_proxy);
		}
		
		//echo '<b>' . $url . '</b><br/>';
		
				
		$curl_result = curl_exec ($ch); 
		
		if((curl_errno ($ch) != 0 ) && (curl_errno ($ch) != 18))
		{
			$this->curl_code = curl_errno ($ch);
			
			echo $url;
		}
		else
		{
		
			 $info = curl_getinfo($ch);
			 $header = substr($curl_result, 0, $info['header_size']);
			 
			 $this->http_code = $info['http_code'];

			if ($this->HttpCodeValid ($this->http_code)	)		 
			{
			 	// Everything seems OK
			 
			 }
			 else
			 {
			 	
			 	// Extract LSID error code, if any
			 	$this->lsid_error_code = 0;
				$rows = split ("\n", $header);
				foreach ($rows as $row)
				{
					$parts = split (":", $row, 2);
					if (count($parts) == 2)
					{
						if (preg_match("/LSID-Error-Code/", $parts[0]))
						{
							$this->lsid_error_code = $parts[1];
						}
					}
				}

			 }
	
			$metadata = substr ($curl_result, $info['header_size']);
			$filename = $this->CacheMetadata($metadata);
		}
		curl_close ($ch); 
		
		return $metadata;
	}
	
	//--------------------------------------------------------------------------
	/*
	 * @brief Cache the metadata
	 *
	 * Fiilename is generated using MD5 hash and appending ".rdf"
	 *
	 * @return Filename 
	 *
	 */
	function CacheMetadata ($metadata)
	{	
		global $config;
		
		$extension = 'xml';
		if (preg_match("/rdf:RDF/", $metadata))
		{
			$extension = 'rdf';
		}
		
		$filename = $this->lsid->asString();
		$filename = str_replace(':', '-', $filename);
		
		$filename .= "." . $extension;
		$cache_authority = $config['cache_dir'] . "/" . $this->lsid->authority;
		$cache_filename = $cache_authority . "/" . $filename;
		
		
		// Ensure cache subfolder exists for this authority
		if (!file_exists($cache_authority))
		{
			$oldumask = umask(0); 
			mkdir($cache_authority, 0777);
			umask($oldumask);
		}
			
		// Store data in cache
		$cache_file = @fopen($cache_filename, "w+") or die("could't open file \"--$cache_filename\"");
		@fwrite($cache_file, $metadata);
		fclose($cache_file);
		
		return $filename;
	}
	
	
}

//--------------------------------------------------------------------------
/*
 * @brief Resolve an LSID
 *
 * Resolve LSID and return metadata
 *
 * @result If successful return metadata (RDF), otheerwise return XML formatted
 * information on why resolution failed.
 *
 */
function resolveLSID ($l)
{
	global $config;
	
	$response = new stdclass;
	
	$response->lsid = $l;
	$response->status = 404;
			
	$lsid = new LSID($l);
	$proxy = '';
	if ($config['proxy_name']  != '')
	{
		$proxy = $config['proxy_name'] . ":" . $config['proxy_port'];
	}
	$authority = new Authority($proxy);
	
	$ok = false;
	
	if (!$lsid->isValid())
	{
		$response->status = 400;
		$response->msg = "LSID is not validly formed";
	}
	else
	{
		$ok = $authority->Resolve($l);
		
		if (!$ok)
		{
			$response->status = 404;
			$response->msg = "DNS lookup for SRV record for " . $lsid->getAuthority() . " failed";		
		}
		else
		{
			$authority->GetAuthorityWSDL();
			
			$ok = $authority->GetHTTPBinding();
			
			if (!$ok)
			{
				$response->status = 501;
				$response->msg = "No HTTP binding found";	
			}
			{
				$ok = $authority->GetServiceWSDL($l);
				
				if (!$ok)
				{
					$response->status = 501;
					$response->msg = "Error retrieving service WSDL";	
				}
				else
				{				
					$authority->GetMetadataHTTPLocation();
					$rdf = $authority->GetHTTPMetadata($l);
					
					if ($rdf != '')
					{
						$response->status = 200;
						$response->rdf = $rdf;
					}
				}
			}
	
		
		}
	}
	
	$response->statusCodes = array();
	if (isset($authority->http_code)) { $response->statusCodes['HTTP'] = $authority->http_code; }
	if (isset($authority->lsid_code)) { $response->statusCodes['LSID'] = $authority->lsid_code; }
	if (isset($authority->curl_code)) { $response->statusCodes['CURL'] = $authority->curl_code; }
	
	if (count($response->statusCodes) == 0)
	{
		unset($response->statusCodes);
	}
	
	return $response;
}

if (0)
{
	$lsid = 'urn:lsid:taxonomy.org.au:TherevidaeMandala:MEI024058';
	$response = resolveLSID($lsid);
	print_r($response);
}
	

?>