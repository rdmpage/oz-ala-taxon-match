<?php


// dump list of names

require_once (dirname(__FILE__) . '/adodb5/adodb.inc.php');



//----------------------------------------------------------------------------------------
function match_ion(&$obj, $match_author = true)
{
	$db = NewADOConnection('mysql');
	$db->Connect("localhost", 	'root' , '' , 'ion');
	
	$obj->ids = array();
		
	// 1.
	$sql = 'SELECT * FROM names WHERE nameComplete = "' . $obj->nameComplete . '"';
	
	if ($match_author && isset($obj->author))
	{
		$taxonAuthor = preg_replace('/,\s+/u', ' ', $obj->author);
		$sql .= ' AND taxonAuthor="' . $taxonAuthor . '"';
	}
	
	//echo $sql . "\n";
	
	$result = $db->Execute($sql);
	if ($result == false) die("failed [" . __FILE__ . ":" . __LINE__ . "]: " . $db->ErrorMsg());

	while (!$result->EOF) 
	{
		$hit = new stdclass;
		$hit->resolvedTaxonId = 'ION:' . $result->fields['id'];
		$hit->resolvedTaxonName = $result->fields['nameComplete'];
	
		$obj->ids[] = $hit;
		$result->MoveNext();

	}
		
	

}

//----------------------------------------------------------------------------------------
function match_gbif(&$obj, $match_author = true)
{
	$db = NewADOConnection('mysql');
	$db->Connect("localhost", 	'root' , '' , 'gbif-backbone');
	
	$obj->ids = array();
	
	$match_type = 'exact name';
	
	$string_to_match = $obj->nameComplete;
	
	// 0. GBIF doesn't recognise subgenera
	if (preg_match('/^(?<genus>[A-Z]\w+)\s+\((?<subgenus>[A-Z]\w+)\)\s+(?<rest>\w.*)/', $string_to_match, $m))
	{
		$string_to_match = $m['genus'] . ' ' . $m['rest'];
		$match_type = 'ignore subgenus';
	}
	
		
	// 1.
	$sql = 'SELECT * FROM taxon WHERE canonicalName = "' . $string_to_match . '"';
	
	if ($match_author && isset($obj->author))
	{
		$taxonAuthor = $obj->author;
		$sql .= ' AND scientificNameAuthorship="' . $taxonAuthor . '"';
	}
	
	//echo $sql . "\n";
	
	$result = $db->Execute($sql);
	if ($result == false) die("failed [" . __FILE__ . ":" . __LINE__ . "]: " . $db->ErrorMsg());

	while (!$result->EOF) 
	{
		//$obj->ids[] = $result->fields['taxonID'];
		
		$hit = new stdclass;
		
		if ($result->fields['acceptedNameUsageID'] != '')
		{
			$hit->resolvedTaxonId = 'GBIF:' . $result->fields['acceptedNameUsageID'];
		}
		else
		{
			$hit->resolvedTaxonId = 'GBIF:' . $result->fields['taxonID'];
		}
		
		
		$hit->resolvedTaxonName = $result->fields['canonicalName'];

		if ($result->fields['scientificNameAuthorship'] != '')
		{
			$hit->resolvedAuthorName = $result->fields['scientificNameAuthorship'];
		}
		
		$hit->resolvedMatchType = $match_type;
	
		$obj->ids[] = $hit;
		
		
		$result->MoveNext();

	}
		
	

}

//----------------------------------------------------------------------------------------
function match_ncbi(&$obj, $match_author = false)
{
	$db = NewADOConnection('mysql');
	$db->Connect("localhost", 	'root' , '' , 'ncbi');
	
	$obj->ids = array();
		
	// 1.
	$sql = 'SELECT * FROM names WHERE name_txt = "' . $obj->nameComplete . '"';
	
	/*
	if ($match_author && isset($obj->author))
	{
		$taxonAuthor = $obj->author;
		$sql .= ' AND scientificNameAuthorship="' . $taxonAuthor . '"';
	}
	*/
	
	echo $sql . "\n";
	
	$result = $db->Execute($sql);
	if ($result == false) die("failed [" . __FILE__ . ":" . __LINE__ . "]: " . $db->ErrorMsg());

	while (!$result->EOF) 
	{
		//$obj->ids[] = $result->fields['taxonID'];
		
		$hit = new stdclass;
		$hit->resolvedTaxonId = 'NCBI:' . $result->fields['tax_id'];
		$hit->resolvedTaxonName = $result->fields['name_txt'];
	
		$obj->ids[] = $hit;
		
		
		$result->MoveNext();

	}
		
	

}


	
$page = 1000;
$offset = 0;

$done = false;

