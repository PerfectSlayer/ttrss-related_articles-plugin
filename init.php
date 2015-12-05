<?php
class Related_Articles extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Find related articles",
			"PerfectSlayer",
			true);
	}

	function save() {
		$similarity = (float) db_escape_string($_POST["similarity"]);
		$min_title_length = (int) db_escape_string($_POST["min_title_length"]);
		$enable_globally = checkbox_to_sql_bool($_POST["enable_globally"]) == "true";

		if ($similarity < 0) $similarity = 0;
		// if ($similarity > 1) $similarity = 1;

		if ($min_title_length < 0) $min_title_length = 0;

		$similarity = sprintf("%.2f", $similarity);

		$this->host->set($this, "similarity", $similarity);
		$this->host->set($this, "min_title_length", $min_title_length);
		$this->host->set($this, "enable_globally", $enable_globally);

		echo T_sprintf("Data saved (%s, %d)", $similarity, $enable_globally);
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_UPDATE_TASK, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
		$host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);
		$host->add_hook($host::HOOK_RENDER_ARTICLE_CDM, $this);

	}

	function hook_render_article_cdm($article) {
		$owner_uid = $_SESSION["uid"];

		$id = $article['id'];
		$title = db_escape_string($article['title']);

		$similarity = $this->host->get($this, "similarity");
		if (!$similarity) $similarity = '5';

		$result = db_query("SELECT ttrss_entries.id AS id,
		        ttrss_entries.updated AS updated,
		        ttrss_entries.title AS title,
		        ttrss_feeds.title AS feed_name,
		        ttrss_feeds.favicon_avg_color AS feed_color,
		        MATCH(ttrss_related_articles.title, ttrss_related_articles.content) AGAINST('$title') AS score
		    FROM
		        ttrss_related_articles, ttrss_entries, ttrss_user_entries LEFT JOIN ttrss_feeds ON (ttrss_feeds.id = ttrss_user_entries.feed_id)
		    WHERE
		        MATCH(ttrss_related_articles.title, ttrss_related_articles.content) AGAINST('$title') > $similarity AND
		        ttrss_entries.id = ttrss_user_entries.ref_id AND
		        ttrss_user_entries.owner_uid = $owner_uid AND
		        ttrss_related_articles.ref_id = ttrss_entries.id AND
		        ttrss_related_articles.ref_id != $id
		    ORDER BY
		        score DESC
		    LIMIT 10");

		$addition = "<ul class=\"browseFeedList\">";

		while ($line = db_fetch_assoc($result)) {
		    $score = sprintf("%.2f", $line['score']);

		    $addition = $addition . "<li>";

		    $addition = $addition . "<div class='insensitive small' style='margin-left : 20px; float : right'>" . smart_date_time(strtotime($line["updated"])) . "</div>";
		    $addition = $addition . "<img src='images/score_high.png' title='$score' style='vertical-align : middle'>";

		    $addition = $addition . "<div class='hlFeed' style='display: inline-block; font-size: 11px; width: 135px'>";
		    $addition = $addition . "<a href='#' style='umargin-top: 1px; padding: 1px 6px 0px; border: 1px solid rgba(0, 0, 0, 0.03); border-radius: 99px; background: rgba(0, 0, 0, 0.1) none repeat scroll 0% 0%; color: #444; line-height: 1; overflow: hidden; max-width: 115px; text-overflow: ellipsis; background-color: " . $line["feed_color"] . "'>" . $line["feed_name"] . "</a>";
		    $addition = $addition . "</div>";

		    $addition = $addition . " " .$line["title"];

		    $addition = $addition .  " <span class='insensitive'>($score)</span>";

		    $addition = $addition . "</li>";
		}

		$addition = $addition . "</ul>";

		$article["content"] = $addition . $article["content"];

		return $article;
	}

	function hook_update_task() {
		// TODO Specific user filter applied (ttrss_user_entries.owner_uid = 1)
		// Select entries which are not already in related feeds table
		$result = db_query("SELECT ttrss_entries.id AS id, ttrss_entries.title AS title, ttrss_entries.content AS content
			FROM ttrss_entries, ttrss_user_entries
			WHERE ttrss_entries.id = ttrss_user_entries.ref_id AND ttrss_user_entries.owner_uid = 1 AND ttrss_entries.id NOT IN (
				SELECT ttrss_related_articles.ref_id FROM ttrss_related_articles
			)");
		// Add each entry
		while ($line = db_fetch_assoc($result)) {
			$id = $line['id'];
			$title = db_escape_string($line['title']);
			$content = db_escape_string($line['content']);
			// TODO Strip tags of content
			// TODO Do batch insertion
			db_query("INSERT INTO ttrss_related_articles(id, ref_id, title, content) VALUES(NULL, '$id', '$title','$content')");
		}
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
				console.log(dojo.objectToQuery(this.getValues()));
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

		$enabled_feeds = $this->filter_unknown_feeds($enabled_feeds);
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

	function api_version() {
		return 2;
	}
}
?>
