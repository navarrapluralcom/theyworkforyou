<?php
# vim:sw=4:ts=4:et:nowrap

include_once "../../includes/easyparliament/init.php";
include_once INCLUDESPATH."easyparliament/member.php";
include_once INCLUDESPATH."easyparliament/glossary.php";

// From http://cvs.sourceforge.net/viewcvs.py/publicwhip/publicwhip/website/
include_once INCLUDESPATH."postcode.inc";

if (get_http_var('pid') == 16407) {
    header('Location: /search/?pid=10133');
    exit;
}

$searchstring = construct_search_string();
twfy_debug('SEARCH', $searchstring);

$this_page = 'search';

$warning = '';
if (preg_match('#^\s*[^\s]+\.\.[^\s]+\s*$#', $searchstring)) {
    $warning = 'You cannot search for just a date range, please select some other criteria as well.';
}
if (preg_match('#\.\..*?\.\.#', $searchstring)) {
    $warning = 'You cannot search for more than one date range.';
}

if (get_http_var('adv') || $warning) {
	$PAGE->page_start();
	$PAGE->stripe_start();
    if ($warning) echo "<p id='warning'>$warning</p>";
    $PAGE->advanced_search_form();
    $PAGE->stripe_end(array(
	    array(
		    'type'		=> 'include',
		    'content'	=> 'search'
	    )
    ));
} elseif ($searchstring) {
	// We're searching for something.
	$searchstring = filter_user_input($searchstring, 'strict');
    $searchstring2 = trim(preg_replace('#[a-z]+:[a-z0-9]+#', '', $searchstring));
    $time = parse_date($searchstring2);
    if ($time['iso']) {
        header('Location: /hansard/?d=' . $time['iso']);
        exit;
    }

    // We have only one of these, rather than one in HANSARDLIST also
    global $SEARCHENGINE;

    if (get_http_var('o')=='p') {
        $q_house = '';
        if (ctype_digit(get_http_var('house')))
            $q_house = get_http_var('house');

        # Fetch the results
        $data = search_by_usage($searchstring, $q_house);

        $wtt = get_http_var('wtt');
        if ($wtt) {
            $pagetitle = 'League table of Lords who say ' . $data['pagetitle'];
        } else {
            $pagetitle = 'Who says ' . $data['pagetitle'] . ' the most?';
        }
        $DATA->set_page_metadata($this_page, 'title', $pagetitle);
        $PAGE->page_start();
        $PAGE->stripe_start();
    	$PAGE->search_form($searchstring);
        if (isset($data['error'])) {
            print '<p>' . $data['error'] . '</p>';
            $PAGE->stripe_end();
            $PAGE->page_end();
            return;
        }

        if (isset($data['limit_reached'])) {
            print '<p><em>This service runs on a maximum number of 5,000 results, to conserve memory</em></p>';
        }
        print "\n\n<!-- ";
        foreach ($data['party_count'] as $party => $count) {
            print "$party:$count<br>";
        }
        print " -->\n\n";
        if ($wtt) { ?>
<p><strong><big>Now, try reading what a couple of these Lords are saying,
to help you find someone appropriate. When you've found someone,
hit the "I want to write to this Lord" button on their results page
to go back to WriteToThem.
</big></strong></p>
<?      }
?>
<p>Please note that this search is only for the exact word/phrase entered.
For example, putting in 'autism' won't return results for 'autistic spectrum disorder',
you will have to search for it separately.</p>
<table><tr><th>Number of occurences</th><th><?

if ($wtt) print 'Speaker';
else { ?>Table includes - <?

$URL = new URL($this_page);
$url_l = $URL->generate('html', array('house'=>2));
$url_c = $URL->generate('html', array('house'=>1));
$URL->remove(array('house'));
$url_b = $URL->generate();
if ($q_house==1) {
    print 'MPs | <a href="' . $url_l . '">Lords</a> | <a href="' . $url_b . '">Both</a>';
} elseif ($q_house==2) {
    print '<a href="' . $url_c . '">MPs</a> | Lords | <a href="' . $url_b . '">Both</a>';
} else {
    print '<a href="' . $url_c . '">MPs</a> | <a href="' . $url_l . '">Lords</a> | Both';
}

} ?></th><th>Date range</th></tr>
<?
        foreach ($data['speakers'] as $pid => $speaker) {
            print '<tr><td align="center">';
            print $speaker['count'] . '</td><td>';
            if ($pid) {
                $house = $speaker['house'];
                $left = $speaker['left'];
                if ($house==1) {
                    print '<span style="color:#009900">&bull;</span> ';
                } elseif ($house==2) {
                    print '<span style="color:#990000">&bull;</span> ';
                }
                if (!$wtt || $left == '9999-12-31')
                    print '<a href="' . WEBPATH . 'search/?s='.urlencode($searchstring).'&amp;pid=' . $pid;
                if ($wtt && $left == '9999-12-31')
                    print '&amp;wtt=2';
                if (!$wtt || $left == '9999-12-31')
                    print '">';
            }
            print $speaker['name'];
            if ($pid) print '</a>';
            if ($speaker['party']) print ' (' . $speaker['party'] . ')';
            if (isset($speaker['office']))
                print ' - ' . join('; ', $speaker['office']);
            print '</td> <td>';
            $pmindate = $speaker['pmindate'];
            $pmaxdate = $speaker['pmaxdate'];
            if (format_date($pmindate, 'M Y') == format_date($pmaxdate, 'M Y')) {
                print format_date($pmindate, 'M Y');
            } else {
                print str_replace(' ', '&nbsp;', format_date($pmindate, 'M Y') . ' &ndash; ' . format_date($pmaxdate, 'M Y'));
            }
            print '</td></tr>';
        }
        print '</table>';
/*    } elseif (get_http_var('o') == 't') {
        $SEARCHENGINE = new SEARCHENGINE($searchstring);
        $pagetitle = $SEARCHENGINE->query_description_short();
        $pagetitle = 'When is ' . $pagetitle . ' said most in debates?';
        $DATA->set_page_metadata($this_page, 'title', $pagetitle);
        $PAGE->page_start();
        $PAGE->stripe_start();
    	$PAGE->search_form($searchstring);
        $SEARCHENGINE = new SEARCHENGINE($searchstring . ' groupby:speech section:debates section:whall');
        $count = $SEARCHENGINE->run_count();
        if ($count <= 0) {
            print '<p>There were no results.</p>';
            $PAGE->page_end();
            return;
        }
        $sort_order = 'date';
        $SEARCHENGINE->run_search(0, 10000, 'date');
        $gids = $SEARCHENGINE->get_gids();
        if (count($gids) <= 0) {
            print '<p>There were no results.</p>';
            $PAGE->page_end();
            return;
        }

        $hdates = array();
        $big_list = join('","', $gids);
        $db = new ParlDB;
        $q = $db->query('SELECT hdate FROM hansard WHERE gid IN ("' . $big_list . '")');
        print '<!-- Counts: ' . count($gids) . ' vs ' . $q->rows() . ' -->';
        for ($n=0; $n<$q->rows(); $n++) {
            $hdate = $q->field($n, 'hdate');
            if (!isset($hdates[$hdate]))
                $hdates[$hdate] = 0;
            $hdates[$hdate]++;
        }
        arsort($hdates);
        print '<table><tr><th>No.</th><th>Date</th></tr>';
        foreach ($hdates as $hdate => $count) {
            print '<tr><td>';
            print $count . '</td><td>';
            print '<a href="' . WEBPATH . 'hansard/?d=' . $hdate . '">';
            print $hdate;
            print '</a>';
            print '</td></tr>';
        }
        print '</table>';
*/
    } else {


        $SEARCHENGINE = new SEARCHENGINE($searchstring); 
    	$pagetitle = 'Search for ' . $SEARCHENGINE->query_description_short();
    	$pagenum = get_http_var('p');
    	if (is_numeric($pagenum) && $pagenum > 1) {
    		$pagetitle .= ", page $pagenum";
    	}
	
    	$DATA->set_page_metadata($this_page, 'title', $pagetitle);
	    $DATA->set_page_metadata($this_page, 'rss', 'search/rss/?s=' . urlencode($searchstring));
    	$PAGE->page_start();
    	$PAGE->stripe_start();
    	$PAGE->search_form($searchstring);
	
        $o = get_http_var('o');
    	$args = array (
    		's' => $searchstring,
    		'p' => $pagenum,
    		'num' => get_http_var('num'),
            'pop' => get_http_var('pop'),
            'o' => ($o=='d' || $o=='r' || $o=='o') ? $o : 'd',
    	);
	
    	$LIST = new HANSARDLIST();

        if ($args['s']) {
    	    find_members($args['s']);
            find_constituency($args);
        }

        $LIST->display('search', $args);
	
        if ($args['s']) {
            #        find_users($args);
        	find_glossary_items($args);
            #        find_comments($args);
    	}
    }
    $PAGE->stripe_end(array (
        array(
            'type'		=> 'include',
            'content'	=> 'search_links'
        ),
        array(
            'type'		=> 'include',
            'content'	=> 'search_filters'
        ),
        array(
            'type'		=> 'include',
            'content'	=> 'search'
        )
    ));
} else {
	// No search term. Display help.
	#$this_page = 'search_help';
	$PAGE->page_start();
	$PAGE->stripe_start();
    $PAGE->advanced_search_form();
    $PAGE->stripe_end(array(
	    array(
		    'type'		=> 'include',
		    'content'	=> 'search'
	    )
    ));
}