while (!$done)
{
	$db = NewADOConnection('mysql');
	$db->Connect("localhost", 
		'root' , '' , 'afd');

	// Ensure fields are (only) indexed by column name
	$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

	$db->EXECUTE("set names 'utf8'"); 

	// valid taxa 
	$sql = 'SELECT * FROM afd WHERE PARENT_TAXON_GUID IS NOT NULL';

	// names with publications
//	$sql = 'SELECT * FROM afd WHERE NAME_GUID IS NOT NULL AND PUBLICATION_GUID IS NOT NULL';
	//$sql = 'SELECT * FROM afd WHERE NAME_GUID IS NOT NULL';
	
	//$sql = 'SELECT * FROM afd WHERE GENUS="Ideoblothrus"';
	//$sql = 'SELECT * FROM afd WHERE GENUS="Synsphyronus"';
	
	//$sql = 'SELECT * FROM afd WHERE GENUS="Pseudotyrannochthonius"';
	
	//$sql = 'SELECT * FROM afd WHERE GENUS="Thevenetimyia"';
	//$sql = 'SELECT * FROM afd WHERE GENUS="Balaana"';

	//$sql = 'SELECT * FROM afd WHERE GENUS LIKE "Ba%"';
	
	//$sql = 'SELECT * FROM afd WHERE GENUS="Synsphyronus"';
	
	
	//$sql = 'SELECT * FROM afd WHERE TAXON_GUID="d0a38aed-32a3-4c16-a057-056c701b8cc7"';

	// Bactrocera (Bactrocera) bryoniae
	$sql = 'SELECT * FROM afd WHERE TAXON_GUID="cb35d773-0443-4f19-813f-bfd662d48436"';


	//$sql = 'SELECT * FROM afd WHERE SCIENTIFIC_NAME="Synsphyronus apimelus Harvey, 1987"';
	
	$sql .= ' AND NAME_TYPE <> "Common Name"';

	$sql .= ' LIMIT ' . $page . ' OFFSET ' . $offset;
		
	$result = $db->Execute($sql);
	if ($result == false) die("failed [" . __FILE__ . ":" . __LINE__ . "]: " . $db->ErrorMsg());

	while (!$result->EOF) 
	{
		$obj = new stdclass;
	
		$obj->taxon_guid =  $result->fields['TAXON_GUID'];
		$obj->name_guid  =  $result->fields['NAME_GUID'];

			
		// Name itself
		
		$obj->rank = $result->fields['RANK'];

		$name_parts = array();		
		if ($result->fields['FAMILY'] != '')
		{
			if ($result->fields['RANK'] == 'Family')
			{
				$name_parts[] = $result->fields['FAMILY'];
			}				
		}			
			
		if ($result->fields['GENUS'] != '')
		{
			$name_parts[] = $result->fields['GENUS'];
		}						

		if ($result->fields['SUBGENUS'] != '')
		{
			$name_parts[] = '(' . $result->fields['SUBGENUS'] . ')';
		}			

		if ($result->fields['SPECIES'] != '')
		{
			$name_parts[] = $result->fields['SPECIES'];
		}

		if ($result->fields['SUBSPECIES'] != '')
		{
			$name_parts[] = $result->fields['SUBSPECIES'];
		}		
			
		$obj->nameComplete = join(' ', $name_parts);
		
		//authorship
		
		
		$nameComplete = $result->fields['SCIENTIFIC_NAME'];
	
		$authorship = '';
		if ($result->fields['AUTHOR'] != '')
		{
			$authorship = $result->fields['AUTHOR'];
		}
		if ($result->fields['YEAR'] != '')
		{
			$authorship .= ', ' . $result->fields['YEAR'];
		}
	
		$pattern = '\s+(?<author>\(?' . preg_quote($authorship, '/') . '\)?)';
		
		if (preg_match('/' . $pattern . '/u', $nameComplete, $m))
		{
			$obj->author = $m['author'];
		}
		
		if ($obj->nameComplete == '')
		{
			$obj->nameComplete = preg_replace('/' . $pattern . '/u', '', $nameComplete);
		}


		//print_r($obj);		
		
		//match_ion($obj, true);
		match_gbif($obj, false);
		//match_ncbi($obj, false);
		
		
		//print_r($obj);	
		
		// sql
		
		
		$num_matches = count($obj->ids);
		
		//echo $num_matches . "\n";
		
		if ($num_matches == 0)
		{
			// no match
			$keys = array();
			$values = array();

			foreach ($obj as $k => $v)
			{
				switch ($k)
				{			
					case 'taxon_guid':
					case 'name_guid':
					case 'rank':
					case 'nameComplete':
					case 'author':
						$keys[] = $k;
						$values[] = '"' . addcslashes($v, '"') . '"';
						break;
					default:
						break;
				}
			}
			
			echo 'INSERT INTO alamatch(' . join(",", $keys) . ') VALUES (' . join(",", $values) . ');' . "\n";
		}
		else
		{
			// One or more matches
			
			foreach ($obj->ids as $match)
			{
			
				// common
				$keys = array();
				$values = array();
				
				foreach ($obj as $k => $v)
				{
					switch ($k)
					{			
						case 'taxon_guid':
						case 'name_guid':
						case 'rank':
						case 'nameComplete':
						case 'author':
							$keys[] = $k;
							$values[] = '"' . addcslashes($v, '"') . '"';
							break;
						default:
							break;
					}
				}
				
				if (preg_match('/GBIF/', $match->resolvedTaxonId))
				{
					$keys[] = 'gbif_id';
					$values[] =  '"' . addcslashes(str_replace('GBIF:', '', $match->resolvedTaxonId), '"') . '"';	

					$keys[] = 'gbif_name';
					$values[]  = '"' . addcslashes($match->resolvedTaxonName, '"') . '"';		
					
					if (isset($match->resolvedAuthorName))
					{
						$keys[] = 'gbif_author';
						$values[]  = '"' . addcslashes($match->resolvedAuthorName, '"') . '"';									
					}

					$keys[] = 'gbif_match_type';
					$values[]  = '"' . addcslashes($match->resolvedMatchType, '"') . '"';	

					
					$keys[] = 'gbif_count';
					$values[]  = '"' . $num_matches . '"';		
				}				
				
				
				echo 'INSERT INTO alamatch(' . join(",", $keys) . ') VALUES (' . join(",", $values) . ');' . "\n";
						
				

			}
		}		


		$result->MoveNext();

	}
	
	
	if ($result->NumRows() < $page)
	{
		$done = true;
	}
	else
	{
		$offset += $page;
		
		//if ($offset > 3000) { $done = true; }
	}
	

}

