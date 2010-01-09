<?php
error_reporting( E_ALL );
/**
 * @file
 * @ingroup Search
 */

/**
 * Search engine hook for Google Search Appliance
 * @ingroup Search
 */
class SearchGSA extends SearchEngine {
	
    /**
	 * Perform a full text search query and return a result set.
	 *
	 * @param string $term - Raw search term
	 * @return GSASearchResultSet
	 * @access public
	 */
	function searchText( $term ) {
		global $wgGSA, $wgGSAsite, $wgGSAclient, $wgServer, $wgScriptPath, $wgGSAignoreArchive;
	
		$xml = array();
		$start = null;
		$end = 0;
		for ( $i=$this->offset; 
			$i < $this->limit + $this->offset; 
			$i += 100 ) 
		{
			$params = array( 'as_sitesearch' => $wgServer . $wgScriptPath,
					 'q' => $term,
					 'site' => $wgGSAsite,
					 'client' => $wgGSAclient,
					 'output' => 'xml',
					 'start' => $i,
					 'num' => ( $this->limit > 100 ? 100 : $this->limit ) );
            if ($wgGSAignoreArchive) {
                $params['q'] .= ' -inurl:images/archive';
            }
			$request = sprintf("%s?%s", $wgGSA, http_build_query($params));
			$new_xml = new SimpleXMLElement(file_get_contents($request));
	        //@file_put_contents('/tmp/xml-infra-text.xml', file_get_contents($request));	
			$start = is_null($start) || $start > $new_xml->RES['SN'] ? $new_xml->RES['SN'] : $start;
			$end = $end < $new_xml->RES['EN'] ? $new_xml->RES['EN'] : $end;
			$xml[] = $new_xml;
		}
		//print "$request <br/>";
        
        return new GSASearchResultSet($xml, array($term), $this->limit, $this->offset, $start, $end);
	}

	/**
	 * Perform a title-only search query and return a result set.
	 *
	 * @param string $term - Raw search term
	 * @return GSASearchResultSet
	 * @access public
	 */
	function searchTitle( $term ) {
		global $wgGSA, $wgGSAsite, $wgGSAclient, $wgServer, $wgScriptPath, $wgGSAignoreArchive;

		$xml = array();
		$start = null;
		$end = 0;
		for ( $i=$this->offset; 
			$i < $this->limit + $this->offset; 
			$i += 100 ) 
		{
			$params = array( 'as_sitesearch' => $wgServer . $wgScriptPath,
					 'as_occt' => 'title',
					 'q' => $term,
					 'site' => $wgGSAsite,
					 'client' => $wgGSAclient,
					 'output' => 'xml',
					 'start' => $i,
					 'num' => ( $this->limit > 100 ? 100 : $this->limit ) );
			$request = sprintf("%s?%s", $wgGSA, http_build_query($params));
			$new_xml = new SimpleXMLElement(file_get_contents($request));
	        //@file_put_contents('/tmp/xml-infra-title.xml', file_get_contents($request));	
			$start = is_null($start) || $start > $new_xml->RES['SN'] ? $new_xml->RES['SN'] : $start;
			$end = $end < $new_xml->RES['EN'] ? $new_xml->RES['EN'] : $end;
			$xml[] = $new_xml;
		}
		//print "$request <br/>";

		return new GSASearchResultSet($xml, array($term), $this->limit, $this->offset, $start, $end);
	}

    function searchInterwiki( $term ) {
        global $wgGSA, $wgGSAsite, $wgGSAclient, $wgServer, $wgScriptPath,
            $wgSitename, $wgGSAinterwikiCount, $wgGSAignoreArchive;
      
        $this->limit = $wgGSAinterwikiCount > 0 ? $wgGSAinterwikiCount : $this->limit; 
 
        $path = substr($wgScriptPath, 0, strrpos($wgScriptPath, '/'));
        $wiki = substr($wgScriptPath, strrpos($wgScriptPath, '/') + 1);

        $xml = array();
        $start = null;
        $end = 0;
        for ( $i=$this->offset; 
            $i < $this->limit + $this->offset; 
            $i += 100 ) 
        {
            $params = array( 'as_sitesearch' => $wgServer . $path,
                     'q' => $term . " -inurl:wiki/$wiki",
                     'client' => $wgGSAclient,
                     'output' => 'xml',
                     'start' => $i,
                     'num' => ( $this->limit > 100 ? 100 : $this->limit ) );
            if ($wgGSAignoreArchive) {
                $params['q'] .= ' -inurl:images/archive';
            }
            $request = sprintf("%s?%s", $wgGSA, http_build_query($params));
            $contents = file_get_contents($request);
            
            // could parse XML response but ran into some issues so using regex currently
            $contents = preg_replace(
                array(
                    '/' . str_replace('/', '\/', $wgServer . $path . '/') . '([^\/]*)\//',
                    '/ - [^-<]* Wiki/',
                ),
                array(
                    '$1:',
                    '',
                ),
                $contents
            );

            // Debugging regular expressions...
            //@file_put_contents('/tmp/xml-raw.xml', $contents);
            //@file_put_contents('/tmp/xml-regexed.xml', $zcontents);

            $new_xml = new SimpleXMLElement($contents);

            // return null when there are no hits to avoid displaying interwiki box
            if (!isset($new_xml->RES->R)) {
                return null;
            }

            // group results by wiki
            $sort_input = array();
            foreach ($new_xml->RES->R as $r) {
                $de = dom_import_simplexml( $r );
                $sort_input[(string) $r->U] = $de->cloneNode( true );
            }

            // actual sort
            $reordered = ksort($sort_input);

            // now swap the values in the SimpleXML structure
            if ($reordered) {
                $counter = 0;
                foreach ($sort_input as $de) {
                    // TODO figure out how to do this properly (knock knock PHP!)
                    // would like to be able to just swap around entire R elements
                    $sxe = simplexml_import_dom($de);
                    $new_xml->RES->R[$counter]->U   = $sxe->U;
                    $new_xml->RES->R[$counter]->T   = $sxe->T;
                    if (stripos($sxe->U, 'images/')) {
                        $pathinfo = pathinfo($sxe->U);
                        $new_xml->RES->R[$counter]->T .= ' (' . $pathinfo['extension'] . ')';
                    }
                    $new_xml->RES->R[$counter]->UE  = $sxe->UE;
                    $new_xml->RES->R[$counter]->UT  = $sxe->UT;
                    $new_xml->RES->R[$counter]->S   = $sxe->S;
                    $counter++;
                }
            }

            $start = is_null($start) || $start > $new_xml->RES['SN'] ? $new_xml->RES['SN'] : $start;
            $end = $end < $new_xml->RES['EN'] ? $new_xml->RES['EN'] : $end;
            $xml[] = $new_xml;
        }
		//print "$request <br/>";

        return new GSASearchResultSet($xml, array($term), $this->limit, $this->offset, $start, $end);
	}
}

