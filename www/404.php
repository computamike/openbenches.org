<?php
$broken_images = array(	"f0ce9dd7f357bebaf86609fec57b48394385da0b",
								"5bcbaa4e7f2e30810c2bb81125b57dbcd957577f",
								"ab2cfeb3fbcd0e53b55c0f28dcb5aaecc7887f47",
								"ba85b02ef55f23802b77e44a9895373849f8e8b7",
								"607aacd26ffe46460e8d64025d53af064d09dbbb",
								"19a831e25b0dee061e8e68c98d0670ccf1338ab5",
								"ba85b02ef55f23802b77e44a9895373849f8e8b7",
								"607aacd26ffe46460e8d64025d53af064d09dbbb",
								"95cfe3a312e89f1fffe4ee58a6af25e471927f8b");
$broken_image = $broken_images[array_rand($broken_images,1)];
$image = get_image_cache($broken_image, 600)
?>
<h2>404 - Bench Not Found</h2>
<img src="<?php echo $image; ?>" class="proxy-image" alt="Photograph of a bench with a removed plaque">
<?php
	include("searchform.php");
	include("footer.php");
