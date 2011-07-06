<?php
	if(!function_exists("sornot"))
	{
		function sornot($t, $n)
		{
			if($n == 1)
				return $t;
			else
				return $t . "s";
		}
	}
	
	//thanks: http://wordpress.org/support/topic/is_plugin_active
	function pmpro_is_plugin_active( $plugin ) {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ) );
	}
	
	//scraping - override n if you have more than 1 group of matches and don't want the first group
	function pmpro_getMatches($p, $s, $firstvalue = FALSE, $n = 1)
	{
		$ok = preg_match_all($p, $s, $matches);		
		
		if(!$ok)
			return false;
		else
		{		
			if($firstvalue)
				return $matches[$n][0];
			else
				return $matches[$n];
		}
	}
	
	function pmpro_br2nl($text, $tags = "br")
	{
		$tags = explode(" ", $tags);

		foreach($tags as $tag)
		{
			$text = eregi_replace("<" . $tag . "[^>]*>", "\n", $text);
			$text = eregi_replace("</" . $tag . "[^>]*>", "\n", $text);
		}

		return($text);
	}
	
	function pmpro_getOption($s)
	{
		if($_REQUEST[$s])
			return $_REQUEST[$s];
		elseif(get_option("pmpro_" . $s))
			return get_option("pmpro_" . $s);
		else
			return "";
	}
	
	function pmpro_setOption($s, $v = NULL)
	{
		//no value is given, set v to the request var
		if($v === NULL)
			$v = $_REQUEST[$s];
				
		if(is_array($v))
			$v = implode(",", $v);
		
		return update_option("pmpro_" . $s, $v);	
	}		
	
	function pmpro_get_slug($post_id)
	{	
		global $pmpro_slugs, $wpdb;
		if(!$pmpro_slugs[$post_id])
			$pmpro_slugs[$post_id] = $wpdb->get_var("SELECT post_name FROM $wpdb->posts WHERE ID = '" . $post_id . "' LIMIT 1");
		
		return $pmpro_slugs[$post_id];			
	}
	
	function pmpro_url($page = NULL, $querystring = "", $scheme = NULL)
	{
		global $besecure;		
		$besecure = apply_filters("besecure", $besecure);
		
		if(!$scheme && $besecure)
			$scheme = "https";
		elseif(!$scheme)
			$scheme = "http";
					
		if(!$page)
			$page = "levels";
			
		global $pmpro_pages;
		
		//? vs &
		if(strpos(get_permalink($pmpro_pages[$page]), "?"))
			return home_url(str_replace(home_url(), "", get_permalink($pmpro_pages[$page])) . str_replace("?", "&", $querystring), $scheme);
		else
			return home_url(str_replace(home_url(), "", get_permalink($pmpro_pages[$page])) . $querystring, $scheme);
	}
	
	function pmpro_isLevelFree(&$level)
	{
		if($level->initial_payment <= 0 && $level->billing_amount <= 0 && $level->trial_amount <= 0)
			return true;
		else
			return false;
	}
	
	function pmpro_isLevelRecurring(&$level)
	{
		if($level->billing_amount > 0 || $level->trial_amount > 0)
			return true;
		else
			return false;
	}
	
	function pmpro_isLevelTrial(&$level)
	{
		if($level->trial_limit > 0)
		{			
			return true;
		}
		else
			return false;
	}
	
	function pmpro_getLevelCost(&$level)
	{
		$r = '
		The price for membership is <strong>$' . number_format($level->initial_payment, 2) . '</strong> ';
		if($level->billing_amount != '0.00')
		{
			$r .= 'and then <strong>$' . $level->billing_amount;
			if($level->cycle_number == '1') 
			{ 
				$r .= ' per ';
			}
			else
			{ 
				$r .= 'every ' . $level->cycle_number . ' ';
			}

			$r .= sornot($level->cycle_period,$level->cycle_number);
			
			if($level->billing_limit) 
			{  
				$r .= ' for ' . $level->billing_limit . ' ' . sornot($level->cycle_period,$level->billing_limit) . '.';
			} 
			else
				$r .= '.';
			
			$r .= '</strong>';
		}		
		
		if($level->trial_limit)
		{ 
			$r .= ' After your initial payment, your first ';
			if($level->trial_amount == '0.00') 
			{ 				
				if($level->trial_limit == '1') 
				{ 										
					$r .= 'payment is Free.';
				} 
				else
				{ 					
					$r .= $level->trial_limit . ' payments are Free.';
				} 
			} 
			else
			{ 				
				$r .= $level->trial_limit.' ' .sornot("payment", $level->trial_limit) . ' will cost $' . $level->trial_amount . '.';
			} 
		}  
		
		//taxes?
		$tax_state = pmpro_getOption("tax_state");
		$tax_rate = pmpro_getOption("tax_rate");
		
		if($tax_state && $tax_rate)
		{
			$r .= " Customers in " . $tax_state . " will be charged " . round($tax_rate * 100) . "% tax.";
		}
		
		return $r;
	}
	
	function pmpro_hideAds()
	{
		global $pmpro_display_ads;
		return !$pmpro_display_ads;
	}
	
	function pmpro_displayAds()
	{
		global $pmpro_display_ads;
		return $pmpro_display_ads;
	}
	
	function pmpro_next_payment($user_id = NULL)
	{
		global $wpdb, $current_user;
		if(!$user_id)
			$user_id = $current_user->ID;
			
		if(!$user_id)
			return false;
			
		//when were they last billed
		$lastdate = $wpdb->get_var("SELECT UNIX_TIMESTAMP(timestamp) as timestamp FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $user_id . "' ORDER BY timestamp DESC LIMIT 1");
				
		if($lastdate)
		{
			//next payment will be same day, following month
			$lastmonth = date("n", $lastdate);
			$lastday = date("j", $lastdate);
			$lastyear = date("Y", $lastdate);
						
			$nextmonth = ((int)$lastmonth) + 1;
			if($nextmonth == 13)
			{
				$nextmonth = 1;
				$nextyear = ((int)$lastyear) + 1;
			}
			else
				$nextyear = $lastyear;
			
			$daysinnextmonth = date("t", strtotime($nextyear . "-" . $nextmonth . "-1"));
			
			if($daysinnextmonth < $lastday)
			{
				$nextday = $daysinnextmonth;
			}
			else
				$nextday = $lastday;
				
			return strtotime($nextyear . "-" . $nextmonth . "-" . $nextday);
		}
		else
		{
			return false;
		}
		
	}
	
	if(!function_exists("last4"))
	{
		function last4($t)
		{
			return substr($t, strlen($t) - 4, 4);
		}	
	}

	if(!function_exists("hideCardNumber"))
	{
		function hideCardNumber($c, $dashes = true)
		{
			if($c)
			{
				if($dashes)
					return "XXXX-XXXX-XXXX-" . substr($c, strlen($c) - 4, 4);
				else
					return "XXXXXXXXXXXX" . substr($c, strlen($c) - 4, 4);
			}
			else
			{
				return "";	
			}
		}
	}
	
	if(!function_exists("cleanPhone"))
	{
		function cleanPhone($phone)
		{
			//clean the phone
			$phone = str_replace("-", "", $phone);
			$phone = str_replace(".", "", $phone);
			$phone = str_replace("(", "", $phone);
			$phone = str_replace(")", "", $phone);
			$phone = str_replace(" ", "", $phone);
		
			return $phone;
		}
	}

	if(!function_exists("formatPhone"))
	{
		function formatPhone($phone)
		{
			$phone = cleanPhone($phone);
			
			if(strlen($phone) == 11)
				return substr($phone, 0, 1) . " (" . substr($phone, 1, 3) . ") " . substr($phone, 4, 3) . "-" . substr($phone, 7, 4);
			elseif(strlen($phone) == 10)
				return "(" . substr($phone, 0, 3) . ") " . substr($phone, 3, 3) . "-" . substr($phone, 6, 4);
			elseif(strlen($phone) == 7)
				return substr($phone, 0, 3) . "-" . substr($phone, 3, 4);
			else
				return $phone;
		}
	}

	function pmpro_showRequiresMembershipMessage()
	{
		//get the correct message
		if(is_feed())
		{
			$content = pmpro_getOption("rsstext");
			$content = str_replace("!!levels!!", implode(", ", $post_membership_levels_names), $content);
		}
		elseif($current_user->ID)
		{		
			//not a member
			$content = pmpro_getOption("nonmembertext");
			$content = str_replace("!!levels!!", implode(", ", $post_membership_levels_names), $content);
		}
		else
		{
			//not logged in!
			$content = pmpro_getOption("notloggedintext");
			$content = str_replace("!!levels!!", implode(", ", $post_membership_levels_names), $content);
		}	
	}

	/* pmpro_hasMembershipLevel() checks if the passed user is a member of the passed level	
	 *
	 * $level may either be the ID or name of the desired membership_level. (or an array of such)
	 * If $user_id is omitted, the value will be retrieved from $current_user.
	 *
	 * Return values:
	 *		Success returns boolean true.
	 *		Failure returns a string containing the error message.
	 */
	function pmpro_hasMembershipLevel($levels = NULL, $user_id = NULL)
	{
		global $current_user, $all_membership_levels, $wpdb;
		
		if($user_id)
		{
			//get the membership level from the global array
			$membership_level = $all_membership_levels[$user_id];
			if(!$membership_level)
			{
				//no level, check the db and add to array
				$membership_level = $wpdb->get_row("SELECT l.id AS ID, l.*
													FROM {$wpdb->pmpro_membership_levels} AS l
													JOIN {$wpdb->pmpro_memberships_users} AS mu ON (l.id = mu.membership_id)
													WHERE mu.user_id = $user_id
													LIMIT 1");
				if($membership_level)
					$all_membership_levels[$user_id] = $membership_level;
				else
					$all_memberships_levels[$user_id] = -1;	//not a member of anything
			}
		}
		else
		{
			//no user_id passed, check the current user
			$user_id = $current_user->ID;
			$membership_level = $current_user->membership_level;
		}								
		
		//if 0 was passed, return true if they have no level and false if they have any				
		if(is_array($levels))
		{
			if($levels[0] === "0")
			{
				if($membership_level->ID)
					return false;
				else
					return true;
			}
		}
		else
		{
			if($levels === "0")
			{
				if($membership_level->ID)
					return false;
				else
					return true;
			}
		}
			
		//no levels?
		if($membership_level == "-1" || !$membership_level)
			return false;				
		
		//if no level var was passed, we're just checking if they have any level
		if(!$levels)
		{
			if($membership_level->ID)
				return true;
			else
				return false;
		}		
		
		//okay, so something to check let's set the levels
		if(!is_array($levels))
			$levels = array($levels);
			
		//and check each one
		foreach($levels as $level)
		{
			if($level == $membership_level->ID || $level == $membership_level->name)
			{				
				return true;
			}
		}
		
		return false;
	}
	
	/* pmpro_changeMembershipLevel() creatues or updates the membership level of the given user to the given level.
	 *
	 * $level may either be the ID or name of the desired membership_level.
	 * If $user_id is omitted, the value will be retrieved from $current_user.
	 *
	 * Return values:
	 *		Success returns boolean true.
	 *		Failure returns boolean false.
	 */
	function pmpro_changeMembershipLevel($level, $user_id = NULL)
	{								
		global $wpdb;
		global $current_user, $pmpro_error;

		if(!$user_id)
			$user_id = $current_user->ID;
			
		if(!$user_id)
		{
			$pmpro_error = "User ID not found.";
			return false;
		}
					
		//was a name passed? (Todo: make sure level names have at least one non-numeric character.		
		if($level !== "" && $level !== false && $level !== NULL && !is_numeric($level))
		{
			$level = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_membership_levels WHERE name = '" . $wpdb->escape($level) . "' LIMIT 1");			
			if(!$level)	
			{
				$pmpro_error = "Membership level not found.";
				return false;
			}
		}
				
		//are they even changing?
		$old_level = $wpdb->get_row("SELECT * FROM  $wpdb->pmpro_memberships_users WHERE user_id = '" . $user_id . "'");
		if($old_level->membership_id == $level)
			return false;	//not changing
		
		//are they paying? may need to cancel their old membership				
		if(!pmpro_isLevelFree($old_level))
			$paying = true;
			
		if($paying)
		{					
			//get last order
			$order = new MemberOrder();
			$order->getLastMemberOrder($user_id);						
						
			if($order->subscription_transaction_id)
			{
				if($order->cancel())
				{
					//we're good					
				}
				else
				{				
					//uh oh										
					$pmpro_error = "There was an error canceling your membership: " . $order->error;				
					return false;
				}				
			}
		}
			
		//adding, changing, or deleting
		if($level)
		{						
			//adding, changing
			$sql = "REPLACE INTO $wpdb->pmpro_memberships_users (`membership_id`,`user_id`) VALUES ('" . $level . "','" . $user_id . "')";
		}
		else
		{
			//false or null or 0 was passed, so we're deleting removing
			$sql = "DELETE FROM $wpdb->pmpro_memberships_users WHERE user_id = '" . $wpdb->escape($user_id) . "' LIMIT 1";			
		}
		
		//run the query, return		
		if(!$wpdb->query($sql))
		{
			if(mysql_errno())
				$pmpro_error = "Error: " . mysql_error();			
			return false;
		}
		else 
		{
			pmpro_set_current_user();
			do_action("pmpro_after_change_membership_level", $level, $user_id);
			return true;
		}
	}

	/* pmpro_toggleMembershipCategory() creates or deletes a linking entry between the membership level and post category tables.
	 *
	 * $level may either be the ID or name of the desired membership_level.
	 * $category must be a valid post category ID.
	 *
	 * Return values:
	 *		Success returns boolean true.
	 *		Failure returns a string containing the error message.
	 */
  function pmpro_toggleMembershipCategory( $level, $category, $value )
  {
    global $wpdb;
    $category = intval($category);

		if ( ($level = intval($level)) <= 0 )
		{
			$safe = addslashes($level);
			if ( ($level = intval($wpdb->get_var("SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name = '$safe' LIMIT 1"))) <= 0 )
			{
				return "Membership level not found.";
			}
		}

    if ( $value )
    {
      $sql = "REPLACE INTO {$wpdb->pmpro_memberships_categories} (`membership_id`,`category_id`) VALUES ('$level','$category')";
      $wpdb->query($sql);		
      if(mysql_errno()) return mysql_error();
    }
    else
    {
      $sql = "DELETE FROM {$wpdb->pmpro_memberships_categories} WHERE `membership_id` = '$level' AND `category_id` = '$category' LIMIT 1";
      $wpdb->query($sql);		
      if(mysql_errno()) return mysql_error();
    }

    return true;
  }

	/* pmpro_updateMembershipCategories() ensures that all those and only those categories given
   * are associated with the given membership level.
	 *
   * $level is a valid membership level ID or name
   * $categories is an array of post category IDs
   *
	 * Return values:
	 *		Success returns boolean true.
	 *		Failure returns a string containing the error message.
	 */
  function pmpro_updateMembershipCategories( $level, $categories ) {
    global $wpdb;
    $category = intval($category);

		if ( ($level = intval($level)) <= 0 )
		{
			$safe = addslashes($level);
			if ( ($level = intval($wpdb->get_var("SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name = '$safe' LIMIT 1"))) <= 0 )
			{
				return "Membership level not found.";
			}
		}

    // remove all existing links...
    $sql = "DELETE FROM {$wpdb->pmpro_memberships_categories} WHERE `membership_id` = '$level'";
    $wpdb->query($sql);		
    if(mysql_errno()) return mysql_error();

    // add the given links [back?] in...
    foreach ( $categories as $cat )
    {
      if ( is_string( $r = pmpro_toggleMembershipCategory( $level, $cat, true ) ) )
      {
        return $r;
      }
    }

    return true;
  }
  
	function pmpro_isAdmin($user_id = NULL)
	{
		global $current_user, $wpdb;
		if(!$user_id)
			$user_id = $current_user->ID;
		
		if(!$user_id)
			return false;
			
		$sqlQuery = "SELECT meta_value FROM $wpdb->usermeta WHERE user_id = '$user_id' AND meta_key = 'wp_capabilities' AND meta_value LIKE '%\"administrator\"%' LIMIT 1";		
		$admincap = $wpdb->get_var($sqlQuery);
		if($admincap)
			return true;
		else
			return false;
	}
	
	function pmpro_replaceUserMeta($user_id, $meta_keys, $meta_values, $prev_values = NULL)
	{
		//expects all arrays for last 3 params or all strings
		if(!is_array($meta_keys))
		{
			$meta_keys = array($meta_keys);
			$meta_values = array($meta_values);
			$prev_values = array($prev_values);
		}
		
		for($i = 0; $i < count($meta_values); $i++)
		{
			if($prev_values[$i])
			{
				update_user_meta($user_id, $meta_keys[$i], $meta_values[$i], $prev_values[$i]);				
			}
			else
			{
				$old_value = get_user_meta($user_id, $meta_keys[$i], true);
				if($old_value)
				{
					update_user_meta($user_id, $meta_keys[$i], $meta_values[$i], $old_value);					
				}
				else
				{
					update_user_meta($user_id, $meta_keys[$i], $meta_values[$i]);				
				}
			}
		}
		
		return $i;
	}
	
	function pmpro_getMetavalues($query)
	{
		global $wpdb;
		
		$results = $wpdb->get_results($query);
		foreach($results as $result)
		{
			$r->{$result->key} = $result->value;
		}
		
		return $r;
	}
	
	//function to return the pagination string
	function pmpro_getPaginationString($page = 1, $totalitems, $limit = 15, $adjacents = 1, $targetpage = "/", $pagestring = "&pn=")
	{		
		//defaults
		if(!$adjacents) $adjacents = 1;
		if(!$limit) $limit = 15;
		if(!$page) $page = 1;
		if(!$targetpage) $targetpage = "/";
		
		//other vars
		$prev = $page - 1;									//previous page is page - 1
		$next = $page + 1;									//next page is page + 1
		$lastpage = ceil($totalitems / $limit);				//lastpage is = total items / items per page, rounded up.
		$lpm1 = $lastpage - 1;								//last page minus 1
		
		/* 
			Now we apply our rules and draw the pagination object. 
			We're actually saving the code to a variable in case we want to draw it more than once.
		*/
		$pagination = "";
		if($lastpage > 1)
		{	
			$pagination .= "<div class=\"pagination\"";
			if($margin || $padding)
			{
				$pagination .= " style=\"";
				if($margin)
					$pagination .= "margin: $margin;";
				if($padding)
					$pagination .= "padding: $padding;";
				$pagination .= "\"";
			}
			$pagination .= ">";

			//previous button
			if ($page > 1) 
				$pagination .= "<a href=\"$targetpage$pagestring$prev\">&laquo; prev</a>";
			else
				$pagination .= "<span class=\"disabled\">&laquo; prev</span>";	
			
			//pages	
			if ($lastpage < 7 + ($adjacents * 2))	//not enough pages to bother breaking it up
			{	
				for ($counter = 1; $counter <= $lastpage; $counter++)
				{
					if ($counter == $page)
						$pagination .= "<span class=\"current\">$counter</span>";
					else
						$pagination .= "<a href=\"" . $targetpage . $pagestring . $counter . "\">$counter</a>";					
				}
			}
			elseif($lastpage >= 7 + ($adjacents * 2))	//enough pages to hide some
			{
				//close to beginning; only hide later pages
				if($page < 1 + ($adjacents * 3))		
				{
					for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++)
					{
						if ($counter == $page)
							$pagination .= "<span class=\"current\">$counter</span>";
						else
							$pagination .= "<a href=\"" . $targetpage . $pagestring . $counter . "\">$counter</a>";					
					}
					$pagination .= "...";
					$pagination .= "<a href=\"" . $targetpage . $pagestring . $lpm1 . "\">$lpm1</a>";
					$pagination .= "<a href=\"" . $targetpage . $pagestring . $lastpage . "\">$lastpage</a>";		
				}
				//in middle; hide some front and some back
				elseif($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2))
				{
					$pagination .= "<a href=\"" . $targetpage . $pagestring . "1\">1</a>";
					$pagination .= "<a href=\"" . $targetpage . $pagestring . "2\">2</a>";
					$pagination .= "...";
					for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
					{
						if ($counter == $page)
							$pagination .= "<span class=\"current\">$counter</span>";
						else
							$pagination .= "<a href=\"" . $targetpage . $pagestring . $counter . "\">$counter</a>";					
					}
					$pagination .= "...";
					$pagination .= "<a href=\"" . $targetpage . $pagestring . $lpm1 . "\">$lpm1</a>";
					$pagination .= "<a href=\"" . $targetpage . $pagestring . $lastpage . "\">$lastpage</a>";		
				}
				//close to end; only hide early pages
				else
				{
					$pagination .= "<a href=\"" . $targetpage . $pagestring . "1\">1</a>";
					$pagination .= "<a href=\"" . $targetpage . $pagestring . "2\">2</a>";
					$pagination .= "...";
					for ($counter = $lastpage - (1 + ($adjacents * 3)); $counter <= $lastpage; $counter++)
					{
						if ($counter == $page)
							$pagination .= "<span class=\"current\">$counter</span>";
						else
							$pagination .= "<a href=\"" . $targetpage . $pagestring . $counter . "\">$counter</a>";					
					}
				}
			}
			
			//next button
			if ($page < $counter - 1) 
				$pagination .= "<a href=\"" . $targetpage . $pagestring . $next . "\">next &raquo;</a>";
			else
				$pagination .= "<span class=\"disabled\">next &raquo;</span>";
			$pagination .= "</div>\n";
		}
		
		return $pagination;

	}
?>