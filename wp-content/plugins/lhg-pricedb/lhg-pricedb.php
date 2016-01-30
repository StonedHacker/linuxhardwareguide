<?php

/*
Plugin Name: LHG PriceDB
Plugin URI: http://www.linux-hardware-guide.com
Description: Interface to the LHG price data base
Version: 0.1
Author: Captain Pike https://github.com/cptpike
Author URI: http://www.linux-hardware-guide.com
License: Proprietary
*/

require_once(plugin_dir_path(__FILE__).'/includes/lhg.conf');
# sets $lhg_price_db
# e.g.
# $lhg_price_db = new wpdb("wordpress", "PASSWORD", "lhgpricedb", "192.168.1.2");


require_once(plugin_dir_path(__FILE__).'includes/lhg_widget_supplier_overview.php');
require_once(plugin_dir_path(__FILE__).'includes/lhg_widget_featured_article.php');
require_once(plugin_dir_path(__FILE__).'includes/lhg_shop_button.php');
require_once(plugin_dir_path(__FILE__).'includes/lhg_pricedb_functions.php');
require_once(plugin_dir_path(__FILE__).'includes/lhg_scan_overview.php');
require_once(plugin_dir_path(__FILE__).'includes/lhg_menu_tags.php');
require_once(plugin_dir_path(__FILE__).'includes/lhg_autocreate.php');
require_once(plugin_dir_path(__FILE__).'includes/lhg_sanity_checks.php');
require_once(plugin_dir_path(__FILE__).'includes/lhg_shortcodes.php');



if (!is_admin()) return;
if (!current_user_can('activate_plugins')) return;



//echo "SADDR: ".$_SERVER['SERVER_ADDR'];

//require_once(
require_once(plugin_dir_path(__FILE__).'includes/lhg_pricedb_update.php');

add_action ("admin_menu", "lhg_create_menu");
function lhg_create_menu () {
	add_menu_page ("LHG Tools", "LHG Tools",'publish_posts', "lhg_pricedb_update",'lhg_menu_settings_page',plugins_url('/images/lhg_logo_16x16.png',__FILE__) );

        add_submenu_page( "lhg_pricedb_update","Overview Articles", "LHG Articles", "publish_posts",'lhg_menu_settings_page','lhg_menu_settings_page');
	add_submenu_page( "lhg_pricedb_update","Overview Hardware Scans", "HW Scans", "publish_posts",'lhg_menu_hw_scans','lhg_menu_hw_scans');
	add_submenu_page( "lhg_pricedb_update","Overview Tags", "Tags", "publish_posts",'lhg_menu_tags','lhg_menu_tags');
	add_submenu_page( "lhg_pricedb_update","DB Sanity Checks", "Sanity", "publish_posts",'lhg_sanity_checks','lhg_sanity_checks');

}



// Add a column to the edit post list
add_filter( 'manage_edit-post_columns', 'lhg_db_add_new_columns');
/**
 * Add new columns to the post table
 *
 * @param Array $columns - Current columns on the list post
 */
function lhg_db_add_new_columns( $columns ) {

        global $region;

        //print "REG: $region";
        $shop_ids = lhg_return_shop_ids( $region );

        //print "Shop IDs:<br>";
        //print_r($shop_ids);


        if ($shop_ids != "")
        foreach ($shop_ids as $shopid ) {
        	$sid = $shopid->id;

                //print_r("-- $sid <br>");
                $shopname = lhg_db_get_shop_name($sid);

                //ToDo: currently skip Amazon
                if (!strpos($shopname, "mazon") > 0 ) {
	                //echo "SN: $shopname";
        	        $column_meta  = array( 'shop-ID-'.$sid => $shopname );

			$columns = array_slice( $columns, 0, 3, true ) +
        	        	$column_meta +
			        array_slice( $columns, 3, NULL, true );
		}
	}

        return $columns;
}

// Add action to the manage post column to display the data
add_action( 'manage_posts_custom_column' , 'lhg_db_custom_columns' );
/**
 * Display data in new columns
 *
 * @param  $column Current column
 *
 * @return Data for the column
 */
