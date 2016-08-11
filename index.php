<?php

//print_r($_GET);
//print_r($_SERVER);

$lsid = '';
$format = 'html';

if (isset($_GET['lsid']))
{
	$lsid = $_GET['lsid'];
}

if (isset($_SERVER['HTTP_ACCEPT']))
{
	switch ($_SERVER['HTTP_ACCEPT'])
	{
		case 'application/rdf+xml':
			$format = 'xml';
			break;
	
		case 'application/json':
		case 'application/ld+json':
			$format = 'json';
			break;

		case 'application/ntriples':
			$format = 'nt';
			break;
	
		default:
			$format = 'html';
			break;
	}
}
	
if (isset($_GET['format']))
{
	switch ($_GET['format'])
	{
		case 'xml':
		case 'json':
		case 'nt';
			$format = $_GET['format'];
			break;
		case 'jsonld':
			$format = 'json';
			break;
		default:
			$format = 'html';
			break;
	}
}


if (($lsid != '') && ($format != 'html'))
{
	// API call
	//echo 'appi call';
	$location = 'api.php?lsid=' . $lsid . '&format=' . $format;
	header("Location: " . $location . "\n");
}
else
{
?>

<!DOCTYPE html>
<html>
  <head>
  	<meta charset="utf-8" />
  	<title>LSID Resolver</title>
  	
    <style type="text/css">
      body { margin: 40px; font-family:sans-serif; }
      input[type="text"] {
    		font-size:28px;
    		width:80%;
	  }
	  button {font-size:28px;}
	  
       
	  .error { 
	   font-family: Arial;
	   color: rgb(255,255,255);
       background-color:red;
		padding:10px;
       }
       
	}
    </style>
    
    
   
    <!-- jquery -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.0/themes/base/jquery-ui.css">
  	<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
  	<script src="https://code.jquery.com/ui/1.12.0/jquery-ui.min.js"></script>
    
    <script>
        //--------------------------------------------------------------------------------
		// http://stackoverflow.com/a/11407464
		$(document).keypress(function(event){

			var keycode = (event.keyCode ? event.keyCode : event.which);
			if(keycode == '13'){
				$('#go').click();   
			}

		});    
	
		//http://stackoverflow.com/a/25359264
		$.urlParam = function(name){
			var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
			if (results==null){
			   return null;
			}
			else{
			   return results[1] || 0;
			}
		}    
	
        //--------------------------------------------------------------------------------
		// http://stackoverflow.com/a/16976927
		function ObjectLength_Modern( object ) {
			return Object.keys(object).length;
		}

		function ObjectLength_Legacy( object ) {
			var length = 0;
			for( var key in object ) {
				if( object.hasOwnProperty(key) ) {
					++length;
				}
			}
			return length;
		}

		var ObjectLength =
			Object.keys ? ObjectLength_Modern : ObjectLength_Legacy; 
			
        //--------------------------------------------------------------------------------
		function resolve(lsid) {
			var lsid = $('#lsid').val();
			
			$('#results').html('');
			$('#tabs-1').html('');
			$('#tabs-2').text('');
			$('#tabs-3').text('');
			
			$('#results').html('Resolving...');
			
			$.getJSON('api.php?lsid=' + lsid + "&callback=?",
				function(data){
					
					
					//alert(data.status);
					
					if (data.status == 200) {
						$('#results').html('');
						$('#tabs-1').html(JSON.stringify(data.jsonld, null, 2));
						$('#tabs-2').text(data.ntriples);
						$('#tabs-3').text(data.rdf);
					} else {
						var html = '';
						html += '<div class="error">';
						html += '<p>Badness happened (' + data.status + ')' + ' ' + data.msg + '</p>';
						html += '</div>';
						$('#results').html(html);
						
					}
						
					$('#results').html(html);

					
				 }
				
				);
		}			
		

	</script>    
	
  <script>
  $( function() {
    $( "#tabs" ).tabs();
  } );
  </script>	

  </head>
  <body>
    <h1>Life Sciences Identifier (LSID) Resolver</h1>    	
		<div style="width:100%;padding-bottom:20px;">
			<input type="text" id="lsid" value="" placeholder="" >
			<button id="go" onclick="resolve();">Go</button>
		</div>
		
		<p>
		Resolve a <a href="https://en.wikipedia.org/wiki/LSID">LSID</a> and return metadata 
		as <a href="https://en.wikipedia.org/wiki/Resource_Description_Framework">RDF</a> as
		<a href="https://en.wikipedia.org/wiki/JSON-LD">JSON-LD</a>, <a href="https://en.wikipedia.org/wiki/N-Triples">N-Triples</a>,
		and <a href="https://en.wikipedia.org/wiki/RDF/XML">RDF/XML</a>.</p>
		
		<p> To retrieve RDF directly, use either content negotiation or append the required file extension to the LSID 
		(e.g., <a href="./urn:lsid:nmbe.ch:spidersp:021946.jsonld">/urn:lsid:nmbe.ch:spidersp:021946.jsonld</a> returns RDF for the LSID 
		urn:lsid:nmbe.ch:spidersp:021946 in JSON-LD format).
		<table cellspacing="10">
			<tr><th>Syntax</th><th>File extension</th><th>Accept header</th></tr>
			<tr><td>JSON-LD</td><td>.jsonld</td><td>application/ld+json</td></tr>
			<tr><td>N-triples</td><td>.nt</td><td>application/ntriples</td></tr>
			<tr><td>RDF/XML</td><td>.rdf</td><td>application/rdf+xml</td></tr>
		</table>
		</p>
		
		<div>
			<h2>Examples</h2>
			<h3>Taxa and names</h3>
			<ul>
				<li><a href="?lsid=urn:lsid:nmbe.ch:spidersp:021946">urn:lsid:nmbe.ch:spidersp:021946</a></li>
				<li><a href="?lsid=urn:lsid:algaebase.org:taxname:101541">urn:lsid:algaebase.org:taxname:101541</a></li>
				<li><a href="?lsid=urn:lsid:marinespecies.org:taxname:138474">urn:lsid:marinespecies.org:taxname:138474</a></li>
				<li><a href="?lsid=urn:lsid:itis.gov:itis_tsn:180543">urn:lsid:itis.gov:itis_tsn:180543</a></li>
				<li><a href="?lsid=urn:lsid:Blattodea.speciesfile.org:TaxonName:6343">urn:lsid:Blattodea.speciesfile.org:TaxonName:6343</a?</li>
				<li><a href="?lsid=urn:lsid:Orthoptera.speciesfile.org:TaxonName:61777">urn:lsid:Orthoptera.speciesfile.org:TaxonName:61777</a></li>
				<li><a href="?lsid=urn:lsid:Coreoidea.speciesfile.org:TaxonName:459009">urn:lsid:Coreoidea.speciesfile.org:TaxonName:459009</a></li>
				<li><a href="?lsid=urn:lsid:biosci.ohio-state.edu:osuc_concepts:249011">urn:lsid:biosci.ohio-state.edu:osuc_concepts:249011</a></li>
				<li><a href="?lsid=urn:lsid:organismnames.com:name:1776318">urn:lsid:organismnames.com:name:1776318</a></li>					
			</ul>
			<h3>Publications</h3>
			<ul>
				<li><a href="?lsid=urn:lsid:biosci.ohio-state.edu:osuc_pubs:412">urn:lsid:biosci.ohio-state.edu:osuc_pubs:412</a></li>
			</ul>
			<h3>Occurrences</h3>
			<ul>
				<li><a href="?lsid=urn:lsid:biosci.ohio-state.edu:osuc_occurrences:CASENT__2043391">urn:lsid:biosci.ohio-state.edu:osuc_occurrences:CASENT__2043391</a></li>
			</ul>
			<h3>Broken (or needing a hack to work)</h3>
			<ul>
				<li><a href="?lsid=urn:lsid:ipni.org:names:20012728-1">urn:lsid:ipni.org:names:20012728-1</a></li>
				<li><a href="?lsid=urn:lsid:zoobank.org:act:7746E1DE-0AFD-443A-A8D5-FBD6DD369F43">urn:lsid:zoobank.org:act:7746E1DE-0AFD-443A-A8D5-FBD6DD369F43</a></li>
				<li><a href="?lsid=urn:lsid:indexfungorum.org:names:319089">urn:lsid:indexfungorum.org:names:319089</a></li>
				<li><a href="?lsid=urn:lsid:luomus.fi:taxonconcept:5a5abdc0-2ec0-4b83-9555-b2362d6f105f:1">urn:lsid:luomus.fi:taxonconcept:5a5abdc0-2ec0-4b83-9555-b2362d6f105f:1</a></li>
			</ul>
		</div>
		
		
		<div class="results" id="results">
		</div>
			
		<div id="tabs">
		  <ul>
			<li><a href="#tabs-1">JSON-LD</a></li>
			<li><a href="#tabs-2">N-Triples</a></li>
			<li><a href="#tabs-3">XML</a></li>
		  </ul>
		  <div id="tabs-1" style="white-space: pre;">
		  
		  </div>
		  <div id="tabs-2" style="white-space: pre;">
		  
		  </div>
		  <div id="tabs-3" style="white-space: pre;">
		  
		  </div>
		</div>
		
		
	<script>
		// do we have a URL parameter?
		//var lsid = $.urlParam('lsid');
		var lsid = '<?php echo $lsid; ?>';
		if (lsid != '') {
		   lsid = decodeURIComponent(lsid);
		   $('#lsid').val(lsid); 
		   resolve();
		}
	</script>
	

  
  </body>
</html>



<?php
}
?>