$PAGE->page_end();

function find_comments($args) {
    $commentlist = new COMMENTLIST;    
    $commentlist->display('search', $args);
}

function find_constituency ($args) {
	// We see if the user is searching for a postcode or constituency.
	global $PAGE;

	if ($args['s'] != '') {
        $searchterm = $args['s'];
    } else {
        $PAGE->error_message('No search string');
        return false;
    }

	$constituencies = array();
    $constituency = '';
	$validpostcode = false;

	if (validate_postcode($searchterm)) {
		// Looks like a postcode - can we find the constituency?
		$constituency = postcode_to_constituency($searchterm);
        if ($constituency != '') {
            $validpostcode = true;
        }
	}

	if ($constituency == '' && $searchterm) {
		// No luck so far - let's see if they're searching for a constituency.
		$try = strtolower($searchterm);
		if (normalise_constituency_name($try)) {
			$constituency = normalise_constituency_name($try);
		} else {
            $query = "select distinct
                    (select name from constituency where cons_id = o.cons_id and main_name) as name 
                from constituency AS o where name like '%" . mysql_escape_string($try) . "%'
                and from_date <= date(now()) and date(now()) <= to_date";
            $db = new ParlDB;
            $q = $db->query($query);
            for ($n=0; $n<$q->rows(); $n++) {
                $constituencies[] = $q->field($n, 'name');
            }
        }
	}

    if (count($constituencies)==1) {
        $constituency = $constituencies[0];
    }

	if ($constituency != '') {
		// Got a match, display....
			
		$MEMBER = new MEMBER(array('constituency'=>$constituency));
        $URL = new URL('mp');
        if ($MEMBER->valid) {
            $URL->insert(array('m'=>$MEMBER->member_id()));
            print '<h3>MP for ' . preg_replace("#$searchterm#i", '<span class="hi">$0</span>', $constituency);
            if ($validpostcode) {
                // Display the postcode the user searched for.
                print ' (' . htmlentities(strtoupper($args['s'])) . ')';
            }
            ?></h3>
            
            <p><a href="<?php echo $URL->generate(); ?>"><strong><?php echo $MEMBER->full_name(); ?></strong></a> (<?php echo $MEMBER->party(); ?>)</p>
    <?php
        }

	} elseif (count($constituencies)) {
        print "<h3>MPs in constituencies matching '".htmlentities($searchterm)."'</h3><ul>";
        foreach ($constituencies as $constituency) {
            $MEMBER = new MEMBER(array('constituency'=>$constituency));
            $URL = new URL('mp');
            if ($MEMBER->valid) {
                $URL->insert(array('m'=>$MEMBER->member_id()));
            }
            print '<li><a href="'.$URL->generate().'"><strong>' . $MEMBER->full_name() .
                '</strong></a> (' . preg_replace("#$searchterm#i", '<span class="hi">$0</span>', $constituency) .
                ', '.$MEMBER->party().')</li>';
        }
        print '</ul>';
    }
}

