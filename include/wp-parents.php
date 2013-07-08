<?php
function get_topmost_parent_id ($post_id) {
	if ($post_id === null)
		$post_id = get_the_ID();

	$parent_id = get_parent_id ($post_id);
	if ($parent_id == 0){
		return $post_id;
	} else {
		return get_topmost_parent_id ($parent_id);
	}
}
function get_parents_ids ($post_id = null) {
	if ($post_id === null)
		$post_id = get_the_ID();

	$parents_ids = array ();

	$parent_id = get_parent_id ($post_id);
	while ($parent_id != 0) {
		$parents_ids[] = $parent_id;
		$parent_id = get_parent_id ($parent_id);
	}

	return $parents_ids;
}
function get_parent_id ($post_id) {
	if ($post_id === null)
		$post_id = get_the_ID();

	return get_post($post_id)->post_parent;
}