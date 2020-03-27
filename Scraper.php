<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
include 'phpQuery/pquery.php';

class Scrape
{
	private $tmp_image_urls = array();
	var $images = array();
	private $final_image_count = 0;
	public $total_images = 0;
	private $referrer = '';
	private $start_url = '';
	private $company_id = 0;
	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->database();
		$this->ci->load->model('etons');
	}
	
	public function set_company_id($company_id)
	{
		$this->company_id = $company_id;
	}
	
	private function _log_message($message, $url)
	{
		$this->ci->etons->log_message($message, $url);
	}
	
	public function scrape($url)
	{
		$this->start_url = $url;
		$this->_log_message('Starting scraping with URL: '.$url, $url);
		
		$this->referrer = $url;
		$built_links = $this->_get_links($url);
		
		$return_array = array();
		
		if($built_links !== FALSE)
		{
			
			$final_links = array();
			$i = 1;
			foreach($built_links as $idx => $link)
			{
				if(isset($link) && $i < 100)
				{
					$this->_log_message('Looking at link '.$i.' of '.count($built_links).' for images', $url);
					
					$page_images = $this->_get_images($link);
					
					if(!empty($link) && !empty($page_images))
					{
						$final_links[$link]['images'] = $page_images;
						$final_links[$link]['total_images'] = count($page_images);
					}
					
					$i++;
				}
			}
			$final_links['total_images'] = count($this->images);
			$this->total_images = count($this->images);
			$this->_log_message('Total number of images found for URL: '.count($this->images).' with a score of 3 or higher', $url);
								
		
			
			return $final_links;
		}
			
	}
	
	public function get_total_images()
	{
		return $this->total_images;
	}
	
	private function _do_curl($url)
	{
		$ch = curl_init($url);
    	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.0; da; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10');
   	 	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    	
    	$file = curl_exec($ch);
    	curl_close($ch);
    	
    	return $file;
    	
	}
	
	private function _http($target, $ref)
	{
	    # Initialize PHP/CURL handle
		$ch = curl_init();
	        
		curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIE_FILE);   // Cookie management.
		curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_FILE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);    // Timeout
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.0; da; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10');   // Webbot name
		curl_setopt($ch, CURLOPT_URL, $target);             // Target site
		curl_setopt($ch, CURLOPT_REFERER, $ref);            // Referer value
		curl_setopt($ch, CURLOPT_VERBOSE, FALSE);           // Minimize logs
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);    // No certificate
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);     // Follow redirects
		curl_setopt($ch, CURLOPT_MAXREDIRS, 4);             // Limit redirections to four
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);     // Return in string
	    
	    # Create return array
	    $return_array['FILE']   = curl_exec($ch); 
	    $return_array['STATUS'] = curl_getinfo($ch);
	    $return_array['ERROR']  = curl_error($ch);
	    
	    # Close PHP/CURL handle
	  	curl_close($ch);
	    
	    # Return results
	  	return $return_array;
    }
	
	
	private function _get_links($url)
	{
		$c_url = $this->_http($url, $this->referrer);
		
		if(empty($c_url['ERROR']))
		{
			
			$doc = phpQuery::newDocument($c_url['FILE']);
			$links = array();
			foreach(pq('a') as $a)
			{
				$link = pq($a)->attr('href');
				$link_parts = parse_url($link);
				if($this->_compare_host($url, $link) && (strpos($link, '.pdf') === false))
				{
					$links[] = $this->_resolve_address($link, $this->referrer);
				}
				
			}
			foreach(pq('area') as $a)
			{
				$link = pq($a)->attr('href');
				$link_parts = parse_url($link);
				if($this->_compare_host($url, $link) && (strpos($link, '.pdf') === false))
				{
					$links[] = $this->_resolve_address($link, $this->referrer);
				}
			}
			$link_count = count($links);
			$this->_log_message('Found '.$link_count.' links at '.$url, $url);
			
			return $links;
		}
		else
		{
			return $c_url['ERROR'];
		}
	}
	
	public function get_images($url)
	{
		return $this->_get_images($url);
	}
	
	private function _get_images($url)
	{
		$this->_log_message('About to retrieve images from '.$url, $url);
		
		$c_url = $this->_http($url, $this->referrer);
		
		if(empty($c_url['ERROR']))
		{
			$doc = phpQuery::newDocument($c_url['FILE']);
			$scraped_imgs = array();
			
			$images = pq('img');
			if(count($images) > 0)
			{
				$this->_log_message('Found '.count($images).' images in URL: '.$url, $url);
				
				foreach($images as $img)
				{
					
					$src = $this->_resolve_address(pq($img)->attr('src'), $this->referrer);
					
					$w = pq($img)->attr('width');
					$h = pq($img)->attr('height');
					$alt = pq($img)->attr('alt');
					$title = pq($img)->attr('title');
					
					
					$this->_log_message('Looking at image: '.$src.' in URL: '.$url, $url);
					
					if(empty($w) && empty($h))
					{
						$this->_log_message('Width and height attributes not set for image: '.$src, $url);
						
						if($img_info = @getimagesize($src))
						{
							
							$w = $img_info[0];
							$h = $img_info[1];
							$this->_log_message('Using getimagesize(), width is '.$w.' and height is '.$h.' for image: '.$src, $url);
							
						}
						else
						{
							$this->_log_message('Width and height not found using getimagesize() for image: '.$src, $url);
							
							$w = 1;
							$h = 1;
						}
						
					}
					if($w > 100 && $h > 100)
					{
						
						$ratio = $this->_calc_ratio($w, $h);
						
						$this->_log_message('Ratio for image: '.$src.' is '.$ratio, $url);
						
					}
					else
					{
						$ratio = 0.1;
					}
					
					$image = array('url' => $src, 'w' => $w, 'h' => $h, 'alt' => $alt, 'ratio' => $ratio);
					$image['status'] = $c_url['STATUS'];
					
					$score = $this->_score_image($image);
					$this->_log_message('Score for image: '.$src.' is '.$score, $url);
					
					$image['score'] = intval($score);
					if($image['score'] >= 2)
					{
						if(!in_array($src, $this->images))
						{
							if(count($this->images) < 20)
							{
								$this->_log_message('Image: '.$src.' is scored as good, putting it into pending images table', $url);
							
								echo 'Image: '.$src.' is scored as good, putting it into pending images table'."\n\n";
								$scraped_imgs[] = $image;
								$this->images[$src] = $src;
								$this->_save_image($image, $url, $this->company_id);
							}
							else
							{
								return;
							}
							
						}	
					}
					
				}
				
				return $scraped_imgs;
			}
			
			
		}
		else
		{
			return $c_url['ERROR'];
		}
		
	}
	
	private function _save_image($image_array, $page_url, $company_id)
	{
		$data = array(
			'company_id_FK' => $company_id,
			'page_url' => $page_url,
			'img_src' => $image_array['url'],
			'img_alt' => $image_array['alt'],
			'img_width' => $image_array['w'],
			'img_height' => $image_array['h'],
			'timestamp' => time()
		);
		$scrape_image_id = $this->etons->save_scraped_image($data);
	}
	
	private function _process_image($src, $w, $h, $alt)
	{
		if(empty($w) && empty($h))
		{
			if($image_data = $this->_get_image_size($src))
			{
				print_r($image_data);
				$w = $image_data[0];
				$h = $image_data[1];
			}
			else
			{
				$w = 1;
				$h = 1;
			}
		}
		if($w > 100 && $h > 100)
		{
			$ratio = $this->_calc_ratio($w, $h);
			
		}
		else
		{
			$ratio = 0.1;
		}
		$image = array('url' => $src, 'w' => $w, 'h' => $h, 'alt' => $alt, 'ratio' => $ratio);
		$image_score = $this->_score_image($image);
		if($image_score >= 2)
		{
			$image['score'] = intval($image_score);
			return $image;
		}
		
	}
	
	private function _get_image_size($src)
	{
		
		return getimagesize($src);
	}
	
	private function _test_link($link, $testfor)
	{
		return stristr($link, $testfor);
	}
	
	function _compare_host($url1, $url2)
	{
		$info = @parse_url($url1);
		if(empty($info))
		{
			return FALSE;
		}
		
		$host1 = $info['host'];
		
		$info2 = @parse_url($url2);
		if(empty($info2))
		{
			return FALSE;
		}
		$host = $info2['host'];
		return (@strtolower($host1) === @strtolower($host2));
	}
	
	private function _score_image($img)
	{
		
		$score = 0;
		
		if(strlen($img['alt']) > 0)
		{
			$score += 1;
			
		}
		$img['w'] = intval($img['w']);
		$img['h'] = intval($img['h']);
		if(!empty($img['w']) && !empty($img['h']))
		{
			
			
										
			if(($img['w'] > 600) || ($img['h'] > 600))
			{
				$score += 3;
				
			}
			elseif(($img['w'] > 400 && $img['w'] < 600) || ($img['h'] > 400 && $img['h'] < 600))
			{
				$score += 2;
				
			}
			elseif(($img['w'] > 200 && $img['w'] < 400) || ($img['h']> 200 && $img['h'] < 400))
			{
				$score += 1;
				
			}
		
			
			//$ratio = round($ratio, 1);
			if($this->_check_ratio($img['ratio'])) 
			{
				$score += 1;
				
			}
			else
			{
				$score -= 1;
				
			}
			
		}
		else
		{
			$score += 1;
			
		}
		
		
		
		return $score;
	}
	
	private function _calc_ratio($w, $h)
	{
		if(!empty($w) && !empty($h))
		{
			return round($w / $h, 1, PHP_ROUND_HALF_UP);
		}
		else
		{
			return "no ratio";
		}
		
	}
	
	private function _check_ratio($num)
	{
		return (($num > 0.5) === ($num < 1.5));
	}
	
	private function _resolve_address($link, $page_base)
	{
	    #---------------------------------------------------------- 
	    # CONDITION INCOMING LINK ADDRESS
		#
		
		$link = trim($link);
		$page_base = trim($page_base);
	    
		# if there isn't one, put a "/" at the end of the $page_base
		$page_base = trim($page_base);
		if( (strrpos($page_base, "/")+1) != strlen($page_base) )
			$page_base = $page_base."/";
	    
		# remove unwanted characters from $link
		$link = str_replace(";", "", $link);			// remove ; characters
		$link = str_replace("\"", "", $link);			// remove " characters
		$link = str_replace("'", "", $link);			// remove ' characters
		$abs_address = $page_base.$link;
	    
	    $abs_address = str_replace("/./", "/", $abs_address);
	    
		$abs_done = 0;
	    
	    #---------------------------------------------------------- 
	    # LOOK FOR REFERENCES TO THE BASE DOMAIN ADDRESS
	    #---------------------------------------------------------- 
	    # There are essentially four types of addresses to resolve:
	    # 1. References to the base domain address
	    # 2. References to higher directories
	    # 3. References to the base directory
	    # 4. Addresses that are alreday fully resolved
		#
		if($abs_done==0)
		{
			# Use domain base address if $link starts with "/"
			if (substr($link, 0, 1) == "/")
			{
				// find the left_most "."
				$pos_left_most_dot = strrpos($page_base, ".");
		
				# Find the left-most "/" in $page_base after the dot 
				for($xx=$pos_left_most_dot; $xx<strlen($page_base); $xx++)
				{
					if( substr($page_base, $xx, 1)=="/")
						break;
				}
            
				$domain_base_address = $this->_get_base_domain_address($page_base);
	            
				$abs_address = $domain_base_address.$link;
				$abs_done=1;
			}
		}

	    #---------------------------------------------------------- 
	    # LOOK FOR REFERENCES TO HIGHER DIRECTORIES
		#
		if($abs_done==0)
		{
			if (substr($link, 0, 3) == "../")
			{
				$page_base=trim($page_base);
				$right_most_slash = strrpos($page_base, "/");
		        
				// remove slash if at end of $page base
				if($right_most_slash==strlen($page_base)-1)
				{
					$page_base = substr($page_base, 0, strlen($page_base)-1);
					$right_most_slash = strrpos($page_base, "/");
				}
            
				if ($right_most_slash<8)
					$unadjusted_base_address = $page_base;
	        
				$not_done=TRUE;
				while($not_done)
				{
					// bring page base back one level
					list($page_base, $link) = $this->_move_address_back_one_level($page_base, $link);
					if(substr($link, 0, 3)!="../")
						$not_done=FALSE;
				}
				if(isset($unadjusted_base_address))		
					$abs_address = $unadjusted_base_address."/".$link;
				else
					$abs_address = $page_base."/".$link;
				$abs_done=1;
			}
		}
        
	    #---------------------------------------------------------- 
	    # LOOK FOR REFERENCES TO BASE DIRECTORY
		#
		if($abs_done==0)
		{
			if (substr($link, 0, "1") == "/")
			{
				$link = substr($link, 1, strlen($link)-1);	// remove leading "/"
				$abs_address = $page_base.$link;			// combine object with base address
				$abs_done=1;
			}
		}
    
	    #---------------------------------------------------------- 
	    # LOOK FOR REFERENCES THAT ARE ALREADY ABSOLUTE
		#
	    if($abs_done==0)
		{
			if (substr($link, 0, 4) == "http")
			{
				$abs_address = $link;
				$abs_done=1;
			}
		}
    
	    #---------------------------------------------------------- 
	    # ADD PROTOCOL IDENTIFIER IF NEEDED
		#
		if( (substr($abs_address, 0, 7)!="http://") && (substr($abs_address, 0, 8)!="https://") )
			$abs_address = "http://".$abs_address;
    
		return $abs_address;  
	}
	
	private function _get_base_page_address($url)
	{
		$slash_position = strrpos($url, "/");

		if ($slash_position>8)
		{
			$page_base = substr($url, 0, $slash_position+1);  	// "$slash_position+1" to include the "/".
		}
		else
		{
			$page_base = $url;  	// $url is already the page base, without modification.
			if($slash_position!=strlen($url))
				$page_base=$page_base."/";
		}

		
		# If the page base ends with a \\, replace with a \
		$last_two_characters = substr($page_base, strlen($page_base)-2, 2);
		if($last_two_characters=="//")
			$page_base = substr($page_base, 0, strlen($page_base)-1);

		return $page_base;
	}
	
	private function _get_base_domain_address($page_base)
	{
		for ($pointer=8; $pointer<strlen($page_base); $pointer++)
		{
			if (substr($page_base, $pointer, 1)=="/")
			{
				$domain_base=substr($page_base, 0, $pointer);
				break;
			}
		}
	
		$last_two_characters = substr($page_base, strlen($page_base)-2, 2);
		if($last_two_characters=="//")
			$page_base = substr($page_base, 0, strlen($page_base)-1);

		return $domain_base;
	}
	
	private function _move_address_back_one_level($page_base, $object_source)
	{
		// bring page base back one leve
		$right_most_slash = strrpos($page_base, "/");
		$new_page_base = substr($page_base, 0, $right_most_slash);

		// remove "../" from front of object_source
		$object_source = substr($object_source, 3, strlen($object_source)-3);

		$return_array[0]=$new_page_base;
		$return_array[1]=$object_source;
		return $return_array;
	} 
	
}