function lhg_db_custom_columns( $column ) {
	global $post;
        $oos='<font color="red"><b>out of stock</b></font>';
        //echo "COL: $column";
	switch ( substr($column,0,8) ) {
		case 'shop-ID-':
                        //for which shop ID are we showing information?
                        $shopid=substr($column,8);

                        //check if shop_article_id is defined
			$number = lhg_db_does_article_id_exist( $post->ID, $shopid);

                        if ($number > 1) {
                	        $metaData = "ERROR: too many results";
                                break;
			}

			$shop_article_id = lhg_db_get_shop_article_id( $post->ID, $shopid);


                        if ($number == 1) {
        	                $metaData = lhg_db_get_price( $post->ID, $shopid);
				$id = lhg_db_get_id( $post->ID, $shopid);
				$difftime = time() - lhg_db_get_time( $post->ID, $shopid);

                                $stime = number_format($difftime / (60*60*24),0);
                                if ($stime == 0) $timestring = "today";
                                if ($stime == 1) $timestring = "yesterday";
                                if ($stime > 1) $timestring = $stime. " days ago";

                                if ($shop_article_id == "NOT_AVAILABLE") {
	      			echo '<div class="inline-edit-group">
                                        Not available<br>checked '.$timestring.'<br>
					<a href="admin.php?page=lhg_pricedb_update&mode=update&sid='.$shopid.'&id='.$id.'&pid='.$post->ID.'">
					update</a></div>';
				}else{

                                	if ($metaData == "") $metaData = "(no price found)";
	      				echo '<div class="inline-edit-group">
                                        	'.$shop_article_id.'<br>'.$metaData.'<br>
						<a href="admin.php?page=lhg_pricedb_update&mode=update&sid='.$shopid.'&id='.$id.'&pid='.$post->ID.'">
						update</a></div>';

                                }

                                break;
			}

                        if ($number == 0) {
	                        $metaData = "not in DB";

	      			echo '<div class="inline-edit-group">
					<a href="admin.php?page=lhg_pricedb_update&mode=create&sid='.$shopid.'&pid='.$post->ID.'">
					create</a></div>';

	      			echo '<div class="inline-edit-group">
					(<a href="admin.php?page=lhg_pricedb_update&mode=notavail&sid='.$shopid.'&pid='.$post->ID.'">Not available</a>)</div>';
                                break;
			}



			break;
		case 'metade':
			$metaData = get_post_meta( $post->ID, 'price-amazon.de', true );
			break;

	}


        if ($metaData == "out of stock") $metaData = $oos;
        if ($metaData == "") $metaData = "not found";

        //Redcoon
        // if (substr($column,0,8) == "shop-ID-") echo $metaData;

}

// Register the column as sortable
//function lhg_db_register_sortable_columns( $columns ) {
//    $columns['meta-ID-1'] = 'Redcoon';
//    return $columns;
//}

//add_filter( 'manage_edit-post_sortable_columns', 'lhg_db_register_sortable_columns' );


function lhg_db_get_price( $postid, $shopid) {
    //connect to external DB to request price information
	/**
	 * Instantiate the wpdb class to connect to your second database, $database_name
	 */

        //Debug mode!
        //$postid = 3701;

        // $lhg_price_db->print_error();
	/**
	 * Use the new database object just like you would use $wpdb
	 */
        //echo "1";

        global $lhg_price_db;
        $sql = "SELECT shop_last_price FROM `lhgprices` WHERE lhg_article_id = ".$postid." AND shop_id = ".$shopid;
	$result = $lhg_price_db->get_var($sql);
        //echo $results;
        //var_dump($results);
        //echo "ERR: ".var_dump($lhg_price_db->last_query) ."ERREND<br>";
        // $lhg_price_db->print_error();

	//echo "R1:". $result["shop_last_price"];
	//echo "R1:". $result["shop_last_price"];
	//echo "R3:". $result;
	return $result; //s -> shop_last_price;

}

function lhg_db_does_article_id_exist ( $postid, $shopid) {
    //check, if article ID for this shop has been defined already

    global $lhg_price_db;

    $sql = "SELECT COUNT(*) FROM `lhgprices` WHERE lhg_article_id = ".$postid." AND shop_id = ".$shopid;
    $result = $lhg_price_db->get_var($sql);

    //var_dump($result);
        //echo "ERR: ".var_dump($lhg_price_db->last_query) ."ERREND<br>";
        // $lhg_price_db->print_error();

	//echo "R1:". $result["shop_last_price"];
	//echo "R1:". $result["shop_last_price"];
	//echo "R3:". $result;
	return $result; //s -> shop_last_price;

}