/**
 * @ingroup Search
 */

class GSASearchResultSet extends SearchResultSet {
    var $interwikiResultSet;        // cache interwiki search results (prevent double query)

	function GSASearchResultSet( $resultSet, $terms, $limit, $offset, $start, $end ) {
		$this->mResultSet = $resultSet;
		$this->mTerms = $terms;
		$this->limit = $limit;
		$this->offset = $offset;
		$this->counter = array(0, 0);
		$this->start = $start;
		$this->end = $end;
	}

	function termMatches() {
		return $this->mTerms;
	}

	function hasSuggestion() {
		return array_key_exists('Spelling', $this->mResultSet[0]->children());
	}

	function getSuggestionQuery() {
		return strip_tags($this->mResultSet[0]->Spelling->Suggestion);
	}

	function getSuggestionSnippet() {
		return $this->mResultSet[0]->Spelling->Suggestion;
	}

	function getTotalHits() {
		return null;
	}
	
	function numRows() {
		if ( $this->start < $this->offset || empty($this->start) )
			return 0;
		$num = $this->end - $this->start + 1;
		return $num;
	}

	function next() {

		if ( $this->counter[1] >= count($this->mResultSet[$this->counter[0]]->RES->R) ) {
			$this->counter[0]++;
			$this->counter[1] = 0;
		}

		if ( $this->counter[0] >= count($this->mResultSet) ) 
			return false;
		return new GSASearchResult( $this->mResultSet[$this->counter[0]]->RES->R[$this->counter[1]++] );
		
	}

    function getInterwikiResults() {
        if ($this->interwikiResultSet != null) {
            return $this->interwikiResultSet;
        }

        $search = new SearchGSA();
        // maybe not quite right, only searchs interwiki on matched terms
        $this->interwikiResultSet = $search->searchInterwiki( implode(' ', $this->termMatches()) );
        return $this->interwikiResultSet;
    }

    /**
     * Check if there are results on other wikis
     *
     * @return boolean
     */
    function hasInterwikiResults() {
        return $this->getInterwikiResults() != null;
    }
}

class GSASearchResult extends SearchResult {
	var $mRevision = null;
    var $url_prefix = null;

	function GSASearchResult( $row ) {
		global $wgServer, $wgUploadPath, $wgScriptPath;

		$this->gsa_row = $row;
        
		$url = preg_replace(
            sprintf("/%s%s.*\/(.*)/", preg_quote($wgServer, '/'), 
			preg_quote($wgUploadPath,'/')),
            "Image:$1",
            $this->gsa_row->U
        );
		
        $this->mTitle = Title::newFromURL( str_replace("$wgServer$wgScriptPath/", '', $url) );
		if( !is_null($this->mTitle) )
			$this->mRevision = Revision::newFromTitle( $this->mTitle );
    }

    /**
     * @param array $terms terms to highlight
     * @return string highlighted text snippet, null (and not '') if not supported 
     */
    function getTextSnippet($terms){
            $this->initText();
            return $this->gsa_row->S;
    }
	
	/**
	 * @param array $terms terms to highlight
	 * @return string highlighted title, '' if not supported
	 */
	function getTitleSnippet($terms) {
        global $wgSitename;
		return str_replace(" - $wgSitename", '', $this->gsa_row->T);
	}

    function getInterwikiPrefix() {
        if (stristr($this->gsa_row->U, 'http') === 0) {
            // TODO
            // non-interwiki URL, parse out wiki name
        }
        else {
            // interwiki link like "wikipedia:Some Article"
            return substr($this->gsa_row->U, 0, strpos($this->gsa_row->U, ':'));
        }
    }

}
