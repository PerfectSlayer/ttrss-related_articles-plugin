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