function lhg_db_get_shop_article_id ( $postid, $shopid) {

    global $lhg_price_db;

    $sql = "SELECT shop_article_id FROM `lhgprices` WHERE lhg_article_id = ".$postid." AND shop_id = ".$shopid;
    $result = $lhg_price_db->get_var($sql);

    return $result; 

}

function lhg_db_get_shop_price ( $postid, $shopid) {

    global $lhg_price_db;

    $sql = "SELECT shop_last_price FROM `lhgprices` WHERE lhg_article_id = ".$postid." AND shop_id = ".$shopid;
    $result = $lhg_price_db->get_var($sql);

    $error = $lhg_price_db->last_error;
    if ($error != "") var_dump($error);

    return $result; 

}

function lhg_db_get_shop_url ( $postid, $shopid) {

        //echo "<br>SID: $shopid";
        //echo "<br>PID: $postid";

    global $lhg_price_db;

    $sql = "SELECT shop_url FROM `lhgprices` WHERE lhg_article_id = \"".$postid."\" AND shop_id = ".$shopid;
    $result = $lhg_price_db->get_var($sql);

    return $result; 

}

function lhg_db_get_shop_name ( $shopid ) {

	global $lhg_price_db;


        $sql = "SELECT name FROM `lhgshops` WHERE id = \"".$shopid."\"";
    	$result = $lhg_price_db->get_var($sql);

        //var_dump($result);

        return $result;


}

function lhg_db_update ( $value, $db_table, $lhg_db_id) {

    global $lhg_price_db;
    //echo "VAL: $value";

    $sql = "";
    if ($db_table == "shop_article_id")
	    $sql = "UPDATE lhgprices SET `shop_article_id` = \"".$value."\" WHERE id = ".$lhg_db_id;

    //echo "<br>SQL: $sql";
    $result = $lhg_price_db->query($sql);
    //echo "<br>Res: $result";
    //echo "<br>LQ: ".var_dump($lhg_price_db->last_query) ."ERREND<br>";
    //echo "<br>LER: ".var_dump($lhg_price_db->last_error) ."ERREND<br>";

    $error = $lhg_price_db->last_error;
    if ($error != "") var_dump($error);

    return $result; 

}

function lhg_db_create_entry ( $post_id, $shop_id, $shop_article_id ) {

    global $lhg_price_db;
    //echo "VAL: $value";

    $sql = "INSERT INTO lhgprices (lhg_article_id, shop_id, shop_article_id)
    			VALUES ('$post_id', '$shop_id', '$shop_article_id')";

    //echo "<br>SQL: $sql";
    $result = $lhg_price_db->query($sql);
    //echo "<br>Res: $result";
    //echo "<br>LQ: ".var_dump($lhg_price_db->last_query) ."ERREND<br>";
    //echo "<br>LER: ".var_dump($lhg_price_db->last_error) ."ERREND<br>";

    $error = $lhg_price_db->last_error;
    if ($error != "") var_dump($error);

    return $result; 

}



function lhg_db_get_id ( $postid, $shopid) {

    global $lhg_price_db;

    $sql = "SELECT id FROM `lhgprices` WHERE lhg_article_id = ".$postid." AND shop_id = ".$shopid;
    $result = $lhg_price_db->get_var($sql);

    return $result; 

}

function lhg_db_get_time ( $postid, $shopid) {

    global $lhg_price_db;

    $sql = "SELECT last_update FROM `lhgprices` WHERE lhg_article_id = ".$postid." AND shop_id = ".$shopid;
    $result = $lhg_price_db->get_var($sql);

    return $result; 

}

function lhg_get_usbid( $postid) {

    global $lhg_price_db;

    $sql = "SELECT usbids FROM `lhgtransverse_posts` WHERE postid_com = ".$postid;
    $result = $lhg_price_db->get_var($sql);

    return $result; 

}

function lhg_get_pciid( $postid) {

    global $lhg_price_db;

    $sql = "SELECT pciids FROM `lhgtransverse_posts` WHERE postid_com = ".$postid;
    $result = $lhg_price_db->get_var($sql);

    return $result; 

}

function lhg_get_idstrg( $postid) {

    global $lhg_price_db;

    $sql = "SELECT idstring FROM `lhgtransverse_posts` WHERE postid_com = ".$postid;
    $result = $lhg_price_db->get_var($sql);

    return $result; 

}

add_action ('edit_post', 'lhg_save_id_widget_data' );
add_action ('save_post', 'lhg_save_id_widget_data_add' );

