<?php


include("/home/uesp/secrets/uespblog.secrets");


$db = new mysqli($blogDBHost, $blogDBUserName, $blogDBPassword, $blogDBName);
if (!$db || $db->connect_error) die("Failed to initialize database connection!");

$xw = xmlwriter_open_memory();
xmlwriter_set_indent($xw, 1);
xmlwriter_set_indent_string($xw, ' ');

$result = $db->query("SELECT * FROM evo_items__item;");
$posts = array();

while ($row = $result->fetch_assoc()) {
	$id = $row['post_ID'];
	
	$row['comments'] = array();
	$row['categories'] = array();
	$row['tags'] = array();
	
	$row['allCategories'] = "";
	$row['allTags'] = "";
	
	$row['post_title'] = str_replace('’', "&apos;", $row['post_title']);
	
	$row['post_excerpt'] = str_replace('’', "&apos;", $row['post_excerpt']);
	$row['post_excerpt'] = str_replace('“', "&#x93;", $row['post_excerpt']);
	$row['post_excerpt'] = str_replace('”', "&#x94;", $row['post_excerpt']);
	$row['post_excerpt'] = str_replace('[teaserbreak]', "", $row['post_excerpt']);
	$row['post_excerpt'] = preg_replace('/[\xA0]/', '&nbsp;', $row['post_excerpt']);
	
	$row['post_content'] = str_replace('’', "&apos;", $row['post_content']);
	$row['post_content'] = str_replace('‘', "&#x91;", $row['post_content']);
	$row['post_content'] = str_replace('’', "&#x92;", $row['post_content']);
	$row['post_content'] = str_replace('“', "&#x93;", $row['post_content']);
	$row['post_content'] = str_replace('”', "&#x94;", $row['post_content']);
	$row['post_content'] = str_replace('–', "&#x96;", $row['post_content']);
	$row['post_content'] = str_replace('[teaserbreak]', "", $row['post_content']);
	$row['post_content'] = preg_replace('/[\x85]/', '&#x85;', $row['post_content']);
	$row['post_content'] = preg_replace('/[\x97]/', '&#x97;', $row['post_content']);
	$row['post_content'] = preg_replace('/[\xA0]/', '&nbsp;', $row['post_content']);
	$row['post_content'] = preg_replace('/[\xA3]/', '&#xa3;', $row['post_content']);
	$row['post_content'] = preg_replace('/[\xE8]/', '&#xe8;', $row['post_content']);
	$row['post_content'] = preg_replace('/[\xE9]/', '&#xe9;', $row['post_content']);
	$row['post_content'] = preg_replace('/[\xEB]/', '&#xeb;', $row['post_content']);
	
	$row['post_excerpt'] = preg_replace('#http://blog.uesp.net/media/[A-Za-z0-9_\-/]+/([A-Za-z0-9_\-]+)#', '//newblog.uesp.net/wp-content/uploads/2020/04/\1', $row['post_excerpt']);
	$row['post_content'] = preg_replace('#http://blog.uesp.net/media/[A-Za-z0-9_\-/]+/([A-Za-z0-9_\-]+)#', '//newblog.uesp.net/wp-content/uploads/2020/04/\1', $row['post_content']);
	
	$row['post_excerpt'] = preg_replace('#/media/[A-Za-z0-9_\-/]+/([A-Za-z0-9_\-]+)#', '//newblog.uesp.net/wp-content/uploads/2020/04/\1', $row['post_excerpt']);
	$row['post_content'] = preg_replace('#/media/[A-Za-z0-9_\-/]+/([A-Za-z0-9_\-]+)#', '//newblog.uesp.net/wp-content/uploads/2020/04/\1', $row['post_content']);
	
	$posts[$id] = $row;
}

$count = count($posts);
//print("\tLoaded $count posts...\n");

$result = $db->query("SELECT * FROM evo_users;");
$users = array();

while ($row = $result->fetch_assoc()) {
	$id = $row['user_ID'];
	$users[$id] = $row;
}

$count = count($users);
//print("\tLoaded $count users...\n");

$result = $db->query("SELECT * FROM evo_comments;");
$comments = array();

while ($row = $result->fetch_assoc()) {
	$id = $row['comment_ID'];
	$comments[$id] = $row;
}

$count = count($comments);
//print("\tLoaded $count comments...\n");

$result = $db->query("SELECT * FROM evo_files;");
$files = array();

while ($row = $result->fetch_assoc()) {
	$id = $row['file_ID'];
	$row['file_hash'] = "";
	$row['file_path_hash'] = "";
	$files[$id] = $row;
}

$count = count($files);
//print("\tLoaded $count files...\n");

$result = $db->query("SELECT * FROM evo_postcats;");
$postCategories = array();

while ($row = $result->fetch_assoc()) {
	$postCategories[] = $row;
}

$count = count($postCategories);
//print("\tLoaded $count post categories...\n");

$result = $db->query("SELECT * FROM evo_categories;");
$categories = array();

while ($row = $result->fetch_assoc()) {
	$id = $row['cat_ID'];
	$categories[$id] = $row;
}

$count = count($categories);
//print("\tLoaded $count categories...\n");

$result = $db->query("SELECT * FROM evo_items__tag;");
$tags = array();

while ($row = $result->fetch_assoc()) {
	$id = $row['tag_ID'];
	$tags[$id] = $row;
}

$count = count($tags);
//print("\tLoaded $count tags...\n");

$result = $db->query("SELECT * FROM evo_items__itemtag;");
$postTags = array();

