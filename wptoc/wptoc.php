<?php
/*
Plugin Name: Table of Contents
Plugin URI: http://www.evanscode.com/wordpress-table-of-contents-plugin/
Description: Generates a table of contents for the_content().
Version: 1.2.0
Author: Evans Codeworks Inc.
Author URI: http://www.evanscode.com/
*/

/**
 * WPToc is a plugin that generates a Table of Contents structure for a post.
 * The table is based on the structure of html header tags (h1 through h6).
 * The plugin is very flexible in terms of being able to turn on or off
 * toc generation on a page by page basis as well as in templates more broadly.
 * 
 * @author  Laran Evans <laran@evanscode.com>
 * @package WordPress
 * @since   2.5
 **/

// --------------------------------------------------------------------------------
// IF YOU'RE BROWSING THROUGH THIS FILE YOU SHOULD SCROLL TO THE BOTTOM TO SEE
// THE METHODS THAT ARE CALLED FROM WORDPRESS. PRIVATE/UTILITY METHODS ARE ABOVE
// AND THE METHODS THAT YOU'LL USE WITHIN WORDPRESS ARE AT THE BOTTOM OF THIS FILE.
// --------------------------------------------------------------------------------

/**
 * This is the main function used to generate a toc from a block of html.
 * 
 * @access private
 * @param string $html the block of html from which to extract the header (h1-h6) structure
 * @return array a multi-dimensional array structure. You never need to call this method.
 *               So don't worry about it's structure.
 */
function wptoc_toc($html) {
	// Anything between <!-- [wptoc_notoc] --> and <!-- [/wptoc_notoc] --> tags will not be included.
	$toc= wptoc_extract(1,preg_replace('/<!--\s*\[wptoc_notoc\]\s*-->.*?<!--\s*\[\/wptoc_notoc\]\s*-->/is', '', $html));
	$toc= wptoc_linkify($toc);
	$out= array();
	$out['toc']=$toc;
	$out['html']=$html;
	$out['html-with-anchors']=wptoc_anchor($html,$toc);
	return $out;
}

/**
 * @access private
 */
function wptoc_toc_as_ulist($html,$use_links=TRUE) {
	return wptoc_as_ulist(wptoc_toc($html),$use_links);
}

/**
 * @access private
 */
function wptoc_toc_as_olist($html,$use_links=TRUE) {
	return wptoc_as_olist(wptoc_toc($html),$use_links);
}

/**
 * @access private
 */
function wptoc_as_ulist($toc,$use_links=TRUE) {
	// Bail if we can't build a list from $toc
	if(!isset($toc) || 0 == count($toc)) { return ''; }
	
	$o = "<ul>\n";
	foreach($toc as $_toc) {
		$o.="<li>";
		$o.= TRUE === $use_links ? $_toc['link'] : $_toc['text-only'];
		$o.= wptoc_as_ulist($_toc['children'],$use_links);
		$o.="</li>\n";
	}
	$o.= "</ul>\n";
	return $o;
}

/**
 * @access private
 */
function wptoc_as_olist($toc,$use_links=TRUE) {
	// Bail if we can't build a list from $toc
	if(!isset($toc) || 0 == count($toc)) { return ''; }
	
	$o = "<ol>\n";
	foreach($toc as $_toc) {
		$o.="<li>";
		$o.= TRUE === $use_links ? $_toc['link'] : $_toc['text-only'];
		$o.= wptoc_as_olist($_toc['children'],$use_links);
		$o.="</li>\n";
	}
	$o.= "</ol>\n";
	return $o;
}