function lhg_save_id_widget_data_add( $postid ) {

	if ( wp_is_post_revision( $postid ) ) return;
        // i.e. will already be handeled by edit_post
        // otherwise
	lhg_save_id_widget_data( $postid );

}



function lhg_save_id_widget_data( $postid ) {
    #echo "HERE!\n";
    #echo "PID: $postid \n";

    if ($lang == "de") return;


    global $lhg_price_db;

    #$sql = "SELECT idstring FROM `lhgtransverse_posts` WHERE postid_com = ".$postid;
    #$result = $lhg_price_db->get_var($sql);
    #
    #return $result;

    #write data (currently no check if updated)
    #
    # write USB ID
    $value_usb = $_POST['product-library-usbid'];
    $value_pci = $_POST['product-library-pciid'];
    $value_strg = $_POST['product-library-idstrg'];
    #echo "Val: $value \n";
    $sql = "UPDATE lhgtransverse_posts SET `usbids` = \"%s\" WHERE postid_com = %s";
    $safe_sql = $lhg_price_db->prepare($sql, $value_usb, $postid);
    $result = $lhg_price_db->query($safe_sql);
    #echo "SQL: $result";

    # write PCI ID
    $sql = "UPDATE lhgtransverse_posts SET `pciids` = \"%s\" WHERE postid_com = %s";
    $safe_sql = $lhg_price_db->prepare($sql, $value_pci, $postid);
    $result = $lhg_price_db->query($safe_sql);

    # write PCI ID
    $sql = "UPDATE lhgtransverse_posts SET `idstring` = \"%s\" WHERE postid_com = %s";
    $safe_sql = $lhg_price_db->prepare($sql, $value_strg, $postid);
    $result = $lhg_price_db->query($safe_sql);

}

function lhg_create_article_image( $image_url , $image_title ) {

  $id = getmypid();

  #title -> file name
  $image_title = str_replace(" ","_",$image_title);
  $image_title = str_replace("/","_",$image_title);
  $image_title = str_replace("&","_",$image_title);
  $image_title = str_replace("(","_",$image_title);
  $image_title = str_replace(")","_",$image_title);
  $image_title = str_replace("�","_",$image_title);
  $image_title = preg_replace('/[^A-Za-z0-9\-]/', '', $image_title);
  $image_title = sanitize_file_name($image_title);
  $image_title = str_replace("__","_",$image_title);
  $image_title = str_replace("__","_",$image_title);

  #print "IURL: $image_url";
  #print "<br>Title: $image_title";


  $local_file = "/tmp/image.".$id.".jpg";

  file_put_contents($local_file, fopen($image_url, 'r'));

  $im = @imagecreatefromjpeg($local_file);
  list($width, $height) = getimagesize($local_file);

  #print "<br>BxH = $width x $height";

  if ( ($width != 130) or ($height != 130) ){
  	#rescaling necessary !!
        $w_new = 130;
        $h_new = 130;
	$newimage = imagecreatetruecolor($w_new, $h_new);
        $backgroundColor = imagecolorallocate($newimage, 255, 255, 255);
	imagefill($newimage, 0, 0, $backgroundColor);

        if ($width > 130) {
           	#print "<br>Scale width: ".$w_new/$width;
                $scaling = $w_new/$width;
                if ($height * $scaling > 130 ) $scaling = $h_new/height;

                #print "Scaling: ".$scaling;
                #imagecopyresized($newimage, $im, ($w_new - ($width*$scaling))/2, ($h_new - ($height*$scaling))/2, 0, 0, $width*$scaling, $height*$scaling,
                #                               $width , $height  );
                imagecopyresampled($newimage, $im, ($w_new - ($width*$scaling))/2, ($h_new - ($height*$scaling))/2, 0, 0, $width*$scaling, $height*$scaling,
                                               $width , $height  );
	}

	$im = $newimage;

  }

/*

    if (!$im) {
        $im  = imagecreatetruecolor(150, 30);
        $bgc = imagecolorallocate($im, 255, 255, 255);
        $tc  = imagecolorallocate($im, 0, 0, 0);

        imagefilledrectangle($im, 0, 0, 150, 30, $bgc);

        imagestring($im, 1, 5, 5, 'Fehler beim Öffnen von ' . $imgname, $tc);
    }
*/

    $picdir = "/var/www/wordpress";
    $created_gif_url = "/wp-uploads/autoimage/".$image_title.".gif";
    imagegif($im, $picdir.$created_gif_url);
    imagedestroy($im);

    return $created_gif_url;

}




?>