function showRelatedArticles(id) {
	try {

		var query = "backend.php?op=pluginhandler&plugin=related_articles&method=showrelated&param=" + param_escape(id);

		if (dijit.byId("relatedArticlesDialog"))
			dijit.byId("relatedArticlesDialog").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "relatedArticlesDialog",
			title: __("Related articles"),
			style: "width: 800px",
			execute: function() {

			},
			href: query,
		});

		dialog.show();

	} catch (e) {
		exception_error("showRelatedArticles", e);
	}
}
