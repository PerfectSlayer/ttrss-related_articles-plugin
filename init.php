<?php
// Load dependencies
require_once 'colors.php';

/**
 * This class is a plugin that displays related article when reading one.
 * @author PerfectSlayer (bruce.bujon@gmail.com)
 */
class Related_Articles extends Plugin {
	/** The plugin host. */
	private $host;

	/**
	 * Initialize the plugin.
	 * @param	host	The plugin host.
	 */
	function init($host) {
		// Save plugin host
		$this->host = $host;
		// Register plugin hooks
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
		$host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);
	}

	/**
	 * Get the plugin API version.
	 * @return The plugin API version.
	 */
	function api_version() {
		return 2;
	}

	/**
	 * Get the JavaScript file of the plugin.
	 */
	function get_js() {
		return file_get_contents(__DIR__ . '/init.js');
	}

	/**
	 * Get the CSS file of the plugin.
	 */
	function get_css() {
		return file_get_contents(__DIR__ . '/init.css');
	}

	/**
	 * Get the plugin properties.
	 * @return An array of plugin properties.
	 */
	function about() {
		return array(1.0,
			'Find related articles',
			'PerfectSlayer',
			true);
	}

	/*
	 * Methods called from JS by plugin handler.
	 */

	/**
 	 * Save plugin settings.
 	 * Called from JS by plugin handler.
 	 */
	function save() {
		$similarity = (float) db_escape_string($_POST['similarity']);
		$min_title_length = (int) db_escape_string($_POST['min_title_length']);
		$enable_globally = checkbox_to_sql_bool($_POST['enable_globally']) == 'true';

		if ($similarity < 0) $similarity = 0;
		// if ($similarity > 1) $similarity = 1;

		if ($min_title_length < 0) $min_title_length = 0;

		$similarity = sprintf('%.2f', $similarity);

		$this->host->set($this, 'similarity', $similarity);
		$this->host->set($this, 'min_title_length', $min_title_length);
		$this->host->set($this, 'enable_globally', $enable_globally);

		echo T_sprintf('Data saved (%s, %d)', $similarity, $enable_globally);
	}

	/**
	 * Get related articles.
	 * Called from JS by plugin handler.
	 */
	function showrelated() {
		/*
		 * Retrieve article title.
		 */
		// Get the user identifier
		$uid = $_SESSION['uid'];
		// Get the article identifier
		$id = $_REQUEST['param'];
		// Query article title
		$title_statement = $this->pdo->prepare(
			'SELECT
				title
			FROM
				ttrss_entries, ttrss_user_entries
			WHERE 
				ref_id = id AND
				id = :id AND
				owner_uid = :uid'
		);
		$title_statement->bindValue(':id', $id, PDO::PARAM_INT);
		$title_statement->bindValue(':uid', $uid, PDO::PARAM_INT);
		$title_statement->execute();
		// Fetch article title
		$title = $title_statement->fetchColumn();
		/*
		 * Get related articles.
		 */
		// Get similarity
		$similarity = $this->host->get($this, 'similarity');
		// Check if similarity is define
		if (!$similarity) {
			// Apply default similarity
			$similarity = '5';
		}
		$entries_statement = $this->pdo->prepare(
			'SELECT 
				ttrss_entries.id AS id,
				ttrss_entries.link AS link,
				ttrss_entries.updated AS updated,
				ttrss_entries.title AS title,
				ttrss_user_entries.unread AS unread,
				ttrss_feeds.id AS feed_id,
				ttrss_feeds.title AS feed_name,
				ttrss_feeds.favicon_avg_color AS feed_color,
				MATCH(ttrss_entries.title, ttrss_entries.content) AGAINST(\'\':title1\'\' IN NATURAL LANGUAGE MODE) AS score
			FROM
				ttrss_entries, ttrss_user_entries LEFT JOIN ttrss_feeds ON (ttrss_feeds.id = ttrss_user_entries.feed_id)
			WHERE
				ttrss_user_entries.unread = 1 AND
				MATCH(ttrss_entries.title, ttrss_entries.content) AGAINST(\'\':title2\'\' IN NATURAL LANGUAGE MODE) > :similarity AND
				ttrss_entries.id != :id AND
				ttrss_entries.id = ttrss_user_entries.ref_id AND
				ttrss_user_entries.owner_uid = :uid
			ORDER BY
				score DESC
			LIMIT 7'
		);
		$entries_statement->bindValue(':id', $id, PDO::PARAM_INT);
		$entries_statement->bindValue(':uid', $uid, PDO::PARAM_INT);
		$entries_statement->bindValue(':title1', $title, PDO::PARAM_STR);
		$entries_statement->bindValue(':title2', $title, PDO::PARAM_STR);
		$entries_statement->bindValue(':similarity', $similarity, PDO::PARAM_STR);
		$entries_statement->execute();
		// Declare related articles
		$related_articles = array();
		// Fetch related articles
		while ($entry = $entries_statement->fetch(PDO::FETCH_ASSOC)) {
			// Check if max score is defined
			if (!$max_score) {
				// Save max score
				$max_score = $entry['score'];
				$score_type = 'high';
			} else {
				// Compute related score
				$relative_score = ($entry['score'] - $similarity) / $max_score;
				// Get related score type
				if ($relative_score < 0.05) {
					$score_type = 'low';
				} else if ($relative_score < 0.1) {
					$score_type = 'half_low';
				} else if ($relative_score < 0.25) {
					$score_type = 'half_high';
				} else {
					$score_type = 'high';
				}
			}
			// Create related article
			$related_article = array(
				'feed_id' => $entry['feed_id'],
				'feed_name' => htmlspecialchars($entry['feed_name']),
				'feed_color' => 'rgba(' . join(',', _color_unpack($entry['feed_color'])) . ', 0.3)',
				'id' => $entry['id'],
				'link' => htmlspecialchars($entry['link']),
				'title' => htmlspecialchars($entry['title']),
				'date_time' => smart_date_time(strtotime($entry['updated'])),
				'score_type' => $score_type,
				'score' =>  sprintf('%.2f', $entry['score']),
				'unread' => $entry['unread']
			);
			// Append related article
			array_push($related_articles, $related_article);
		}
		// Print JSON encoded related articles
		print json_encode($related_articles);
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Mark similar articles as read')."\">";

		/*if (DB_TYPE != "pgsql") {
			print_error("Database type not supported.");
		}*/

		/*$result = db_query("select 'similarity'::regproc");

		if (db_num_rows($result) == 0) {
			print_error("pg_trgm extension not found.");
		}*/

		$similarity = $this->host->get($this, "similarity");
		$min_title_length = $this->host->get($this, "min_title_length");
		$enable_globally = $this->host->get($this, "enable_globally");

		if (!$similarity) $similarity = '5';
		if (!$min_title_length) $min_title_length = '32';

		$enable_globally_checked = $enable_globally ? "checked" : "";

		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						notify_info(transport.responseText);
					}
				});
				//this.reset();
			}
			</script>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"related_articles\">";

		print "<p>" . __("PostgreSQL trigram extension returns string similarity as a floating point number (0-1). Setting it too low might produce false positives, zero disables checking.") . "</p>";
		print_notice("Enable the plugin for specific feeds in the feed editor.");

		print "<h3>" . __("Global settings") . "</h3>";

		print "<table>";

		print "<tr><td width=\"40%\">".__("Minimum similarity:")."</td>";
		print "<td>
			<input dojoType=\"dijit.form.ValidationTextBox\"
			placeholder=\"0.75\"
			required=\"1\" name=\"similarity\" value=\"$similarity\"></td></tr>";
		print "<tr><td width=\"40%\">".__("Minimum title length:")."</td>";
		print "<td>
			<input dojoType=\"dijit.form.ValidationTextBox\"
			placeholder=\"32\"
			required=\"1\" name=\"min_title_length\" value=\"$min_title_length\"></td></tr>";
		print "<tr><td width=\"40%\">".__("Enable for all feeds:")."</td>";
		print "<td>
			<input dojoType=\"dijit.form.CheckBox\"
			$enable_globally_checked name=\"enable_globally\"></td></tr>";

		print "</table>";

		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
			__("Save")."</button>";

		print "</form>";

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!array($enabled_feeds)) $enabled_feeds = array();

		$enabled_feeds = array(); // $this->filter_unknown_feeds($enabled_feeds);
		$this->host->set($this, "enabled_feeds", $enabled_feeds);

		if (count($enabled_feeds) > 0) {
			print "<h3>" . __("Currently enabled for (click to edit):") . "</h3>";

			print "<ul class=\"browseFeedList\" style=\"border-width : 1px\">";
			foreach ($enabled_feeds as $f) {
				print "<li>" .
					"<img src='images/pub_set.png'
						style='vertical-align : middle'> <a href='#'
						onclick='editFeed($f)'>".
					getFeedTitle($f) . "</a></li>";
			}
			print "</ul>";
		}

		print "</div>";
	}

	function hook_prefs_edit_feed($feed_id) {
		print "<div class=\"dlgSec\">".__("Similarity (pg_trgm)")."</div>";
		print "<div class=\"dlgSecCont\">";

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!array($enabled_feeds)) $enabled_feeds = array();

		$key = array_search($feed_id, $enabled_feeds);
		$checked = $key !== FALSE ? "checked" : "";

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"trgm_similarity_enabled\"
			name=\"trgm_similarity_enabled\"
			$checked>&nbsp;<label for=\"trgm_similarity_enabled\">".__('Mark similar articles as read')."</label>";

		print "</div>";
	}

	function hook_prefs_save_feed($feed_id) {
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();

		$enable = checkbox_to_sql_bool($_POST["trgm_similarity_enabled"]) == 'true';
		$key = array_search($feed_id, $enabled_feeds);

		if ($enable) {
			if ($key === FALSE) {
				array_push($enabled_feeds, $feed_id);
			}
		} else {
			if ($key !== FALSE) {
				unset($enabled_feeds[$key]);
			}
		}

		$this->host->set($this, "enabled_feeds", $enabled_feeds);
	}
}
?>
