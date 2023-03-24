# OpenBenches

![A bench in a park, birds fly up above. In the background is a tree.](https://openbenches.org/images/icons/icon-72x72.png)

https://OpenBenches.org/ - an open data repository for memorial benches.

## Contributing

All contributions are welcome.  Before making a pull request, please:

1. Raise a new issue describing the problem and how you intend to fix it.
2. Submit a Pull Request referencing the Issue.

## Open Data API

You can get all the data, or partial data, from the API.  Data is returned in [geoJSON](http://geojson.org/) format and has the following structure:

```JSON
{
	"type": "FeatureCollection",
	"features": [{
		"id": 1234657,
		"type": "Feature",
		"geometry": {
			"type": "Point",
			"coordinates": [0.1234, 5.678]
		},
		"properties": {
			"created_at": "2021-06-05T12:27:36+01:00",
			"popupContent": "IN LOVING MEMORY OF\nBOB AND\nJANE",
			"media": [{
				"URL": "\/image\/3f786850e387550fdab836ed7e6dc881de23001b",
				"mediaID": 123456789,
				"licence": "CC BY-SA 4.0",
				"media_type": "inscription",
				"sha1": "3f786850e387550fdab836ed7e6dc881de23001b",
				"user": 6143,
				"username": "edent",
				"userprovider": "twitter",
				"width": 4096,
				"height": 3072
			}, {
				"URL": "\/image\/89e6c98d92887913cadf06b2adb97f26cde4849b",
				"mediaID": 43803,
				"licence": "CC BY-SA 4.0",
				"media_type": "bench",
				"sha1": "89e6c98d92887913cadf06b2adb97f26cde4849b",
				"user": 123456780,
				"username": "edent",
				"userprovider": "twitter",
				"width": 4096,
				"height": 3072
			}]
		}
	}]
}
```

### Benches
* All Bench Data
	* `https://openbenches.org/api/v1.0/data.json/`
	* That last `/` is *required*.
* Specific Bench
	* `https://openbenches.org/api/v1.0/data.json/?bench=123`
* Geographic Area (Haversine)
	* `https://openbenches.org/api/v1.0/data.json/?latitude=51.234&longitude=-1.234&radius=20&results=5`
	* `latitude` and `longitude` in [WGS 84](https://en.wikipedia.org/wiki/World_Geodetic_System).
	* `radius` in Kilometres.
	* `results` maximum number of benches returned. By defaults, 20 results are returned.
* Tags
	* `https://openbenches.org/api/v1.0/data.json/?tagText=cat`
	* Returns all the benches with a specific tag.
* Inscriptions
	* By default, the inscriptions are truncated to 128 characters.
	* To get the full inscriptions, append `&truncated=false`
	* `https://openbenches.org/api/v1.0/data.json/?truncated=false`
* Formats
	* By default, the JSON starts with `var benches = `
	* To get pure JSON, append `&format=raw`
	* `https://openbenches.org/api/v1.0/data.json/?bench=123&format=raw`
* Media
	* By default, the API doesn't return media.
	* To get media, append `&media=true`

### Tags
* All available folksonomy tags
	* `https://openbenches.org/api/v1.0/tags.json/`
	* That last `/` is *required*.
	* Returned in a format suitable for [Select2](https://select2.org/data-sources/arrays).

### Users
* All User Data
	* `https://openbenches.org/api/v1.0/users.json/`
	* That last `/` is *required*.
* Specific User
	* `https://openbenches.org/api/v1.0/users.json/?userID=1234`
* Formats
	* By default, the JSON starts with `var users = `
	* To get pure JSON, append `&format=raw`
	* `https://openbenches.org/api/v1.0/users.json/?userID=1234&format=raw`

### Alexa Skill
There is an Alexa Skill which allows you to interact with the site via your voice. This functionality is provided by an API.
* How many benches have been uploaded
	* `https://openbenches.org/api/v1.0/alexa.json/?count`
* The latest bench
	* `https://openbenches.org/api/v1.0/alexa.json/?latest`
* Details of a random bench
	* `https://openbenches.org/api/v1.0/alexa.json/?random`
* Formats
	* By default, the JSON starts with `var alexa = `
	* To get pure JSON, append `&format=raw`
	* `https://openbenches.org/api/v1.0/users.json/?format=raw&count`

## Running Locally

This is a simple PHP and MySQL website. No need for node, complicated deploys, or spinning up containerised virtual machines in the cloud.

### Requirements

* PHP 7 or greater.
* MySQL 5.5 or greater with innodb.
* ImageMagick 6.9.4-10 or greater.

### External APIs

You will need to sign up to some external API providers:

* Map display requires a [Stadia Maps account](https://stadiamaps.com/)
* Reverse Geocoding requires an [OpenCage API key](https://geocoder.opencagedata.com/)
* Flickr Import requires a [Flickr API key](https://www.flickr.com/services/api/)
* Tweeting requires a [Twitter Developer API key](https://apps.twitter.com/)
* Text detection requires a [Google Cloud Vision API key](https://cloud.google.com/vision/)
* Image resizing and caching requires a [CloudImage.io account](https://www.cloudimage.io). (But note: this requires your development webserver to be accessible from the internet)
* **Optional** Login requires a free [Auth0.com](https://auth0.com/) account.
* **Optional** Satellite Map display requires a free [Mapbox](https://www.mapbox.com/) account

Add them to `config.php.example` - rename that to `config.php`

### Database Structure

In the `/database/` folder you'll find a sample database.  All text fields are `utf8mb4_unicode_ci` because we live in the future now.

Hopefully, the tables are self explanatory:

#### Benches

* `benchID`
* `latitude`
* `longitude`
* `address` text representation generated by reverse geocoding. For example "10 Downing Street, London SW1A 2AA, United Kingdom"
* `inscription` the text written on the bench
* `description` placeholder. Might be used for comments about the bench.
* `present` if a bench has been physically removed, this can be set to false.
* `published` set to FALSE if the bench has been deleted
* `added` datetime of when the bench was uploaded to the site
* `userID` foreign key

#### Users

Originally we were going to force people to sign in with Twitter / Facebook / GitHub. But that discourages use - so users are now pseudo-anonymous. Hence this weird structure!

* `userID`
* `provider` could be Twitter, GitHub, Facebook etc.
* `providerID` user ID number on the provider's service.  Anonymous users stores their IP address.
* `name` their display name. Anonymous users stores the time they added a bench.

#### Media

We store the original image - smaller images are rendered dynamically.

Media storage can be complicated. Storing thousands of images in a single directory can cause problems on some systems. To get around this, we calculate the [SHA1 hash](https://en.wikipedia.org/wiki/SHA-1) of each image. The image is stored in a subdirectory based on the hash.  For example, if the hash is `1A2B3C`, the file will be stored in `/photos/1/A/1A2B3C.jpg`

* `mediaID`
* `benchID`
* `userID`
* `sha1` A hash of the file.
* `importURL` If the image was imported from an external source - like Flickr.
* `licence` The default is `CC BY-SA 4.0`, imported images may be different.
* `media_type` We allow different types of photo - in the future, we might have other types of media.
* `width` The image's width in pixels.
* `height` The image's width in pixels.
* `datetime` The date and time the image was created - based on EXIF metadata.
* `make` The make of camera which took the photo - based on EXIF metadata.
* `model` The model of camera which took the photo - based on EXIF metadata.

#### Media Types

At the moment, we only accept photos - of the inscription, the bench, the view from the bench, a panorama, and a VR photosphere.

* `shortName` Internal ID.
* `longName` Displayed to the user.
* `displayOrder` When rendering a form in HTML, this determines the order they are presented in.

#### Licences

* `shortName` Internal ID.
* `longName` Displayed to the user.
* `url` For more information.

#### Tagging

Benches can be given multiple "tags". For example "cat" if the bench commemorates a feline, or "beach" if the bench is at the seaside.

Tagging uses the [Toxi structure](http://howto.philippkeller.com/2005/04/24/Tags-Database-schemas/).

* `tags` contains:
	* `tagID` a unique ID
	* `tagText` the displayed text

* `tag_map` contains:
	* `mapID` a unique ID
	* `tagID` the ID of a tag
	* `benchID` the ID of a bench

Tags are hard-coded in the database and can't be added or edited by regular users.

## Open Source Licenses

Everything we do builds on someone else's hard work.

* OpenBenches data are made available under the [Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)](https://creativecommons.org/licenses/by-sa/4.0/).
* The code powering the website is [MIT](https://opensource.org/licenses/MIT).
* All photos uploaded by users are [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/).
* [Benches from Bath](https://github.com/BathHacked/banes-geographic-data/blob/master/banes_park_seats_and_benches.geojson) are [OGL](http://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/) and Powered by [Bath: Hacked](https://www.bathhacked.org/).
* Logo template by [Creative Mania](https://thenounproject.com/term/park/923893/) [CC BY](http://creativecommons.org/licenses/by/3.0/us/).
* Twitter integration by [CodeBird](https://github.com/jublonet/codebird-php) [GPL v3](https://www.gnu.org/licenses/gpl-3.0.en.html).
* Maps by [Leaflet](https://github.com/Leaflet/Leaflet) [BSD 2-clause "Simplified" License](https://opensource.org/licenses/BSD-2-Clause).
* Cluster library by [Leaflet](https://github.com/Leaflet/Leaflet.markercluster) [MIT](https://github.com/Leaflet/Leaflet.markercluster/blob/master/MIT-LICENCE.txt).
* Map tiles by [MapBox](https://www.mapbox.com/).
* GPS logo by [Chinnaking](https://thenounproject.com/term/gps/1050710/) [CC BY](http://creativecommons.org/licenses/by/3.0/us/).
* Panoramic Visualiser by [Pannellum](https://pannellum.org/) [MIT](https://opensource.org/licenses/MIT).
* JavaScript EXIF reader & image preview by [JavaScript Load Image](https://github.com/blueimp/JavaScript-Load-Image/) ([MIT](https://github.com/blueimp/JavaScript-Load-Image/blob/master/LICENSE.txt)).
* Login services provided by [Auth0.com's PHP library](https://github.com/auth0/auth0-PHP) [MIT](https://github.com/auth0/auth0-PHP/blob/master/LICENSE.txt).
* CSS based on [PicniCSS](https://github.com/franciscop/picnic) [MIT](https://github.com/franciscop/picnic/blob/master/LICENSE) (chosen mostly because we like picnic benches!)
* Tagging library by [Select2](https://select2.org/) [MIT](https://github.com/select2/select2/blob/master/LICENSE.md)
* Animated OCR icon by [Loading.io](https://loading.io/spinner/magnify/-searching-for-loading-icon) [CC BY](http://creativecommons.org/licenses/by/3.0/us/).
* Mastodon Library by [Eleirbag89/MastodonBotPHP](https://github.com/Eleirbag89/MastodonBotPHP) [MIT](https://github.com/Eleirbag89/MastodonBotPHP/blob/master/LICENSE)

And thanks to the many contributors who have improved this codebase.
