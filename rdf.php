<?php

require_once (dirname(__FILE__) . '/vendor/arc2/ARC2.php');
require_once (dirname(__FILE__) . '/vendor/php-json-ld/jsonld.php');


// RDF tools


//--------------------------------------------------------------------------------------------------
/**
 * @brief Format JSON nicely
 *
 * From umbrae at gmail dot com posted 10-Jan-2008 06:21 to http://uk3.php.net/json_encode
 *
 * @param json Original JSON
 *
 * @result Formatted JSON
 */
function json_format($json)
{
    $tab = "  ";
    $new_json = "";
    $indent_level = 0;
    $in_string = false;

/*    $json_obj = json_decode($json);

    if($json_obj === false)
        return false;

    $json = json_encode($json_obj); */
    $len = strlen($json);

    for($c = 0; $c < $len; $c++)
    {
        $char = $json[$c];
        switch($char)
        {
            case '{':
            case '[':
                if(!$in_string)
                {
                    $new_json .= $char . "\n" . str_repeat($tab, $indent_level+1);
                    $indent_level++;
                }
                else
                {
                    $new_json .= $char;
                }
                break;
            case '}':
            case ']':
                if(!$in_string)
                {
                    $indent_level--;
                    $new_json .= "\n" . str_repeat($tab, $indent_level) . $char;
                }
                else
                {
                    $new_json .= $char;
                }
                break;
            case ',':
                if(!$in_string)
                {
                    $new_json .= ",\n" . str_repeat($tab, $indent_level);
                }
                else
                {
                    $new_json .= $char;
                }
                break;
            case ':':
                if(!$in_string)
                {
                    $new_json .= ": ";
                }
                else
                {
                    $new_json .= $char;
                }
                break;
            case '"':
                if($c > 0 && $json[$c-1] != '\\')
                {
                    $in_string = !$in_string;
                }
            default:
                $new_json .= $char;
                break;                    
        }
    }

    return $new_json;
}



//--------------------------------------------------------------------------------------------------
function rdftojsonld(&$response)
{
	// Namespaces
	$sxe = new SimpleXMLElement($response->rdf);
	
	
	$namespaces = $sxe->getNamespaces(true);
	
	//print_r($namespaces);exit();
	
	// Parse RDF into triples
	$parser = ARC2::getRDFParser();		
	$base = 'http://example.com/';
	$parser->parse($base, $response->rdf);	
	
	$triples = $parser->getTriples();
	
	//print_r($triples);exit();
	
	$context = new stdclass;
	
	// nquads
	$nquads = '';
	foreach ($triples as $triple)
	{
		// skip empty values (e.g., in ION RDF)
		if ($triple['o'] != "")
		{
			$predicate = $triple['p'];
			// (Sigh) Fix known fuck ups
			// ION
			$predicate = str_replace('http://rs.tdwg.org/ontology/voc/Common#PublishedIn', 'http://rs.tdwg.org/ontology/voc/Common#publishedIn', $predicate);
			$predicate = str_replace('http://purl.org/dc/elements/1.1/Title', 'http://purl.org/dc/elements/1.1/title', $predicate);

			
			$nquads .=  '<' . $triple['s'] . '> <' .  $predicate . '> ';
		
			// URNs aren't recognised as URIs, apparently
			if (($triple['o_type'] == 'uri') || preg_match('/^urn:/', $triple['o']))
			{
				// Create context for predicates
				$namespace_found = false;
				foreach($namespaces as $k => $v)
				{
					if (!$namespace_found)
					{
						$pattern = '/^' . str_replace("/", "\\/", $v) . '(?<q>.*)$/';
						//echo $pattern . "\n";
						if (preg_match($pattern, $predicate, $m))
						{
							if (!isset($context->{$m['q']}))
							{
								$context->{$m['q']} = (object)array("@id" => $predicate, "@type"=> "@id");
							}
							$namespace_found = true;
						}
					}
				}
				
				// Create context for object
				$namespace_found = false;
				foreach($namespaces as $k => $v)
				{
					if (!$namespace_found)
					{
						$pattern = '/^' . str_replace("/", "\\/", $v) . '(?<q>.*)$/';
						//echo $pattern . "\n";
						if (preg_match($pattern, $triple['o'], $m))
						{
							if (!isset($context->{$m['q']}))
							{
								$context->{$m['q']} = $triple['o'];
							}
							$namespace_found = true;
						}
					}
				}
				
				
				$nquads .= ' <' . $triple['o'] . '>';
			}
			else
			{
				// literal
		
				$object = $triple['o'];
			
				// Handle encoding issues
				$encoding = mb_detect_encoding($object);
				if ($encoding != "ASCII")
				{
					$object = mb_convert_encoding($object, 'UTF-8', $encoding);
				}
			
				// Make sure literals are escaped
				$nquads .= ' "' . addcslashes($object, '"') . '"';
			
				// language
				$lang = '';
				if (isset($triple['o_lang']))
				{
					if ($triple['o_lang'] != '')
					{
						$nquads .= '@' . $triple['o_lang'];
					}
					else
					{
						// try and detect language
						if ($triple['o_type'] == 'literal')
						{
							// See http://www.regular-expressions.info/unicode.html
							// and http://stackoverflow.com/a/4923410
							// Note that this may detect Chinese as well :O	
							if (preg_match('/\p{Hiragana}+/u', $object))
							{
								$lang = 'jp';
							}
							if (preg_match('/\p{Katakana}+/u', $object))
							{
								$lang = 'jp';
							}
							if (preg_match('/\p{Han}+/u', $object))
							{
								$lang = 'jp';
							}
							
							if (preg_match('/\p{Cyrillic}+/u', $object))
							{
								$lang = 'ru';
							}							
							
							
							if ($lang != '')
							{
								$nquads .= '@' . $lang;
							}
						}
					}
				}
				
				$namespace_found = false;
				foreach($namespaces as $k => $v)
				{
					if (!$namespace_found)
					{
						$pattern = '/^' . str_replace("/", "\\/", $v) . '(?<q>.*)$/';
						if (preg_match($pattern, $predicate, $m))
						{
							if (!isset($context->{$m['q']}))
							{
								$context->{$m['q']} = $predicate;
							}
							if ($lang != '')
							{
								$key = $m['q'] . '_' . $lang;
								
								if (!isset($context->{$key}))
								{
									$context->{$key} = new stdclass;
									$context->{$key} ->{'@id'} = $predicate;
									$context->{$key} ->{'@language'} = $lang;
								}
								
							}

							$namespace_found = true;
						}
					}
				}
				
				
				
							
			}
			// ensure we get a named graph
			//$nquads .= ' <' . $triple['s'] . '>'; 
			$nquads .= " . \n";
		}	
	}
	
	
	$response->ntriples = $nquads;
	
	//print_r($context);

	if (0)
	{
		echo "---\n";
		echo $nquads;
		echo "---\n";
	}
	
	$jsonld = jsonld_from_rdf($response->ntriples);
	$jsonld = jsonld_compact($jsonld, $context);
	
	$response->jsonld = $jsonld;
}