/**
 * Insert named html anchors (&lt;a name="foo">&lt;/a>) into $html before all headers found
 * in $toc.
 *
 * @access private
 * @param  string $html the html to add anchors to. It's assumed that it's the same html
 *                      that was used to generate $toc. It doesn't have to be. It shouldn't
 *                      cause an problems if it isn't. But the anchors and the links to them
 *                      likely won't make much sense.
 * @param  mixed  $toc the table of contents. It's used to figure out what headers in $html
 *                     need anchors placed in front of them
 * @param  int    $depth this is used within the function as it calls itself recursively
 *                       to allow it to keep track of how deep it is within the tree. You
 *                       never have call this function with any explicit value for $depth.
 * @param  int    $parent_count this is used within the function as it calls itself recursively
 *                       so that it knows what it's parent is doing.
 * @return string $html is returned with named anchors inserted before each header in $toc.
 *                This allows for each header in $html to be linked to from the rendered
 *                table of contents on a page.
 */
function wptoc_anchor($html, $toc, $depth=0, $parent_count=0) {
	if(!isset($toc) || 0 == count($toc)) { return $html; }
	$count=0;
	foreach($toc as $t) {
		$pattern = "(".preg_quote($t['original']).")";
		$replacement = "<a name=\"wptoc_${parent_count}_${depth}_${count}\"></a>$0";
		$html = preg_replace($pattern,$replacement,$html);
		$html = wptoc_anchor($html,$t['children'],$depth+1,$count);
		$count+= 1;
	}
	return $html;
}

/**
 * This is the main parsing function. It extracts the header (h1-h6) structure from a block of html.
 * It calls itself recursively to build out the tree/multi-dimensional-array structure.
 *
 * @access public
 * @param  int $hnum the h-number to look for (for h1-h6 the h-number is 1-6)
 * @param  string $html the text to scan for headers with h-number == $hnum
 * @return array a multi-dimensional associative array, basically a tree-structure.
 *               Each element maps to a h1-h6 element within $html.
 *               Each element at each level within the array has three keys,
 *               'original', 'text-only' and 'children'.
 *               'original' is the actual h1-h6 element as it is in $html.
 *                   It contains everything from the opening tag (h1-h6) through the closing tag (h1-h6) inclusive.
 *                   Example: &lt;h1 style="color: red;" class="myheader" id="head1">Hello World!&lt;/h1>
 *               'text-only' is everything between the opening and closing tags in the 'original' value.
 *                   Example:
 *                       if 'original' is:
 *                           &lt;h1 style="color: red;" class="myheader" id="head1">Hello World!&lt;/h1>
 *                       then 'text-only' will be:
 *                           Hello World!
 *               'children' is an array of all of the lesser (meaning the h-number is greater than the current element)
 *                   header elements between the current element and either 1) the next element with an h-number
 *                   the same as the current element or 2) the end of $html is no other elements with the same h-humber
 *                   as the current element exist. Each element within 'children' has the same structure
 *                   (original, text-only and children)
 */
function wptoc_extract($hnum, $html) {
	// Skip text in HTML comments.
	$html = preg_replace('/<!--(.|\s)*?-->/', '', $html);
	
	$kids = array();
	$out  = array();
	
	// Find all child nodes
	preg_match_all("/(<h${hnum}[^>]*>)(.*)(<\/h${hnum}>)/i", $html, $kids, PREG_SET_ORDER);
	$numkids = count($kids);
	if(0 < count($kids)) { // if we found kids
		$offset= 0;
		for($i=0; $i < $numkids; $i++) {
			$heading = $kids[$i][0];
			$start = strpos($html, $heading, $offset)+strlen($heading);
			if($i < $numkids-1) { // not last element
				// We want to look only at elements that occur after the current tag.
				$chunk = substr($html, $start, strpos($html, $kids[$i+1][0], $start)-$start);
			} else { // last element
				$chunk = substr($html, $start);
			}
			$_out = array();
			$_out['original']  = $kids[$i][0];
			$_out['text-only'] = preg_replace('/<[^>]*>/', '', $kids[$i][2]); // Remove all html tags from the text.
			$_out['children']  = wptoc_extract($hnum+1, $chunk);
			$out[]= $_out;
		}
	} else if ($hnum < 6) {
		// If no children were found, look for children of lower classes.
		// For instance, if $hnum is 1 and no <h2> elements were found, 
		// look for <h3> elements and return those. Do this recursively,
		// stopping at <h6>.
		$out = wptoc_extract($hnum+1, $html);
	}
	return $out;
}

