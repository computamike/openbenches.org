<footer>
	<?php require("sponsor.php");?>
	<?php require("menu.php");?>
	<div class="footerIcons" itemscope itemtype="https://schema.org/Organization">
		<a itemprop="sameAs" href="https://twitter.com/openbenches" title="Twitter"><img src="/images/svg/footer_twitter.svg" alt="twitter" loading="lazy"/></a>
	  <a itemprop="sameAs" href="https://github.com/openbenches/openbenches.org" title="Github"><img src="/images/svg/footer_github.svg" alt="github" loading="lazy"/></a>
	</div>
	Thanks to <a href="https://krystal.uk/">Krystal Hosting</a>
	<br>
	<a itemprop="license"
		rel="license"
		href="https://creativecommons.org/licenses/by-sa/4.0/"><img src="/images/cc/cc-by-sa.svg" id="cc-by-sa-logo" alt="Creative Commons Attribution Share-alike" loading="lazy"/></a>
	<br>
		Made with 💖 by<br>
	<a itemprop="creator" href="https://shkspr.mobi/blog">Terence Eden</a> and
	<a itemprop="creator" href="https://mymisanthropicmusings.org.uk/">Elizabeth Eden</a>.
</footer>
<script>
function geoFindMe() {
	var output = document.getElementById("gpsButton");

	var gpsIcon = L.icon({
		iconUrl: '/images/gps.png',
		iconSize: [200, 200],
	});

	if (!navigator.geolocation){
		output.innerHTML = "GPS is not supported by your device";
		return;
	}

	function success(position) {
		var latitude  = position.coords.latitude;
		var longitude = position.coords.longitude;

		output.innerHTML = '🔄 Update my location';
		L.marker([latitude, longitude], {opacity:0.5, icon: gpsIcon, zIndexOffset: -100000}).addTo(map);
		map.setView([latitude, longitude], 15);
	}

	function error() {
		output.innerHTML = "🚫 Unable to retrieve your location";
	}

	output.innerHTML = "🛰️ Locating…";

	navigator.geolocation.getCurrentPosition(success, error);
}
</script>
<script>
if ("serviceWorker" in navigator) {
	if (navigator.serviceWorker.controller) {
		console.log("[PWA Builder] active service worker found, no need to register");
	} else {
		// Register the service worker
		navigator.serviceWorker.register("/sw.js?cache=2020-02-10", {
			scope: "./"
		})
		.then(function (reg) {
			console.log("[PWA Builder] Service worker has been registered for scope: " + reg.scope);
		});
	}
}
</script>
</body>
</html>
