/**
 * Toggle related article as unread.
 * @id  The article identifier.
 * @cmode   0 to mark as read, 1 to mark as unread and 2 to toggle.
 */
function raToggleUnread(id, cmode) {
	var query = "?op=rpc&method=catchupSelected" +
		"&cmode=" + param_escape(cmode) + "&ids=" + param_escape(id);


	new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function(transport) {
			handle_rpc_json(transport);
		}
	});
}

// Register hooks
dojo.addOnLoad(function() {
	/*PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED, function(row) {
		console.info("HOOK_ARTICLE_RENDERED");
		console.info(row);
		return true;
	});
	PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED_CDM, function(row) {
		console.info("HOOK_ARTICLE_RENDERED_CDM");
		console.info(row);
		return true;
	});
	PluginHost.register(PluginHost.HOOK_ARTICLE_SET_ACTIVE, function(id) {
		console.info("HOOK_ARTICLE_SET_ACTIVE");
		console.info(id);
		return true;
	});*/
	// Register HOOK_ARTICLE_EXPANDED hook
	PluginHost.register(PluginHost.HOOK_ARTICLE_EXPANDED, function(id) {
		console.info("HOOK_ARTICLE_EXPANDED");
		console.info(id);
		try {
			// Check if related articles list already exist
			var listNode = dojo.byId('related-articles-' + id);
			if (listNode) {
				console.info('Related articles are already loaded for id ' + id);
				return;
			}
			console.info('Loading related articles for id: '+id);
			// Send request to showrelated method with feed entry id
			new Ajax.Request('backend.php', {
				parameters: 'op=pluginhandler&plugin=related_articles&method=showrelated&param=' + param_escape(id),
				onComplete: function(transport) {
					console.info(transport);
					console.info('Response: ' + transport.responseText);
					// Decode JSON encoded response
					var response = JSON.parse(transport.responseText);
					// Check response length
					if (response.length < 1) {
						console.info('No related article for id ' + id);
						return;
					}
					// Get article span
					var spanNode = dojo.byId('CWRAP-' + id);
					if (!spanNode) {
						console.info('Unable to retrieve article span for id ' + id);
						return;
					}
					// Create related article list
					var listNode = dojo.create('ul', {
						'id': 'related-articles-' + id,
						'style': 'list-style-type: none;'
					}, spanNode, 'first');
					// Append each related articles
					for (var related_article of response) {
						// Create li for related article
						var liNode = dojo.create('li', null, listNode);
						// Create div for date-time
						dojo.create('div', {
							'class': 'insensitive small',
							'style': 'margin-left : 20px; float : right',
							'innerHTML': related_article['date_time']
						}, liNode, 0);
						// Create score image
						dojo.create('img', {
							'src': 'images/score_' + related_article['score_type'] + '.png',
							'title': related_article['score'],
							'style': 'vertical-align: middle'
						}, liNode, 1);
						// Create feed div
						var feedDivNode = dojo.create('div', {
							'class': 'hlFeed',
							'style': 'display: inline-block; font-size: 11px; width: 135px'
						}, liNode, 2);
						// Create feed a
						dojo.create('a', {
							'onclick': 'viewfeed({feed:' + related_article['feed_id'] + '})',
							'href': '#',
							'style': 'umargin-top: 1px; padding: 1px 6px 0px; border: 1px solid rgba(0, 0, 0, 0.03); ' +
								'border-radius: 99px; background: rgba(0, 0, 0, 0.1) none repeat scroll 0% 0%; ' +
								'color: #444; line-height: 1; overflow: hidden; max-width: 115px; text-overflow: ellipsis; ' +
								'background-color: ' + related_article['feed_color'] + ';',
							'innerHTML': related_article['feed_name']
						}, feedDivNode);
						// Create link a
						dojo.create('a', {
							'href': related_article['link'],
							'title': related_article['title'],
							'class': related_article['unread'] == '1' ? 'relatedArticleUnread' : 'relatedArticleRead',
							'innerHTML': related_article['title'],
						}, liNode, 'last');
						// Create score span
						dojo.create('span', {
							'class': 'insensitive',
							'style': 'padding-left: 0.5em',
							'innerHTML': '(' + related_article['score'] + ')'
						}, liNode, 'last');
					}
				}
			});
		} catch (exception) {
			// Handle exception
			exception_error("HOOK_ARTICLE_EXPANDED callback", exception);
		}
		// Continue plugin hook run
		return true;
	});
	/*PluginHost.register(PluginHost.HOOK_ARTICLE_COLLAPSED, function(id) {
		console.info("HOOK_ARTICLE_COLLAPSED");
		console.info(id);
		return true;
	});*/
});
