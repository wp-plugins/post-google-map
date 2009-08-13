<?php
//code to generate Google Map
$post_id = $post;
if (is_object($post_id)){
    $post_id = $post_id->ID;
    $title=get_the_title($post_id->ID);
    $link=get_permalink($post_id->ID);
}
//echo "$post_id".$post_id;
$gmp_arr = get_post_meta($post_id, 'gmp_arr', false);

If (is_array($gmp_arr)) {
    for ($row = 0; $row < count($gmp_arr); $row++)
    {
		$y=$y+1;
		$html="<table width=100%><tr>";
        $iTitle=scrub_data($gmp_arr[$row]["gmp_title"]);
        $iDesc=$gmp_arr[$row]["gmp_description"];
        $iDesc_Show=$gmp_arr[$row]["gmp_desc_show"];
        if ($iTitle==""){
            $iTitle=$title;
        }
        $html.="<td colspan=2 valign=top><b><a href=".$link.">".$iTitle."</a></b>";
        if ($iDesc=="" && $iDesc_Show=="on"){
            if ($ran!=1){
                $query = "SELECT post_excerpt,post_content FROM wp_posts WHERE ID = " . $post_id;
                $result = $wpdb->get_results($query, ARRAY_A);
                $excerpt = $result[0]['post_excerpt'];
                $content = $result[0]['post_content'];
				//clean up desc
				$content = scrub_data($content);
				$ran=1;
            }
			$iDesc=$excerpt;
			if ($iDesc==""){
                $iDesc=getWords($content, 5);
			}
		}
        if ($iDesc!=""){
            //echo $iDesc."<br>";
			$iDesc = scrub_data($iDesc);
			//echo $iDesc;
			$html.="<br>".$iDesc;
        }
        $html.="</td></tr><tr>";

        $thumb=gmp_get_post_image($post_id, 60);
        if ($thumb!=""){
            $html.= "<td><a href=".$link.">".$thumb."</a></td>";
        }
        $html.="<td valign=top>".$gmp_arr[$row]["gmp_address1"]."<br>".$gmp_arr[$row]["gmp_city"]." ".$gmp_arr[$row]["gmp_state"]." ".$gmp_arr[$row]["gmp_zip"]."</td></tr></table>";
        //set markers if coords exist
        if (($gmp_arr[$row]["gmp_long"]!="") && ($gmp_arr[$row]["gmp_lat"]!="")){
            $x=$x+1;
            $JS.="var point".$rn." = new GPoint(".$gmp_arr[$row]["gmp_lat"].", ".$gmp_arr[$row]["gmp_long"].");";
            $JS.="var icon".$rn." = new GIcon();";
            $JS.="icon".$rn.".image = '".$imgpath.$gmp_arr[$row]["gmp_marker"]."';";
            $JS.="icon".$rn.".iconAnchor = new GPoint(15, 35);";
            $JS.="var marker".$rn."".$x." = new GMarker(point".$rn.",icon".$rn.");";

            $JS.="GEvent.addListener(marker".$rn."".$x.", 'click', function() {";
                //$JS.="map.openInfoWindowHtml(marker".$x.".getPoint(), html".$x.");";
                $JS.="location.href='".$link."';";
            $JS.="});";

            $JS.="GEvent.addListener(marker".$rn."".$x.", 'mouseover', function() {";
                $JS.="var info".$rn." = document.getElementById('map-info".$rn."');";
                $JS.="info".$rn.".innerHTML = '".str_replace('[google-map]', '', $html)."';";
				if ($bm==""){
					$Default_HTML=$html;
					$bm=1;
				}
            $JS.="});";

            $JS.="map".$rn.".addOverlay(marker".$rn."".$x.");";
            $JS.="bounds".$rn.".extend(marker".$rn."".$x.".getPoint());";

        }
    }
}
?>