/**
 * Toggle related article as unread.
 * @param	button
 * @param	relatedArticleId	The related article identifier.
 */
function raToggleUnread(button, relatedArticleId) {
	console.info('Toggle unread article for id ' + relatedArticleId);
	// Request toggle unread related article
	raRequestToggleUnread(relatedArticleId, 2, function() {
		// Get related article link
		var aNode = button.nextSibling;
		// Switch unread CSS class
		aNode.className = aNode.className === 'relatedArticleRead' ? 'relatedArticleUnread' : 'relatedArticleRead';
	});
}

/**
 * Toggle article as unread.
 * @param	id  		The article identifier.
 * @param	cmode   	0 to mark as read, 1 to mark as unread and 2 to toggle.
 * @param	callback	The callback if request is successful.
 */
function raRequestToggleUnread(id, cmode, callback) {
	// Call catchupSelected method throw RPC backend
	xhrPost("backend.php", {
		op: "rpc",
		method: "catchupSelected",
		ids: id,
		cmode: cmode
	}).then(callback);
}

// Register hooks
require(['dojo/_base/kernel', 'dojo/ready'], function  (dojo, ready) {
	ready(function () {
		// Register HOOK_ARTICLE_RENDERED hook
		PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED, function(row) {
			var id = Article.getActive();
			console.info('HOOK_ARTICLE_RENDERED for id ' + id);
			try {
				// Check if related articles list or loading div already exist
				if (dojo.byId('related-articles-' + id) || dojo.byId('related-articles-loading-' + id)) {
					console.info('Related articles are already loaded for id ' + id);
					return;
				}
				// Get article span
				var contentDiv = row.getElementsByClassName('content');
				if (!contentDiv || contentDiv.length < 1) {
					console.info('Unable to retrieve article div');
					return;
				}
				contentDiv = contentDiv[0];
				// Create loading div
				var divNode = dojo.create('div', {
					id: 'related-articles-loading-' + id,
					innerHTML: 'Loading related articles…',
					style: 'font-style: italic'
				}, contentDiv, 'first');
				console.info('Loading related articles for id: ' + id);
				// Send request to showrelated method with feed entry id
				new Ajax.Request('backend.php', {
					parameters: 'op=pluginhandler&plugin=related_articles&method=showrelated&param=' + id,
					onComplete: function(response) {
						// Check response status
						if (response.status !== 200) {
							// Notify user
							divNode.innerHTML = 'Sorry. Could not load related articles.';
							console.info('Unable to load related articles for id ' + id);
							return;
						}
						// Decode JSON encoded response
						var related_articles = JSON.parse(response.responseText);
						// Check response length
						if (related_articles.length < 1) {
							// Notify user
							divNode.innerHTML = 'No related articles found.';
							console.info('No related article for id ' + id);
							return;
						}
						// Remove loading div
						dojo.destroy(divNode);
						// Create related article list
						var listNode = dojo.create('ul', {
							'id': 'related-articles-' + id,
							'class': 'relatedArticles',
							'style': 'list-style-type: none;'
						}, contentDiv, 'first');
						// Append each related articles
						for (var related_article of related_articles) {
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
								'src': 'plugins.local/related_articles/score_' + related_article['score_type'] + '.png',
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
							// Create toggle unread a
							var toggleReadANode = dojo.create('a', {
								'onclick': 'raToggleUnread(this, ' + related_article['id'] + '); return false',
								'href': '#'
							}, liNode, 'last');
							// Create toggle unread image
							dojo.create('img', {
								'src': 'plugins.local/related_articles/toggle_unread.png',
								'alt': 'Toggle unread related article',
								'style': 'vertical-align: middle; margin: 0em 0.5em'
							}, toggleReadANode);
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
	})
});