function find_users ($args) {
	// Maybe there'll be a better place to put this at some point...
	global $PAGE;

	if ($args['s'] != '') {
		// $args['s'] should have been tidied up by the time we get here.
		// eg, by doing filter_user_input($s, 'strict');
		$searchstring = $args['s'];
	} else {
		$PAGE->error_message("No search string");
		return false;
	}
	
	$searchwords = explode(' ', $searchstring);
	
	if (count($searchwords) == 1) {
		$where = "(firstname LIKE '%" . addslashes($searchwords[0]) . "%' OR lastname LIKE '%" . addslashes($searchwords[0]) . "%')";
	} else {
		// We don't do anything special if there are more than two search words.
		// And here we're assuming the user's put the names in the right order.
		$where = "(firstname LIKE '%" . addslashes($searchwords[0]) . "%' AND lastname LIKE '%" . addslashes($searchwords[1]) . "%')";
	}

    $db = new ParlDB;
	$q = $db->query("SELECT user_id,
							firstname,
							lastname
					FROM 	users
					WHERE	$where AND confirmed=1
					ORDER BY lastname, firstname, user_id
					");

	if ($q->rows() > 0) {
	
		$URL = new URL('userview');
		$users = array();
		
		for ($n=0; $n<$q->rows(); $n++) {
            $URL->insert(array('u'=>$q->field($n, 'user_id')));
            $members[] = '<a href="' . $URL->generate() . '">' . $q->field($n, 'firstname') . ' ' . $q->field($n, 'lastname') . '</a>';
		}
		?>
	<h3>Users matching '<?php echo htmlentities($searchstring); ?>'</h3> 
	<ul>
	<li><?php print implode("</li>\n\t<li>", $members); ?></li>
	</ul>
<?php	
	}
	
	// We don't display anything if there were no matches.

}

function member_db_lookup($searchstring) {
	$searchstring = trim(preg_replace("#[^0-9a-z'& ]#i", '', $searchstring));
	if (!$searchstring) return false;
	$searchwords = explode(' ', $searchstring, 3);
    foreach ($searchwords as $i => $searchword) {
        $searchwords[$i] = mysql_real_escape_string(htmlentities($searchword));
        if (!strcasecmp($searchword,'Opik'))
            $searchwords[$i] = '&Ouml;pik';
    }
	if (count($searchwords) == 1) {
		$where = "first_name LIKE '%" . $searchwords[0] . "%' OR last_name LIKE '%" . $searchwords[0] . "%'";
	} elseif (count($searchwords) == 2) {
		// We don't do anything special if there are more than two search words.
		// And here we're assuming the user's put the names in the right order.
		$where = "(first_name LIKE '%" . $searchwords[0] . "%' AND last_name LIKE '%" . $searchwords[1] . "%')";
        $where .= " OR (first_name LIKE '%" . $searchwords[1] . "%' AND last_name LIKE '%" . $searchwords[0] . "%')";
		$where .= " OR (title LIKE '%" . $searchwords[0] . "%' AND last_name LIKE '%" . $searchwords[1] . "%')";
	} else {
        $searchwords[2] = str_replace('of ', '', $searchwords[2]);
		$where = "(first_name LIKE '%" . $searchwords[0].' '.$searchwords[1] . "%' AND last_name LIKE '%" . $searchwords[2] . "%')";
        $where .= " OR (first_name LIKE '%" . $searchwords[0] . "%' AND last_name LIKE '%" . $searchwords[1].' '.$searchwords[2] . "%')";
		$where .= " OR (title LIKE '%" . $searchwords[0] . "%' AND first_name LIKE '%". $searchwords[1] . "%' AND last_name LIKE '%" . $searchwords[2] . "%')";
		$where .= " OR (title LIKE '%" . $searchwords[0] . "%' AND last_name LIKE '%". $searchwords[1] . "%' AND constituency LIKE '%" . $searchwords[2] . "%')";
    }

    $db = new ParlDB;
	$q = $db->query("SELECT person_id,
                            title, first_name, last_name,
							constituency, party,
                            entered_house, left_house, house
					FROM 	member
					WHERE	($where)
					ORDER BY last_name, first_name, person_id, entered_house desc
					");
    return $q;
}

function find_members ($searchstring) {
	// Maybe there'll be a better place to put this at some point...
	global $PAGE, $parties;
	
	if (!$searchstring) {
		$PAGE->error_message("No search string");
		return false;
	}

    $searchstring = trim(preg_replace('#[a-z]+:[a-z0-9]+#', '', $searchstring));
    $q = member_db_lookup($searchstring);
	if (!$q) return false;

	if ($q->rows() > 0) {
	
		$URL1 = new URL('mp');
		$URL2 = new URL('peer');
		$members = array();
		
        $last_pid = null;
        $entered_house = '';
		for ($n=0; $n<$q->rows(); $n++) {
            if ($q->field($n, 'person_id') != $last_pid) {
                # First, stick the oldest entered house from last PID on to its end!
                if ($entered_house)
                    $members[count($members)-1][1] = format_date($entered_house, SHORTDATEFORMAT) . $members[count($members)-1][1];
                $last_pid = $q->field($n, 'person_id');
                if ($q->field($n, 'left_house') != '9999-12-31') {
                    $former = 'formerly ';
                } else {
                    $former = '';
                }
                $name = member_full_name($q->field($n, 'house'), $q->field($n, 'title'), $q->field($n, 'first_name'), $q->field($n, 'last_name'), $q->field($n, 'constituency') );
                if ($q->field($n, 'house') == 1) {
                    $URL1->insert(array('pid'=>$last_pid));
                    $s = '<a href="' . $URL1->generate() . '"><strong>';
                    $s .= $name . '</strong></a> (' . $former . $q->field($n, 'constituency') . ', ';
                } else {
                    $URL2->insert(array('pid'=>$last_pid));
                    $s = '<a href="' . $URL2->generate() . '"><strong>' . $name . '</strong></a> (';
                }
                $party = $q->field($n, 'party');
                if (isset($parties[$party]))
                    $party = $parties[$party];
                if ($party)
                    $s .= $party . ', ';
                $s2 = ' &ndash; ';
                if ($q->field($n, 'left_house') != '9999-12-31')
                   $s2 .= format_date($q->field($n, 'left_house'), SHORTDATEFORMAT);
		        $MOREURL = new URL('search');
                $MOREURL->insert( array('pid'=>$last_pid, 'pop'=>1, 's'=>null) );
                $s3 = ') &ndash; <a href="' . $MOREURL->generate() . '">View recent appearances</a>';
                $members[] = array($s, $s2, $s3);
            }
            $entered_house = $q->field($n, 'entered_house');
		}
        if ($entered_house)
            $members[count($members)-1][1] = format_date($entered_house, SHORTDATEFORMAT) . $members[count($members)-1][1];
		?>
<div id="people_results">
	<h3>People matching &lsquo;<?php echo htmlentities($searchstring); ?>&rsquo;</h3> 
	<ul class="hilites">
<?
foreach ($members as $member) {
    echo '<li>';
    echo $member[0] . $member[1] . $member[2];
    echo "</li>\n";
}
?>
	</ul>
</div>
<?php	
	}
	
	// We don't display anything if there were no matches.

}

// Checks to see if the search term provided has any similar matching entries in the glossary.
// If it does, show links off to them.
function find_glossary_items($args) {
	
	$searchterm = $args['s'];
	$GLOSSARY = new GLOSSARY($args);

	if (isset($GLOSSARY->num_search_matches) && $GLOSSARY->num_search_matches >= 1) {

		// Got a match(es), display....
		$URL = new URL('glossary');
		$URL->insert(array('gl' => ""));
		
		?>
				<h3>Matching glossary terms:</h3> 
				<p><?
		$n = 1;
		foreach($GLOSSARY->search_matches as $glossary_id => $term) {
			$URL->update(array("gl" => $glossary_id)); 
			?><a href="<?php echo $URL->generate(); ?>"><strong><?php echo htmlentities($term['title']); ?></strong></a><?
			if ($n < $GLOSSARY->num_search_matches) {
				print ", ";
			}
			$n++;
		}
		?></p>
<?
	}
}

function construct_search_string() {
    $searchstring = trim(get_http_var('s'));

    # Stuff from advanced search form
    if ($advphrase = get_http_var('phrase')) {
        $searchstring .= ' "' . $advphrase . '"';
    }

    if ($advexclude = get_http_var('exclude')) {
        $searchstring .= ' -' . join(' -', preg_split('/\s+/', $advexclude));
    }

    if (get_http_var('from') || get_http_var('to')) {
        $from = parse_date(get_http_var('from'));
        if ($from) $from = $from['iso'];
        else $from = '1935-10-01';
        $to = parse_date(get_http_var('to'));
        if ($to) $to = $to['iso'];
        else $to = date('Y-m-d');
        $searchstring .= " $from..$to";
    }

    if ($advdept = get_http_var('department')) {
        $searchstring .= ' department:' . preg_replace('#[^a-z]#i', '', $advdept);
    }

    if ($advparty = get_http_var('party')) {
        $searchstring .= ' party:' . join(' party:', explode(',', $advparty));
    }

    if ($column = trim(get_http_var('column'))) {
        if (preg_match('#^(\d+)W$#', $column, $m)) {
            $searchstring .= " column:$m[1] section:wrans";
        } elseif (preg_match('#^(\d+)WH$#', $column, $m)) {
            $searchstring .= " column:$m[1] section:whall";
        } elseif (preg_match('#^(\d+)WS$#', $column, $m)) {
            $searchstring .= " column:$m[1] section:wms";
        } elseif (preg_match('#^\d+$#', $column)) {
            $searchstring .= " column:$column";
        }
    }

    $advsection = get_http_var('section');
    if (!$advsection)
        $advsection = get_http_var('maj'); # Old URLs had this
    if (is_array($advsection)) {
        $searchstring .= ' section:' . join(' section:', $advsection);
    } elseif ($advsection) {
        $searchstring .= " section:$advsection";
    }

    if ($searchgroupby = trim(get_http_var('groupby'))) {
        $searchstring .= " groupby:$searchgroupby";
    }

    # Searching from MP pages
	if ($searchspeaker = trim(get_http_var('pid'))) {
		$searchstring .= " speaker:$searchspeaker";
    }

    # Searching from MP pages
	if ($searchspeaker = trim(get_http_var('person'))) {
        $q = member_db_lookup($searchspeaker);
        $pids = array();
        for ($i=0; $i<$q->rows(); $i++) {
            $pids[$q->field($i, 'person_id')] = true;
        }
        $pids = array_keys($pids);
        if ($pids)
            $searchstring .= ' speaker:' . join(' speaker:', $pids);
    }

    return trim($searchstring);
}

