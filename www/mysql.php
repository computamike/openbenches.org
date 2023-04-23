<?php
require_once ('config.php');
require_once ('functions.php');

//	Set up the database connection
$mysqli = new mysqli(DB_IP, DB_USER, DB_PASS, DB_TABLE);
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

if (!$mysqli->set_charset("utf8mb4")) {
	printf("Error loading character set utf8mb4: %s\n", $mysqli->error);
	exit();
}

function insert_bench($lat, $long, $inscription, $userID)
{
	//	Trim errant whitespace from the end before inserting
	$inscription = rtrim($inscription);

	$address = get_place_name($lat, $long);

	global $mysqli;
	$insert_bench = $mysqli->prepare(
		"INSERT INTO `benches`
				 (`benchID`,`latitude`,`longitude`,`address`, `inscription`,`description`,`present`,`published`, `added`,  `userID`)
		VALUES (NULL,		?,		  ?,			 ?,			 ?,			  '',			  '1'  ,	 '1', CURRENT_TIMESTAMP, ?		)");
	$insert_bench->bind_param('ddssi', $lat, $long, $address, $inscription, $userID);
	$insert_bench->execute();
	$resultID = $insert_bench->insert_id;
	if ($resultID) {
		$insert_bench->free_result();
		$insert_bench->close();
		unset($mysqli);
		return $resultID;
	} else {
		$insert_bench->free_result();
		$insert_bench->close();
		unset($mysqli);
		return $mysqli->error;
	}
}

function edit_bench($lat, $long, $inscription, $benchID, $published=true, $userID)
{
	$address = get_place_name($lat, $long);

	global $mysqli;
	$edit_bench = $mysqli->prepare(
		"UPDATE `benches`
			 SET `latitude`   = ?,
				  `longitude`  = ?,
				  `address`    = ?,
				  `inscription`= ?,
				  `published`  = ?,
				  `userID`     = ?
		  WHERE `benches`.`benchID` = ?");
	$edit_bench->bind_param('ddssisi', $lat, $long, $address, $inscription, $published, $userID, $benchID);
	$edit_bench->execute();
	$edit_bench->free_result();
	$edit_bench->close();
	unset($mysqli);

	return true;
}

function update_bench_address($benchID, $benchLat, $benchLong) {
	$address = get_place_name($benchLat, $benchLong);
	global $mysqli;
	$edit_bench = $mysqli->prepare(
		"UPDATE `benches`
			 SET `address` = ?
		  WHERE `benches`.`benchID` = ?");
	$edit_bench->bind_param('si', $address, $benchID);
	$edit_bench->execute();
	$edit_bench->free_result();
	$edit_bench->close();
	unset($mysqli);

	return $address;
}


function insert_media($benchID, $userID, $sha1, $licence="CC BY-SA 4.0", $import=null, $media_type=null, $width=null, $height=null, $datetime=null, $make=null, $model=null)
{
	global $mysqli;
	$insert_media = $mysqli->prepare(
		'INSERT INTO `media`
		(`mediaID`, `benchID`, `userID`, `sha1`, `licence`, `importURL`, `media_type`, `width`, `height`, `datetime`, `make`, `model`)
		VALUES
		(NULL,       ?,         ?,        ?,      ?,         ?,           ?,            ?,       ?,       ?,          ?,      ?);'
	);

	$insert_media->bind_param('iissssiisss', $benchID, $userID, $sha1, $licence, $import, $media_type, $width, $height, $datetime, $make, $model);
	$insert_media->execute();
	$resultID = $insert_media->insert_id;
	if ($resultID) {
		$insert_media->free_result();
		$insert_media->close();
		unset($mysqli);
		return $resultID;
	} else {
		$insert_media->free_result();
		$insert_media->close();
		unset($mysqli);
		return $mysqli->error;
	}
}

function edit_media_type($mediaID, $media_type) {
	global $mysqli;
	$edit_media = $mysqli->prepare(
		"UPDATE `media`
			 SET `media_type` =	 ?
		  WHERE `media`.`media_type` = ?");
	$edit_media->bind_param('ss', $media_type, $mediaID);
	$edit_media->execute();
	$edit_media->free_result();
	$edit_media->close();
	unset($mysqli);

	return true;
}

function insert_user($provider, $providerID, $name)
{
	global $mysqli;

	//	Does the user already exist?
	//	Only applicable for Auth0 users
	if ("anon" != $provider) {
		$search_user = $mysqli->prepare("SELECT `userID` FROM `users`
			WHERE `provider` LIKE ? AND `providerID` LIKE ?");
		$search_user->bind_param('ss', $provider, $providerID);
		$search_user->execute();
		$search_user->bind_result($userID);
		# Loop through rows to build feature arrays
		while($search_user->fetch()) {
			if ($userID){
				$search_user->free_result();
				return $userID;
			}
		}
		$search_user->free_result();
		$search_user->close();
		unset($mysqli);
	}

	$insert_user = $mysqli->prepare("INSERT INTO `users`
				 (`userID`, `provider`, `providerID`, `name`)
		VALUES (NULL,	  ?,			  ?,				?);"
	);

	$insert_user->bind_param('sss', $provider, $providerID, $name);
	$insert_user->execute();

	$resultID = $insert_user->insert_id;
	$insert_user->free_result();
	$insert_user->close();
	unset($mysqli);
	if ($resultID) {
		return $resultID;
	} else {
		return null;
	}
}

function get_nearest_benches($lat, $long, $distance=0.5, $limit=20, $truncated = false, $media = false)
{
	//	If media have been requested
	if($media) {
		if(is_numeric($id)) {
			$media_data = get_all_media($id);
		} else {
			$media_data = get_all_media(0);
		}
	} else {
		$media_data = array();
	}

	global $mysqli;

	$get_benches = $mysqli->prepare(
		"SELECT
			(
				6371 * ACOS(COS(RADIANS(?)) *
				COS(RADIANS(latitude)) *
				COS(RADIANS(longitude) -
				RADIANS(?)) +
				SIN(RADIANS(?)) *
				SIN(RADIANS(latitude)))
			)
			AS distance, benchID, latitude, longitude, inscription, published, added
		FROM
			benches
		WHERE published = true AND present = true
		HAVING distance < ?
		ORDER BY distance
		LIMIT 0 , ?");

	$get_benches->bind_param('ddddd', $lat, $long, $lat, $distance, $limit );
	$get_benches->execute();

	/* bind result variables */
	$get_benches->bind_result($dist, $benchID, $benchLat, $benchLong, $benchInscription, $published, $added);

	# Build GeoJSON feature collection array
	$geojson = array(
		'type'		=> 'FeatureCollection',
		'features'  => array()
	);
	# Loop through rows to build feature arrays
	while($get_benches->fetch()) {

		# some inscriptions got stored with leading/trailing whitespace
		$benchInscription=trim($benchInscription);

		# if displaying on map need to truncate inscriptions longer than
		# 128 chars and add in <br> elements
		# N.B. this logic is also in get_all_benches()
		if ($truncated) {
			$benchInscriptionTruncate = mb_substr($benchInscription,0,128);
			if ($benchInscriptionTruncate !== $benchInscription) {
				$benchInscription = $benchInscriptionTruncate . '…';
			}
			$benchInscription=nl2br(htmlspecialchars($benchInscription));
		}
		//	Horrible hack to force numeric inscriptions to be strings
		if (is_numeric($benchInscription)) {
			 $benchInscription .= " ";
		}

		if ($media) {
			if (isset($media_data[$benchID])){
				$mediaFeature = $media_data[$benchID];
			} else {
				$mediaFeature = null;
			}
		} else {
			$mediaFeature = null;
		}
		$feature = array(
			'id' => $benchID,
			'type' => 'Feature',
			'geometry' => array(
				'type' => 'Point',
				# Pass Longitude and Latitude Columns here
				'coordinates' => array($benchLong, $benchLat)
			),
			# Pass other attribute columns here
			'properties' => array(
				'created_at'   => date_format( date_create($added ), "c" ),
				'popupContent' => $benchInscription,
				'media'        => $mediaFeature
			),
		);

		# Add feature arrays to feature collection array
		array_push($geojson['features'], $feature);
	}
	$get_benches->free_result();
	$get_benches->close();
	unset($mysqli);
	return $geojson;
}

function get_nearest_benches_list($lat, $long, $distance=10, $limit=20) {
	global $mysqli;

	$get_benches = $mysqli->prepare(
		"SELECT
			(
				6371 * ACOS(COS(RADIANS(?)) *
				COS(RADIANS(latitude)) *
				COS(RADIANS(longitude) -
				RADIANS(?)) +
				SIN(RADIANS(?)) *
				SIN(RADIANS(latitude)))
			)
			AS distance, benchID, latitude, longitude, inscription, address
		FROM
			benches
		WHERE published = true AND present = true
		HAVING distance < ?
		ORDER BY distance
		LIMIT 0 , ?");

	$get_benches->bind_param('ddddd', $lat, $long, $lat, $distance, $limit );
	$get_benches->execute();

	/* bind result variables */
	$get_benches->bind_result($dist, $benchID, $benchLat, $benchLong, $benchInscription, $address);

	$results = array();
	while($get_benches->fetch()) {
		$inscription = htmlspecialchars($benchInscription);
		if($address != null){
			$inscription .= "<br />Location: ". htmlspecialchars($address);
		}
		$results[$benchID] = $inscription;
	}

	$get_benches->free_result();
	$get_benches->close();
	unset($mysqli);
	return $results;
}

function get_bench($benchID, $truncated, $media = false){
	return get_all_benches($benchID, false, $truncated, $media);
}

function get_all_benches($id = 0, $only_published = true, $truncated = false, $media = false)
{
	//	If media have been requested
	if($media) {
		if(is_numeric($id)) {
			$media_data = get_all_media($id);
		} else {
			$media_data = get_all_media(0);
		}
	} else {
		$media_data = array();
	}

	if ($only_published){
		$where = "WHERE `published` = true AND `present` = true AND";
	} else {
		$where = "WHERE ";
	}

	//	Is this an ID or a tag?
	if(is_numeric($id)) {
		//	An ID
		if(0==$id){
			$benchQuery = ">";
		} else {
			$benchQuery = "=";
		}
		$where .= "`benchID` {$benchQuery} ?";

	} else {
		//	A Tag
		$tagID    = get_tagID($id);
		if(null == $tagID)
		{
			return null;
		}
		$benches  = get_benches_from_tag_id($tagID);
		$benchIDs = implode(",", $benches);
		$where .= "`benchID` IN ({$benchIDs})";
	}

	global $mysqli;

	$get_benches = $mysqli->prepare(
		"SELECT benchID, latitude, longitude, inscription, published, added FROM benches
		{$where}
		LIMIT 0 , 30000");

	if(is_numeric($id)) { $get_benches->bind_param('i', $id); }

	$get_benches->execute();

	/* bind result variables */
	$get_benches->bind_result($benchID, $benchLat, $benchLong, $benchInscription, $published, $added);

	# Build GeoJSON feature collection array
	$geojson = array(
		'type'		=> 'FeatureCollection',
		'features'  => array()
	);
	# Loop through rows to build feature arrays
	while($get_benches->fetch()) {

		# some inscriptions got stored with leading/trailing whitespace
		$benchInscription=trim($benchInscription);

		# if displaying on map need to truncate inscriptions longer than
		# 128 chars and add in <br> elements
		# N.B. this logic is also in get_nearest_benches()
		if ($truncated) {
			$benchInscriptionTruncate = mb_substr($benchInscription,0,128);
			if ($benchInscriptionTruncate !== $benchInscription) {
				$benchInscription = $benchInscriptionTruncate . '…';
			}
			$benchInscription=nl2br(htmlspecialchars($benchInscription));
		}

		//	Horrible hack to force numeric inscriptions to be strings
		if (is_numeric($benchInscription)) {
			 $benchInscription .= " ";
		}

		if ($media) {
			if (isset($media_data[$benchID])){
				$mediaFeature = $media_data[$benchID];
			} else {
				$mediaFeature = null;
			}
		} else {
			$mediaFeature = null;
		}
		$feature = array(
			'id' => $benchID,
			'type' => 'Feature',
			'geometry' => array(
				'type' => 'Point',
				# Pass Longitude and Latitude Columns here
				'coordinates' => array($benchLong, $benchLat)
			),
			# Pass other attribute columns here
			'properties' => array(
				'created_at'   => date_format( date_create($added ), "c" ),
				'popupContent' => $benchInscription,
				'media'        => $mediaFeature
			),
		);
		# Add feature arrays to feature collection array
		array_push($geojson['features'], $feature);
	}

	$get_benches->free_result();
	$get_benches->close();
	unset($mysqli);
	return $geojson;
}

function get_bench_details($benchID){
	global $mysqli;

	$get_bench = $mysqli->prepare(
		"SELECT benchID, latitude, longitude, address, inscription, published, present, description
		 FROM benches
		 WHERE benchID = ?"
	);

	$get_bench->bind_param('i', $benchID);
	$get_bench->execute();

	/* bind result variables */
	$get_bench->bind_result($benchID, $benchLat, $benchLong, $benchAddress, $benchInscription, $published, $present, $description);

	while($get_bench->fetch()) {
		$get_bench->free_result();
		$get_bench->close();
		unset($mysqli);

		return array ($benchID, $benchLat, $benchLong, htmlspecialchars($benchAddress), htmlspecialchars($benchInscription), $published, $present, htmlspecialchars($description));
	}
}

function get_random_bench(){
	global $mysqli;

	$get_bench = $mysqli->prepare(
		"SELECT benchID, latitude, longitude, address, inscription, published
		 FROM benches WHERE published = true AND present = true ORDER BY RAND() LIMIT 1;"
	);

	$get_bench->execute();

	/* bind result variables */
	$get_bench->bind_result($benchID, $benchLat, $benchLong, $benchAddress, $benchInscription, $published);

	while($get_bench->fetch()) {
		$get_bench->free_result();
		$get_bench->close();
		unset($mysqli);
		return array ($benchID, $benchLat, $benchLong, $benchAddress, $benchInscription, $published);
	}
}

function get_latest_bench(){
	global $mysqli;

	$get_bench = $mysqli->prepare(
		"SELECT benchID, latitude, longitude, address, inscription, published
		 FROM benches WHERE published = true AND present = true ORDER BY `benchID` DESC LIMIT 1;"
	);

	$get_bench->execute();

	/* bind result variables */
	$get_bench->bind_result($benchID, $benchLat, $benchLong, $benchAddress, $benchInscription, $published);

	while($get_bench->fetch()) {
		$get_bench->free_result();
		$get_bench->close();
		unset($mysqli);

		return array ($benchID, $benchLat, $benchLong, $benchAddress, $benchInscription, $published);
	}
}

function get_user_from_media($mediaID) {
	//	Who uploaded this media?
	global $mysqli;

	$get_user = $mysqli->prepare(
		"SELECT users.userID, users.name, users.provider
		FROM media
		INNER JOIN users ON media.userID = users.userID
		WHERE mediaID = ?");

	$get_user->bind_param('i',  $mediaID );
	$get_user->execute();
	/* bind result variables */
	$get_user->bind_result($userID, $userName, $userProvider);

	while($get_user->fetch()) {
		$get_user->free_result();
		$get_user->close();
		unset($mysqli);
		return array($userID, $userName, $userProvider);
	}
}

function get_image_html($benchID, $full = true)
{
	//	Which bench? Should this link to the full image?
	//	If it's not linking to the full image, link to the details page.

	$media_types_array = get_media_types_array();

	global $mysqli;

	$get_media = $mysqli->prepare(
		"SELECT sha1, users.userID, users.name, users.provider, users.providerID, importURL, licence, media_type, datetime, make, model, width
		FROM media
		INNER JOIN users ON media.userID = users.userID
		WHERE benchID = ?");

	$get_media->bind_param('i',  $benchID );
	$get_media->execute();
	/* bind result variables */
	$get_media->bind_result($sha1, $userID, $userName, $userProvider, $userProviderID, $importURL, $licence, $media_type, $datetime, $make, $model, $width);

	$html = '';

	# Loop through rows to build the HTML
	while($get_media->fetch()) {

		$userHTML = "";
		//	Who uploaded this media
		if("anon" != $userProvider) {
			$userHTML = "<a href='/user/{$userID}'>℅ {$userName}</a>";
		}

		//	Was this imported from an external source?
		$source="";

		$cc_icon = license_to_icon($licence);

		if(null != $importURL) {
			$source = "<a href=\"{$importURL}\"><img src=\"/images/cc/{$cc_icon}\" class=\"cc-icon\" alt=\"{$licence}\"/></a>";
		} else {
			$source = "<a rel=\"license\" href=\"https://creativecommons.org/licenses/by-sa/4.0/\"><img src=\"/images/cc/{$cc_icon}\" class=\"cc-icon\" alt=\"Creative Commons Attribution Share-alike\"/></a>";
		}

		//	When was the photo taken?
		if ($datetime != null) {
			$formatted_date = date("jS M Y", strtotime($datetime));
		} else {
			$formatted_date = "";
		}
		$make = ucwords($make);
		$exif_html = htmlspecialchars($formatted_date) . "<br>" . htmlspecialchars($make) . " " . htmlspecialchars($model);

		//	Pannellum can't take full width images. This size should be quick to compute
		$panorama_image = "/image/{$sha1}/3396";

		//	Generate alt tag
		if (array_key_exists($media_type, $media_types_array)){
			$alt = $media_types_array[$media_type];
		} else {
			$alt = "Photograph of a bench";
		}

		//	Where to link the image to
		if($full) {
			$link = get_image_cache($sha1, $width);
		} else {
			$link = "/bench/{$benchID}";
		}

		//	Start the container
		$html .= '<div itemprop="photo" class="benchImage">';

		//	What sort of image is it?
		if("360" == $media_type) {
			$panorama = "/libs/pannellum.2.5.6/pannellum.htm#panorama={$panorama_image}&amp;autoRotate=-2&amp;autoLoad=true";
			$html .= "<iframe width=\"600\" height=\"400\" allowfullscreen src=\"{$panorama}\"></iframe>";
		} else if("pano" == $media_type){
			$panorama = "/libs/pannellum.2.5.6/pannellum.htm#panorama={$panorama_image}&amp;autoRotate=-2&amp;autoLoad=true&amp;haov=360&amp;vaov=60";
			$html .= "<iframe width=\"600\" height=\"400\" allowfullscreen src=\"{$panorama}\"></iframe>";
		} else if ("video" == $media_type){
			$video = "/image/{$sha1}";
			$html .= "<video src=\"{$video}\" width=\"600\" controls loop></video>";
		}
		else {

			$dimensions = get_image_dimensions($sha1);
			$width  = $dimensions["width"];
			$height = $dimensions["height"];
			$default_width = IMAGE_DEFAULT_SIZE;
			$ratio  = $default_width / $width;
			$height = round($height * $ratio);

			$html .= "<a href='{$link}'>
							<img src='".get_image_cache($sha1)."' class='proxy-image' alt='{$alt}' width='{$default_width}' height='{$height}' />
						</a>";
		}

		$html .= "<span class='caption'>{$source}&nbsp;{$exif_html}&nbsp;{$userHTML}</span>";

		$html .= "</div>";

		//	If this is the front page, link to the details page
		if(!$full){
			$html .= "<br><a href='{$link}'>View more about this bench</a>";
			//	Only one image is needed.
			break;
		}
	}

	$get_media->free_result();
	$get_media->close();
	unset($mysqli);
	return $html;
}

function get_image_thumb($benchID, $size = IMAGE_THUMB_SIZE)
{
	global $mysqli;

	$get_media = $mysqli->prepare(
		"SELECT sha1 FROM media
		WHERE benchID = ?
		LIMIT 0 , 1");

	$get_media->bind_param('i',  $benchID );
	$get_media->execute();
	/* bind result variables */
	$get_media->bind_result($sha1);

	$thumb = "";

	# Loop through rows to build feature arrays
	while($get_media->fetch()) {
		$get_media->free_result();
		$get_media->close();
		unset($mysqli);

		$thumb = get_image_cache($sha1, $size);
		break;
	}

	return $thumb;
}

function get_all_media($benchID = 0)
{
	if (0==$benchID){
		$allBenches = true;
	} else {
		$allBenches = false;
	}
	global $mysqli;

	if($allBenches) {
		$get_media = $mysqli->prepare(
			"SELECT benches.benchID, media.sha1, media.importURL, media.licence, media.media_type, media.userID,
			        media.width, media.height, media.mediaID, users.name, users.provider
			 FROM `benches`
				INNER JOIN
			`media` ON benches.benchID = media.benchID
            INNER JOIN
         `users` on media.userID = users.userID");
			$get_media->execute();
			/* bind result variables */
			$get_media->bind_result($benchID, $sha1, $importURL, $licence, $media_type, $userID, $width, $height, $mediaID, $userName, $userProvider);
	} else {
		$get_media = $mysqli->prepare(
			"SELECT media.benchID, media.sha1, media.importURL, media.licence, media.media_type, media.userID,
			        media.width, media.height, media.mediaID, users.name, users.provider
			FROM `media`
				INNER JOIN
         `users` on media.userID = users.userID
            WHERE
         media.benchID =  ?");
			$get_media->bind_param('i',  $benchID );
			$get_media->execute();
			/* bind result variables */
			$get_media->bind_result($benchID, $sha1, $importURL, $licence, $media_type, $userID, $width, $height, $mediaID, $userName, $userProvider);
	}

	$media = array();

	# Loop through rows to build the array
	while($get_media->fetch()) {
		$media_data = array();

		$media_data["URL"] = "/image/{$sha1}";

		if(null != $importURL) {
			$media_data["importURL"] = $importURL;
		}

		$media_data["mediaID"]     = $mediaID;
		$media_data["licence"]     = $licence;
		$media_data["media_type"]  = $media_type;
		$media_data["sha1"]        = $sha1;
		$media_data["user"]        = $userID;
		$media_data["username"]    = $userName;
		$media_data["userprovider"]= $userProvider;
		$media_data["width"]       = $width;
		$media_data["height"]      = $height;

		//	Add all the media details to the response
		if (sizeof($media_data) > 0){
			$media[$benchID][] = $media_data;
		}
	}

	$get_media->free_result();
	$get_media->close();
	unset($mysqli);

	if (sizeof($media) > 0){
		return $media;
	} else {
		return null;
	}
}

function get_rss($items = 10) {
	global $mysqli;

	$get_rss = $mysqli->prepare(
		"SELECT benches.benchID, benches.inscription, benches.address, benches.added, media.sha1

		FROM `benches`
			INNER JOIN
		`media` ON benches.benchID = media.benchID
		WHERE benches.published = true AND benches.present = true
		ORDER by benches.benchID DESC
		LIMIT ?");

	$get_rss->bind_param('i', $items);
	$get_rss->execute();
	$get_rss->bind_result($benchID, $benchInscription, $benchAddress, $benchAdded, $sha1);

	$rssItems = array();

	while($get_rss->fetch()) {
		$rssData = array();

		$rssData["benchID"] = $benchID;
		$rssData["benchInscription"] = htmlspecialchars($benchInscription);
		$rssData["benchAddress"] = $benchAddress;
		$rssData["benchAdded"] = $benchAdded;
		$rssData["sha1"][] = $sha1;

		if (key_exists($benchID,$rssItems)){
			$rssItems[$benchID]["sha1"][] = $sha1;
		} else {
			$rssItems[$benchID] = $rssData;
		}
	}

	$get_rss->free_result();
	$get_rss->close();
	unset($mysqli);
	return $rssItems;
}

function get_image_url($benchID)
{
	global $mysqli;

	$get_media = $mysqli->prepare(
		"SELECT sha1 FROM media
		WHERE benchID = ?
		LIMIT 0 , 1");

	$get_media->bind_param('i',  $benchID );
	$get_media->execute();
	/* bind result variables */
	$get_media->bind_result($sha1);

	$url = "";

	# Loop through rows to build feature arrays
	while($get_media->fetch()) {
		$url = "/image/{$sha1}";
		$get_media->free_result();
		$get_media->close();
		unset($mysqli);
		break;
	}

	return $url;
}

function get_user_from_bench($benchID) {
	global $mysqli;
	$get_user_from_bench = $mysqli->prepare(
		"SELECT userID FROM benches
		WHERE benchID = ?
		LIMIT 0 , 1");

	$get_user_from_bench->bind_param('i',  $benchID);
	$get_user_from_bench->execute();
	/* bind result variables */
	$get_user_from_bench->bind_result($userID);

	# Loop through rows to build feature arrays
	while($get_user_from_bench->fetch()) {
		$get_user_from_bench->free_result();
		$get_user_from_bench->close();
		unset($mysqli);
		return get_user($userID)["name"];
	}
}

function get_user($userID)
{
	global $mysqli;
	$get_user = $mysqli->prepare(
		"SELECT provider, providerID, name FROM users
		WHERE userID = ?
		LIMIT 0 , 1");


	$get_user->bind_param('i',  $userID);
	$get_user->execute();
	/* bind result variables */
	$get_user->bind_result($provider, $providerID, $name);

	$userString = "";
	$user = array();
	# Loop through rows to build feature arrays
	while($get_user->fetch()) {
		if ("anon" != $provider){
			$name = htmlspecialchars($name);
			$user["provider"] = $provider;
			$user["providerID"] = $providerID;
			$user["name"] = $name;
		} else {
			$user["provider"] = "anonymous";
			$user["providerID"] = null;
			$user["name"] = null;
		}
	}
	$get_user->free_result();
	$get_user->close();
	unset($mysqli);
	return $user;
}

function get_all_users()
{
	global $mysqli;
	$get_user = $mysqli->prepare(
		"SELECT userID, provider, providerID, name FROM users");

	$get_user->execute();
	/* bind result variables */
	$get_user->bind_result($userID, $provider, $providerID, $name);

	$userString = "";
	$users = array();
	# Loop through rows to build feature arrays
	while($get_user->fetch()) {
		if ("anon" != $provider){
			$name = htmlspecialchars($name);
			$users[$userID]["provider"] = $provider;
			$users[$userID]["providerID"] = $providerID;
			$users[$userID]["name"] = $name;
		} else {
			$users[$userID]["provider"] = "anonymous";
			$users[$userID]["providerID"] = null;
			$users[$userID]["name"] = null;
		}
	}
	$get_user->free_result();
	$get_user->close();
	unset($mysqli);
	return $users;
}

function get_user_id($provider, $username, $is_id = false) {
	global $mysqli;
	if ($is_id) {
		$get_user_id = $mysqli->prepare(
			"SELECT userID FROM users
			WHERE provider = ?
			AND	providerID = ?
			LIMIT 0 , 1");
	} else {
		$get_user_id = $mysqli->prepare(
			"SELECT userID FROM users
			WHERE provider = ?
			AND	name = ?
			LIMIT 0 , 1");
	}

	$get_user_id->bind_param('ss', $provider, $username);
	$get_user_id->execute();
	/* bind result variables */
	$get_user_id->bind_result($userID);

	# Loop through rows to build feature arrays
	while($get_user_id->fetch()) {
		$get_user_id->free_result();
		$get_user_id->close();
		unset($mysqli);
		return $userID;
	}
}


function get_licence($licenceID)
{
	global $mysqli;

	$get_licence = $mysqli->prepare(
		"SELECT longName, url FROM licences
		WHERE shortName = ?
		LIMIT 0 , 1");

	$get_licence->bind_param('s',  $licenceID );
	$get_licence->execute();
	/* bind result variables */
	$get_licence->bind_result($longName, $url);

	$html = "";

	# Loop through rows to build feature arrays
	while($get_licence->fetch()) {
		$get_licence->free_result();
		$get_licence->close();
		unset($mysqli);
		$longName = htmlspecialchars($longName);
		$licenceID = htmlspecialchars($licenceID);
		$html .= "<small><a href='{$url}' title='{$longName}'>{$licenceID}</a></small>";
		break;
	}

	return $html;
}

function get_admin_list()
{
	global $mysqli;

	$get_list = $mysqli->prepare("SELECT `benchID`, `inscription` FROM `benches` ORDER BY `benchID` DESC LIMIT 0 , 4096");

	$get_list->execute();
	/* bind result variables */
	$get_list->bind_result($benchID, $inscription);

	$html = "<ul>";

	# Loop through rows to build feature arrays
	while($get_list->fetch()) {
		//get_edit_key
		$bench	= $benchID;
		$key	  = urlencode(get_edit_key($bench));
		$inscrib = nl2br(htmlspecialchars($inscription, ENT_HTML5, "UTF-8", false));
		$html	.= "<li>{$bench} <a href='/edit/{$bench}/{$key}'>{$inscrib}</a></li>";
	}

	$get_list->free_result();
	$get_list->close();
	unset($mysqli);
	return $html .= "</ul>";
}

function get_media_types_html($name = "") {
	global $mysqli;

	$get_media = $mysqli->prepare("SELECT `shortName`, `longName` FROM `media_types` ORDER BY `displayOrder` ASC");

	$get_media->execute();
	/* bind result variables */
	$get_media->bind_result($shortName, $longName);

	$html = "<select name='media_type{$name}' id='media_type{$name}'>";

	$count = 1;

	# Loop through rows to build feature arrays
	while($get_media->fetch()) {
		if ($count == $name) {
			$html .= "<option value='{$shortName}' selected>{$longName}</option>";
		} else {
			$html .= "<option value='{$shortName}'>{$longName}</option>";
		}
		$count++;
	}

	$get_media->free_result();
	$get_media->close();
	unset($mysqli);
	return $html .= "</select>";
}

function get_media_types_array() {
	global $mysqli;

	$get_media_types = $mysqli->prepare("SELECT `shortName`, `longName` FROM `media_types` ORDER BY `displayOrder` ASC");

	$get_media_types->execute();
	/* bind result variables */
	$get_media_types->bind_result($shortName, $longName);

	$media_types_array = array();

	# Loop through rows to build feature arrays
	while($get_media_types->fetch()) {
		$media_types_array[$shortName] = $longName;
	}

	$get_media_types->free_result();
	$get_media_types->close();
	unset($mysqli);
	return $media_types_array;
}

function get_search_geojson($q, $truncated = false) {
	//	If media have been requested
	$media_data = get_all_media(0);

	global $mysqli;

	//	Replace spaces in query with `[[:space:]]*`
	$q = prepare_search_query($q);

		//	Query will be like
		//	SELECT * FROM `benches` WHERE `inscription` REGEXP 'of[[:space:]]*Paul[[:space:]]*[[:space:]]*Willmott'
	$search = $mysqli->prepare(
		"SELECT `benchID`, `latitude`, `longitude`, `inscription`, `added`
		 FROM	`benches`
		 WHERE  `inscription`
		 REGEXP	?
		 AND	 `published` = 1");

	$search->bind_param('s', $q);


	$search->execute();

	/* bind result variables */
	$search->bind_result($benchID, $benchLat, $benchLong, $benchInscription, $added);

	# Build GeoJSON feature collection array
	$geojson = array(
		'type'		=> 'FeatureCollection',
		'features'  => array()
	);
	# Loop through rows to build feature arrays
	while($search->fetch()) {

		# some inscriptions got stored with leading/trailing whitespace
		$benchInscription=trim($benchInscription);

		# if displaying on map need to truncate inscriptions longer than
		# 128 chars and add in <br> elements
		# N.B. this logic is also in get_nearest_benches()
		if ($truncated) {
			$benchInscriptionTruncate = mb_substr($benchInscription,0,128);
			if ($benchInscriptionTruncate !== $benchInscription) {
				$benchInscription = $benchInscriptionTruncate . '…';
			}
		}
		$benchInscription=htmlspecialchars($benchInscription);

		//	Horrible hack to force numeric inscriptions to be strings
		if (is_numeric($benchInscription)) {
			 $benchInscription .= " ";
		}

		if (isset($media_data[$benchID])){
			$mediaFeature = $media_data[$benchID];
		} else {
			$mediaFeature = null;
		}

		$feature = array(
			'id' => $benchID,
			'type' => 'Feature',
			'geometry' => array(
				'type' => 'Point',
				# Pass Longitude and Latitude Columns here
				'coordinates' => array($benchLong, $benchLat)
			),
			# Pass other attribute columns here
			'properties' => array(
				'created_at'   => date_format( date_create($added ), "c" ),
				'popupContent' => $benchInscription,
				'media'        => $mediaFeature
			),
		);
		# Add feature arrays to feature collection array
		array_push($geojson['features'], $feature);
	}

	$search->free_result();
	$search->close();
	unset($mysqli);
	return $geojson;
}

function get_search_results($q, $page=0, $results=20, $soundex=false) {
	global $mysqli;

	$offset = $page * $results;

	//	Replace spaces in query with `[[:space:]]*`
	$q = prepare_search_query($q);

	if ($soundex == false) {
		//	Query will be like
		//	SELECT * FROM `benches` WHERE `inscription` REGEXP 'of[[:space:]]*Paul[[:space:]]*[[:space:]]*Willmott'
		$search = $mysqli->prepare(
			"SELECT `benchID`, `inscription`, `address`
			 FROM	`benches`
			 WHERE  `inscription`
			 REGEXP	?
			 AND	 `published` = 1
			 LIMIT ? , ?");
	} else {
		$search = $mysqli->prepare(
			"SELECT `benchID`, `inscription`, `address`
			 FROM	`benches`
			 WHERE  SOUNDEX(`inscription`) = ?
			 AND	 `published` = 1
			 LIMIT ? , ?");
	}

	$search->bind_param('sii', $q, $offset, $results);

	$search->execute();
	/* bind result variables */
	$search->bind_result($benchID, $benchInscription, $address);

	$results = array();
	# Loop through rows to build feature arrays
	while($search->fetch()) {
		$inscription = htmlspecialchars($benchInscription);
		if($address != null){
			$inscription .= "<br />Location: ". htmlspecialchars($address);
		}
		$results[$benchID] = $inscription;
	}

	$search->free_result();
	$search->close();
	unset($mysqli);
	return $results;
}

function get_search_count($q) {
	global $mysqli;

	//	Replace spaces in query with `[[:space:]]*`
	$q = prepare_search_query($q);

	$search = $mysqli->prepare(
		"SELECT  COUNT(*)
		 FROM   `benches`
		 WHERE  `inscription`
		 REGEXP  ?
		 AND    `published` = true");

	$search->bind_param('s', $q);
	$search->execute();
	$search->bind_result($count);
	$search->fetch();
	$search->free_result();
	$search->close();
	unset($mysqli);

	return $count;
}

function get_duplicates_results() {
	global $mysqli;

	//	Query will be like
	//	SELECT inscription, SOUNDEX(inscription), COUNT(SOUNDEX(inscription)) FROM benches WHERE published=1 GROUP BY SOUNDEX(inscription) HAVING COUNT(SOUNDEX(inscription)) > 1
	$search = $mysqli->prepare(
		"SELECT `inscription`, SOUNDEX(`inscription`), COUNT(SOUNDEX(`inscription`))
		 FROM	`benches`
		 WHERE `published` = 1
		 GROUP BY SOUNDEX(`inscription`)
		 HAVING COUNT(SOUNDEX(`inscription`)) > 1
		 LIMIT 0 , 1024");

	$search->execute();
	/* bind result variables */
	$search->bind_result($inscription, $soundex, $count);

	$results = array();
	# Loop through rows to build feature arrays
	while($search->fetch()) {
		if($soundex != null){
			$inscription = htmlspecialchars($inscription) .	" (" . $count . ")";
		}
		$results[$soundex] = $inscription;
	}

	$search->free_result();
	$search->close();
	unset($mysqli);
	return $results;
}

function get_duplicates_count($inscription) {
	global $mysqli;

	$search = $mysqli->prepare(
		"SELECT  COUNT(*)
		 FROM   `benches`
		 WHERE  SOUNDEX(`inscription`) = SOUNDEX(?)
		 AND    `published` = true");

	$search->bind_param('s', $inscription);
	$search->execute();
	$search->bind_result($count);
	$search->fetch();
	$search->free_result();
	$search->close();
	unset($mysqli);

	return $count;
}

function get_soundex($inscription) {
	global $mysqli;

	$search = $mysqli->prepare(
		"SELECT  SOUNDEX(?)");

	$search->bind_param('s', $inscription);
	$search->execute();
	$search->bind_result($soundex);
	$search->fetch();
	$search->free_result();
	$search->close();
	unset($mysqli);

	return $soundex;
}

function get_bench_count() {
	global $mysqli;

	$result = $mysqli->query("SELECT COUNT(*) FROM `benches` WHERE published = true AND present = true ");
	$row = $result->fetch_row();
	$result->free_result();
	// $result->close();
	unset($mysqli);
	return $row[0];
}

function get_leadboard_benches_html() {
	global $mysqli;

	$get_leaderboard = $mysqli->prepare("
		SELECT users.userID, users.name, users.provider, users.providerID, COUNT(*) AS USERCOUNT
		FROM `benches`
		INNER JOIN users ON benches.userID = users.userID
		WHERE benches.published = TRUE AND benches.present = true
		GROUP by users.userID
		ORDER by USERCOUNT DESC");

	$get_leaderboard->execute();
	$get_leaderboard->bind_result($user_ID, $user_name, $user_provider, $user_providerID, $count);

	$html = "<ul class='leaderboard-list'>";
	while($get_leaderboard->fetch()) {
		$avatar = get_user_avatar($user_provider, $user_providerID, $user_name);
		if (null!=$avatar) {
			$html .= "<li><a href='/user/{$user_ID}/'><img src='{$avatar}' class='avatar' alt=''>$user_name</a> {$count}</li>";
		}
	}
	$get_leaderboard->free_result();
	$get_leaderboard->close();
	unset($mysqli);
	return $html .= "</ul>";
}

function get_leadboard_media_html() {
	global $mysqli;

	$get_leaderboard = $mysqli->prepare("
		SELECT users.userID, users.name, users.provider, users.providerID, COUNT(*) AS USERCOUNT
		FROM `media`
		INNER JOIN users ON media.userID = users.userID
		WHERE users.provider != 'anon'
		GROUP by users.userID
		ORDER by USERCOUNT DESC");

	$get_leaderboard->execute();
	$get_leaderboard->bind_result($user_ID, $user_name, $user_provider, $user_providerID, $count);

	$html = "<ul class='leaderboard-list'>";
	while($get_leaderboard->fetch()) {
		$avatar = get_user_avatar($user_provider, $user_providerID, $user_name);
		if (null!=$avatar) {
			$html .= "<li><a href='/user/{$user_ID}/'><img src='{$avatar}' class='avatar' alt=''>$user_name</a> {$count}</li>";
		}
	}
	$get_leaderboard->free_result();
	$get_leaderboard->close();
	unset($mysqli);
	return $html .= "</ul>";
}

function get_user_bench_list($userID, $page=0, $results=20)
{
	global $mysqli;
	$offset = (int)$page * (int)$results;

	$get_user_list = $mysqli->prepare(
		"SELECT `benchID`, `inscription`, `address`
		 FROM	  `benches`
		 WHERE  `userID` = ?
		 AND	  `published` = 1
		 ORDER BY `benchID` DESC
		 LIMIT ? , ?");

	$get_user_list->bind_param('iii', $userID, $offset, $results);

	$get_user_list->execute();
	$get_user_list->bind_result($benchID, $benchInscription, $address);

	$results = array();
	while($get_user_list->fetch()) {
		$inscription = htmlspecialchars($benchInscription);
		if($address != null){
			$inscription .= "<br />Location: ". htmlspecialchars($address);
		}
		$results[$benchID] = $inscription;
	}

	$get_user_list->free_result();
	$get_user_list->close();
	unset($mysqli);
	return $results;
}

function get_user_bench_count($userID) {
	global $mysqli;

	$search = $mysqli->prepare(
		"SELECT COUNT(*)
		 FROM	  `benches`
		 WHERE  `userID` = ?
		 AND	  `published` = true");

	$search->bind_param('i', $userID);

	$search->execute();
	$search->bind_result($count);
	$search->fetch();
	$search->free_result();
	$search->close();
	unset($mysqli);

	return $count;
}

function get_user_map($userID, $truncated=true, $media=false)
{

	//	If media have been requested
	if($media) {
		$media_data = get_all_media(0);
	} else {
		$media_data = array();
	}

	global $mysqli;
	// $where = "WHERE published = true AND userID = {$userID}";

	$get_benches = $mysqli->prepare(
		"SELECT benchID, latitude, longitude, inscription, published, added FROM benches
		WHERE published = true AND present = true AND userID = ?
		LIMIT 0 , 30000");

	$get_benches->bind_param('i', $userID);
	$get_benches->execute();

	/* bind result variables */
	$get_benches->bind_result($benchID, $benchLat, $benchLong, $benchInscription, $published, $added);

	# Build GeoJSON feature collection array
	$geojson = array(
		'type'		=> 'FeatureCollection',
		'features'  => array()
	);
	# Loop through rows to build feature arrays
	while($get_benches->fetch()) {

		# some inscriptions got stored with leading/trailing whitespace
		$benchInscription=trim($benchInscription);

		# if displaying on map need to truncate inscriptions longer than
		# 128 chars and add in <br> elements
		# N.B. this logic is also in get_nearest_benches()
		if ($truncated) {
			$benchInscriptionTruncate = mb_substr($benchInscription,0,128);
			if ($benchInscriptionTruncate !== $benchInscription) {
				$benchInscription = $benchInscriptionTruncate . '…';
			}
		}

		$benchInscription=nl2br(htmlspecialchars($benchInscription));
		//	Horrible hack to force numeric inscriptions to be strings
		if (is_numeric($benchInscription)) {
			 $benchInscription .= " ";
		}

		if ($media) {
			if (isset($media_data[$benchID])){
				$mediaFeature = $media_data[$benchID];
			} else {
				$mediaFeature = null;
			}
		} else {
			$mediaFeature = null;
		}

		$feature = array(
			'id' => $benchID,
			'type' => 'Feature',
			'geometry' => array(
				'type' => 'Point',
				# Pass Longitude and Latitude Columns here
				'coordinates' => array($benchLong, $benchLat)
			),
			# Pass other attribute columns here
			'properties' => array(
				'created_at'   => date_format( date_create($added ), "c" ),
				'popupContent' => $benchInscription,
				'media'        => $mediaFeature
			),
		);
		# Add feature arrays to feature collection array
		array_push($geojson['features'], $feature);
	}

	$get_benches->free_result();
	$get_benches->close();
	unset($mysqli);
	return $geojson;
}

function get_bounding_box_benches_list($lat_ne, $lng_ne, $lat_sw, $lng_sw, $page=0, $limit=20) {
	global $mysqli;

	$get_benches = $mysqli->prepare(
		"SELECT `benchID`, `latitude`, `longitude`, `inscription`, `address`
		FROM `benches`
		WHERE
		`latitude`  BETWEEN ? AND ? AND
		`longitude` BETWEEN ? AND ? AND
		`published` = true AND `present` = true
		LIMIT ? , ?");

	$get_benches->bind_param('dddddd', $lat_sw, $lat_ne, $lng_sw, $lng_ne, $page, $limit);
	$get_benches->execute();

	/* bind result variables */
	$get_benches->bind_result($benchID, $benchLat, $benchLong, $benchInscription, $address);

	$results = array();
	while($get_benches->fetch()) {
		$inscription = htmlspecialchars($benchInscription);
		if($address != null){
			$inscription .= "<br />Location: ". htmlspecialchars($address);
		}
		$results[$benchID] = $inscription;
	}

	$get_benches->free_result();
	$get_benches->close();
	unset($mysqli);
	return $results;
}

function get_bounding_box_benches_count($lat_ne, $lng_ne, $lat_sw, $lng_sw) {
	global $mysqli;

	$get_benches = $mysqli->prepare(
		"SELECT COUNT(*)
		FROM `benches`
		WHERE
		`latitude`  BETWEEN ? AND ? AND
		`longitude` BETWEEN ? AND ? AND
		`published` = true AND `present` = true");

	$get_benches->bind_param('dddd', $lat_sw, $lat_ne, $lng_sw, $lng_ne);
	$get_benches->execute();
	$get_benches->bind_result($count);
	$get_benches->fetch();
	$get_benches->free_result();
	$get_benches->close();
	unset($mysqli);

	return $count;
}

function merge_benches($originalID, $duplicateID) {
	global $mysqli;

	//	Unpublish duplicate bench
	$merge_two_benches = $mysqli->prepare(
		"UPDATE `benches` SET `published` = '0' WHERE `benches`.`benchID` = ?;");
	$merge_two_benches->bind_param('i', $duplicateID);
	$merge_two_benches->execute();
	$merge_two_benches->free_result();
	$merge_two_benches->close();

	//	Redirect duplicate bench
	$merge_two_benches = $mysqli->prepare(
		"INSERT INTO `merged_benches` (`benchID`, `mergedID`) VALUES (?, ?);");
	$merge_two_benches->bind_param('ii', $duplicateID, $originalID);
	$merge_two_benches->execute();
	$merge_two_benches->free_result();
	$merge_two_benches->close();

	//	Merge photos
	$merge_two_benches = $mysqli->prepare(
		"UPDATE `media` SET `benchID` = ? WHERE `media`.`benchID` = ?;");
	$merge_two_benches->bind_param('ii', $originalID, $duplicateID);
	$merge_two_benches->execute();
	$merge_two_benches->free_result();
	$merge_two_benches->close();
	unset($mysqli);

	return true;
}

function get_merged_bench($benchID) {
	global $mysqli;
	$get_merge_from_bench = $mysqli->prepare(
		"SELECT mergedID FROM merged_benches
		WHERE benchID = ?
		LIMIT 0 , 1");

	$get_merge_from_bench->bind_param('i',  $benchID);
	$get_merge_from_bench->execute();
	/* bind result variables */
	$get_merge_from_bench->bind_result($mergedID);

	# Loop through rows to build feature arrays
	while($get_merge_from_bench->fetch()) {
		$get_merge_from_bench->free_result();
		$get_merge_from_bench->close();
		unset($mysqli);
		return $mergedID;
	}
}

function get_tags() {
	global $mysqli;
	$get_tags = $mysqli->prepare(
		"SELECT `tagID`, `tagText` FROM `tags` WHERE 1;");
	$get_tags->execute();

	$get_tags->bind_result($tagID, $tagText);

	$results = array();
	while($get_tags->fetch()) {
		//	https://select2.org/data-sources/arrays
		$results[] = array("id" => $tagID, "text" => $tagText);
	}

	$get_tags->free_result();
	$get_tags->close();
	unset($mysqli);
	return $results;
}

function get_tagID($tagText) {
	$tagText = urldecode($tagText);
	global $mysqli;
	$get_tags = $mysqli->prepare(
		"SELECT `tagID` FROM `tags`
		WHERE `tagText` LIKE ?
		LIMIT 0 , 1");

	$get_tags->bind_param('s',  $tagText);
	$get_tags->execute();

	$get_tags->bind_result($tagID);

	$id = null;
	while($get_tags->fetch()) {
		$id = $tagID;
	}

	$get_tags->free_result();
	$get_tags->close();
	unset($mysqli);
	return $id;
}

function get_benches_from_tag_id($tagID) {
	global $mysqli;
	$get_benches = $mysqli->prepare(
		"SELECT `benchID` FROM `tag_map`
		WHERE `tagID` = ?");

	$get_benches->bind_param('i',  $tagID);
	$get_benches->execute();

	$get_benches->bind_result($benchID);

	$results = array();
	while($get_benches->fetch()) {
		$results[] = $benchID;
	}

	$get_benches->free_result();
	$get_benches->close();
	unset($mysqli);
	return $results;
}

function get_benches_from_tag_text($tagText, $page=0, $results=20)
{
	$offset = $page * $results;

	$tagID    = get_tagID($tagText);
	if (null == $tagID) {
		return array();
	}
	$benches  = get_benches_from_tag_id($tagID);
	if(empty($benches)){
		return array();
	}
	$benchIDs = implode(",", $benches);
	global $mysqli;

	$get_benches = $mysqli->prepare(
		"SELECT `benchID`, `inscription`, `address`
		 FROM   `benches`
		 WHERE  `benchID` IN ({$benchIDs})
		 AND    `published` = 1
		 LIMIT ? , ?");

	$get_benches->bind_param('ii', $offset, $results);

	$get_benches->execute();
	$get_benches->bind_result($benchID, $benchInscription, $address);

	$results = array();
	while($get_benches->fetch()) {
		$inscription = htmlspecialchars($benchInscription);
		if($address != null){
			$inscription .= "<br />Location: ". htmlspecialchars($address);
		}
		$results[$benchID] = $benchInscription;
	}

	$get_benches->free_result();
	$get_benches->close();
	unset($mysqli);
	return $results;
}

function get_bench_tag_count($tagText) {
	$tagID    = get_tagID($tagText);

	global $mysqli;

	$search = $mysqli->prepare(
		"SELECT COUNT(`mapID`)
		 FROM `tag_map`
		 INNER JOIN `benches` ON (benches.benchID = tag_map.benchID)
		 WHERE  tag_map.tagID = ?  AND benches.published = true AND benches.present = true ");

	$search->bind_param('i', $tagID);

	$search->execute();
	$search->bind_result($count);
	$search->fetch();
	$search->free_result();
	$search->close();
	unset($mysqli);

	return $count;
}


function save_tags($benchID, $tags) {
	// this function needs to work for when a bench is added or edited
	// when a bench is edited that may mean that the number of tags
	// increases, or decreases to as few as zero
	// easiest way to deal with this is to remove all entries for the
	// bench then add whatever tags were passed
	global $mysqli;
	$mysqli->begin_transaction();
	try {
		$remove_tags = $mysqli->prepare("DELETE FROM `tag_map` WHERE `benchID`=?");
		$remove_tags->bind_param('i', $benchID);
		$remove_tags->execute();
		$remove_tags->free_result();
		$remove_tags->close();
		
		foreach ($tags as $tagID) {
			$save_tag = $mysqli->prepare(
				"INSERT INTO `tag_map` (`mapID`, `benchID`, `tagID`)
				VALUES                 (NULL,     ?,         ?)");
			$save_tag->bind_param('ii', $benchID, $tagID);
			$save_tag->execute();
			$save_tag->free_result();
			$save_tag->close();
		}
		$mysqli->commit();
	} catch (mysqli_sql_exception $exception) {
		$mysqli->rollback();
	}
	unset($mysqli);
	return true;
}

function get_tags_from_bench($benchID) {
	global $mysqli;

	$get_tags = $mysqli->prepare(
		"SELECT tags.tagText, tags.tagID
		 FROM `tag_map`
		 INNER JOIN tags ON (tags.`tagID` = tag_map.`tagID`)
		 WHERE `benchID` = ?");
	$get_tags->bind_param('i', $benchID);
	$get_tags->execute();
	$get_tags->bind_result($tag, $tagID);

	$tags = array();
	while($get_tags->fetch()) {
		$tags[] = array("tagText"=>$tag, "tagID"=>$tagID);
	}

	$get_tags->free_result();
	$get_tags->close();
	unset($mysqli);
	return $tags;
}

function get_bench_from_sha1( $sha1 ) {
	global $mysqli;
	$get_bench = $mysqli->prepare(
		"SELECT `benchID`
		 FROM   `media`
		 WHERE  `sha1` = ?");

	$get_bench->bind_param('s', $sha1);

	$get_bench->execute();
	$get_bench->bind_result($benchID);

	$id = 0;
	while($get_bench->fetch()) {
		$id = $benchID;
	}

	$get_bench->free_result();
	$get_bench->close();
	unset($mysqli);
	return $id;
}