while ($row = $result->fetch_assoc()) {
	$postTags[] = $row;
}

$count = count($postTags);
fwrite(STDERR, "\tLoaded $count post tags...\n");

foreach ($files as $id => $file) {
	$rootType = $file['file_root_type'];
	$rootId = $file['file_root_ID'];
	$filePath = ltrim($file['file_path'], '/');
	
	$files[$id]['file_url'] = "";
	
	$imageUrl = "http://blog.uesp.net/media/";
	
	if ($rootType == "collection") {
		if ($rootId == 4)
			$imageUrl .= "blogs/photos/" . $filePath;
		else if ($rootId == 1)
			$imageUrl .= "blogs/a/" . $filePath;
		else
			$imageUrl .= "blogs/a/" . $filePath;
	}
	else if ($rootType == "user") {
		$user = $users[$rootId];
		if ($user == null) continue;
		
		$name = $user['user_nickname'];
		if ($name == null) $name = $user['user_login'];
		
		$imageUrl .= "users/" . $name . "/" . $filePath;
	}
	
	$ext = pathinfo($filePath, PATHINFO_EXTENSION);
	if ($ext == null || $ext == "") $imageUrl = "";
	
	$files[$id]['file_url'] = $imageUrl;
}

foreach ($postTags as $idx => $postTag) {
	$postId = $postTag['itag_itm_ID'];
	$tagId = $postTag['itag_tag_ID'];
	
	$post = $posts[$postId];
	if ($post == null) continue;
	
	$tag = $tags[$tagId];
	if ($tag == null) continue;
	
	$posts[$postId]['tags'][] = $tag['tag_name'];
	
	$allTags = implode(", ", $posts[$postId]['tags']);
	$posts[$postId]['allTags'] = $allTags;
}

foreach ($postCategories as $id => $postCategory) {
	$postId = $postCategory['postcat_post_ID'];
	$catId = $postCategory['postcat_cat_ID'];
	
	$post = $posts[$postId];
	if ($post == null) continue;
	
	$category = $categories[$catId];
	if ($category == null) continue;
	
	$posts[$postId]['categories'][] = $category['cat_name'];
	
	$allCats = implode(", ", $posts[$postId]['categories']);
	$posts[$postId]['allCategories'] = $allCats;
}

foreach ($posts as $postId => $post) {
	$userId = $post['post_creator_user_ID'];
	$user = $users[$userId];
	
	if ($user) {;
		$nickname = $user['user_nickname'];
		if ($nickname == null) $nickname = $user['user_login'];
		
		if ($nickname == null || $nickname == "") $nickname = "Unknown";
		$posts[$postId]['post_creator_nickname'] = $nickname;
	}
	
	$catId = $post['post_main_cat_ID'];
	$category = $categories[$catId];
	if ($category) $post['post_main_cat'] = $category['cat_name'];
}

foreach ($comments as $id => $comment) {
	$postId = $comment['comment_item_ID'];
	$post = $posts[$postId];
	if ($post == null) continue;
	
	$newComment = array();
	$newComment['comment_content'] = $comment['comment_content'];
	$newComment['comment_author_user_ID'] = $comment['comment_author_user_ID'];
	$newComment['comment_author'] = $comment['comment_author'];
	$newComment['comment_author_email'] = $comment['comment_author_email'];
	$newComment['comment_author_url'] = $comment['comment_author_url'];
	$newComment['comment_author_IP'] = $comment['comment_author_IP'];
	$newComment['comment_date'] = $comment['comment_date'];
	
	$posts[$postId]['comments'][] = $newComment;
}


xmlwriter_start_document($xw, '1.0', 'UTF-8');
xmlwriter_start_element($xw, 'all');

foreach ($posts as $id => $post) {
	xmlwriter_start_element($xw, 'post');
	
	foreach ($post as $field => $value) {
		
		if (is_array($value)) {
				//Skip
		}
		else if ($field == "post_content" || $field == "post_excerpt" || $field == "post_title") {
			xmlwriter_start_element($xw, $field);
			if ($field == "post_title") xmlwriter_write_cdata($xw, $value);
			if ($field == "post_excerpt") xmlwriter_write_cdata($xw, $value);
			if ($field == "post_content") xmlwriter_write_cdata($xw, $value);
			xmlwriter_end_element($xw);
		}
		else if ($field == "comments") {
			xmlwriter_start_element($xw, $field);
			
			foreach ($value as $index => $comment) {
				xmlwriter_start_element($xw, "comment");
				
				foreach ($comment as $field => $value) {
					xmlwriter_start_element($xw, $field);
					
					if ($field == "comment_content") {
						//xmlwriter_write_cdata($xw, $value);
					}
					else {
						xmlwriter_text($xw, $value);
					}
					
					xmlwriter_end_element($xw);
				}
				
				xmlwriter_end_element($xw);
			}
			
			xmlwriter_end_element($xw);
		}
		else {
			xmlwriter_start_element($xw, $field);
			xmlwriter_text($xw, $value);
			xmlwriter_end_element($xw);
		}
	}
	
	xmlwriter_end_element($xw); 
}

foreach ($files as $id => $file) {
	xmlwriter_start_element($xw, 'file');
	
	foreach ($file as $field => $value) {
		xmlwriter_start_element($xw, $field);
		xmlwriter_text($xw, $value);
		xmlwriter_end_element($xw);
	}
	
	xmlwriter_end_element($xw);
}

xmlwriter_end_element($xw);
echo xmlwriter_output_memory($xw);