/**
 *
 */
function wptoc_linkify($toc,$depth=0,$parent_count=0) {
	if(!isset($toc) || 0 == count($toc)) { return; }
	$count=0;
	for($i=0;$i<count($toc);$i++) {
		$toc[$i]['link']= "<a href=\"#wptoc_${parent_count}_${depth}_${count}\" title=\"";
		$toc[$i]['link'].=preg_replace('/"/',"&quot;",$toc[$i]['text-only']);
		$toc[$i]['link'].="\">".$toc[$i]['text-only']."</a>";
		$toc[$i]['children'] = wptoc_linkify($toc[$i]['children'],$depth+1,$count);
		$count+=1;
	}
	return $toc;
}

// ------------------------------------------------------------------------------
// ALL METHODS BELOW WILL WORK ONLY IN A WORDPRESS ENVIRONMENT. THEY WILL NOT 
// WORK IF EXECUTING THIS PLUGIN FROM A COMMAND LINE, OR OTHERWISE "JUST TESTING"
// THE PLUGIN.
// ------------------------------------------------------------------------------

/**
 * This is the method that does the actual generation of the table of contents
 * in the wordpress environment. This method is what is hooked to the 'the_content'
 * hook in the WordPress lifecycle.
 *
 * If $content contains the string &lt;!-- [wptoc_disable] --> no toc will be generated
 * and subsequent calls to wptoc_has_toc() will return FALSE.
 *
 * @access public
 * @param string $content The content from which to generate a toc.
 */
function wptoc_filter($content) {
	global $toc;
	// If wptoc is explicitly disabled, do no filtering. Don't alter $html
	// and don't set $toc. 
	$pattern = "/<!--\s*\[wptoc_disable\]\s*-->/is";
	if(0 != preg_match($pattern, $content)) {
		unset($toc);
		return preg_replace($pattern, '', $content);
	}
	$_toc = wptoc_toc($content);
	$toc = $_toc['toc'];
	return $_toc['html-with-anchors'];
}

/**
 * This method will render the table of contents as an html &lt;ol> element.
 *
 * @access public
 * @param boolean $linkify When TRUE, each element in the toc will link to a named anchor
 *                for the appropriate header element (h1-h6) on the same page.
 */
function wptoc_show_toc_as_olist($linkify=TRUE) {
	global $toc;
	if(isset($toc)) {
		echo wptoc_as_olist($toc,$linkify);
	}
}

/**
 * This method will render the table of contents as an html &lt;ul> element.
 *
 * @access public
 * @param boolean $linkify When TRUE, each element in the toc will link to a named anchor
 *                for the appropriate header element (h1-h6) on the same page.
 */
function wptoc_show_toc_as_ulist($linkify=TRUE) {
	global $toc;
	if(isset($toc)) {
		echo wptoc_as_ulist($toc,$linkify);
	}
}

/**
 * This tells you whether or not a table of contents was parsed from the_content().
 * You can use this method in a WordPress template to hide a block of html if no toc 
 * will be rendered.
 * 
 * @access public
 * @return boolean TRUE if $toc is set. FALSE otherwise.
 */
function wptoc_has_toc() {
	global $toc;
	return isset($toc);
}

/**
 * Enable toc functionality. This adds a filter to the 'the_content' method in WordPress.
 * 
 * @access public
 */
function wptoc_enable() {
	add_filter('the_content', 'wptoc_filter');
}

/**
 * Disable toc functionality. This removes the filter added by wptoc_enable().
 * 
 * @access public
 */
function wptoc_disable() {
	remove_filter('the_content', 'wptoc_filter');
}

// If you want to enable the TOC functionality on all your content, enable it by default.
wptoc_enable();

// If you want to disable the TOC functionality by default, and manually enable it only on certain templates,
// for example on pages but not on posts, disable it by default (uncomment the following line);
// wptoc_disable();

?>
