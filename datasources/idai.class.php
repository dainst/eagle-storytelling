<?php
/**
 * @package 	eagle-storytelling
 * @subpackage	Search in Datasources | Subplugin: iDAI Gazetteer
 * @link 		http://gazetteer.dainst.org/
 * @author 		Philipp Franck
 *
 * Status: Alpha 2
 * 
 * Sub-Plugin is nearly ready, there is only one Problem: Ich bekomme einen 400er Bad Request wenn ich die API anspreche, um einen einzelnen Record
 * zu bekommen. Eine URL wie http://gazetteer.dainst.org/doc/2281530.json, die im Browser ein Ergebnis liefert, klappt mit PHP über curl oder
 * file_get_contents nicht.  
 *
 */


namespace esa_datasource {
	class idai extends abstract_datasource {


		public $title = 'iDAI Gazetteer';
		public $homeurl = "http://gazetteer.dainst.org/";
		
		public $pagination = false;

		function api_search_url($query, $params = array()) {
			$query = urlencode($query);
			return "http://gazetteer.dainst.org/search.json?q={$query}";
		}
			
		function api_single_url($id) {
			$query = urlencode($id);
			return "http://gazetteer.dainst.org/search.json?q={%22bool%22:{%22must%22:%5B%20{%20%22match%22:%20{%20%22_id%22:%20$id%20}}%5D}}&type=extended";
			//return "http://gazetteer.dainst.org/doc/$id.json";
		}

		function api_record_url($id) {
			$query = urlencode($id);
			return "http://gazetteer.dainst.org/app/#!/show/$id";
		}
			
		function api_url_parser($string) {
			if (preg_match('#https?:\/\/gazetteer\.dainst\.org\/(app\/\#\!\/show|place)\/(.*)\??.?#', $string, $match)) {
				
				//http://gazetteer.dainst.org/place/2059461 or ttp://gazetteer.dainst.org/app/#!/show/2059461
				
				return "http://gazetteer.dainst.org/search.json?q={%22bool%22:{%22must%22:%5B%20{%20%22match%22:%20{%20%22_id%22:%20{$match[2]}%20}}%5D}}&type=extended";
				//return "http://gazetteer.dainst.org/doc/{$match[2]}.json";
			}
		}
			

			
		function parse_result_set($response) {
			$response = $this->_json_decode($response);


			$this->results = array();
			$list = (isset($response->result)) ? $response->result : array($response);
			foreach ($list as $result) {

				$the_name = $result->prefName->title;
				$the_name .= ($result->prefName->ancient == true) ? ' (ancient)' : '';
				$alt_names = array();
				if (isset($result->names) and is_array($result->names)) {
					foreach($result->names as $name) {
						$alt_names[] = $name->title;
					}
				}
				$name_list = implode(', ', $alt_names);
				$type_list = (isset($result->types)) ? implode(', ', $result->types) : '';
				$type_label = (count($type_list) > 1) ? 'Types' : "Type";
				
				if (isset($result->prefLocation)) {
					list($long, $lat) = $result->prefLocation->coordinates;
					$hint = '';
				} else {
					// fetch coordinates from parent location 
					$parent = $this->_json_decode($this->_fetch_external_data($this->api_url_parser($result->parent)));
					list($long, $lat) = $parent->result[0]->prefLocation->coordinates;
					$hint = "<li>(Coordinates taken from parent Object: {$parent->result[0]->prefName->title}</li>";
				}
				
				$html  = "<div class='esa_item_left_column_max_left'>";
				$html .= "<div class='esa_item_map' id='esa_item_map-{$result->gazId}@idai' data-latitude='$lat' data-longitude='$long'>&nbsp;</div>";
				$html .= "</div>";
				
				$html .= "<div class='esa_item_right_column_max_left'>";
				$html .= "<h4>$the_name</h4>";

				$html .= "<ul class='datatable'>";
				
				if (count($alt_names)) {
					$html .= "<li><strong>Names: </strong>$name_list</li>";
				}
				if ($type_list) {
					$html .= "<li><strong>$type_label: </strong>$type_list</li>";
				}
				
				$html .= "<li><strong>Latitude: </strong>$lat</li>";
				$html .= "<li><strong>Longitude: </strong>$long</li>";
				if ($hint) {
					$html .= $hint;
				}
				$html .= "</ul>";
				
				$html .= "</div>";
					
				$this->results[] = new \esa_item('idai', $result->gazId, $html, $this->api_record_url($result->gazId));
			}
			return $this->results;
		}

		function parse_result($response) {
			// if always return a whole set
			$res = $this->parse_result_set($response);
			return $res[0];
		}

	}
}
?>