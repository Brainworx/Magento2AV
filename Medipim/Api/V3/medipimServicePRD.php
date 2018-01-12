<?php
//script to be called from cli or via url
/*
#this is an sh script - above line to point exe to correct interpreter -
#The script is to run magento cron to get data from medipim..
/usr/bin/php -c /data/sites/conf/apovitabe/php/php.ini /site/www/Medipim/Api/V3/medipimServicePRD.php 0 true >> /site/www/var/log/medipim_cron.log
/usr/bin/php -c /data/sites/conf/apovitabe/php/php.ini /site/www/Medipim/Api/V3/medipimServicePRD.php 1 true >> /site/www/var/log/medipim_cron.log
/usr/bin/php -c /data/sites/conf/apovitabe/php/php.ini /site/www/Medipim/Api/V3/medipimServicePRD.php 2 true >> /site/www/var/log/medipim_cron.log
/usr/bin/php -c /data/sites/conf/apovitabe/php/php.ini /site/www/Medipim/Api/V3/medipimServicePRD.php 3 true >> /site/www/var/log/medipim_cron.log
/usr/bin/php -c /data/sites/conf/apovitabe/php/php.ini /site/www/Medipim/Api/V3/medipimServicePRD.php 4 true >> /site/www/var/log/medipim_cron.log
#local
http://192.168.56.101/Magento2AV/Medipim/Api/V3/medipimServicePRD.php?sync=1&syncphoto=1
*/
//setting the current dir to be able to use relative paths
chdir(__DIR__);
echo "dir: ".__DIR__.' - ';
require_once 'MedipimApiV3Client.php';
require_once 'MedipimApiV3Error.php';

try{
	
	$client = new MedipimApiV3Client(123,'IGuh829DevvUZYVwNnTDTvFPkLdm08EhGcUG72Y20peYhStZ2Ugj7AnsRTXZgf8g');
	
	
	date_default_timezone_set("Europe/Brussels");
	echo date('Y-m-d H:i:s')." (".time().")Starting sync".PHP_EOL;
	
	/*
	 * get input from url ************************************************************************************************
	 * - p line nr of roductcatsto sync to load from file medipimsync_productcatstosync
	 * - 1/0 sync photo's
	 */
	$syncphoto=true;
	$arg_count = $_SERVER['argc'];
	if($arg_count != 3){
		$inputsync = $_GET["sync"];
		if($inputsync!=null){
			$productcatsIDtosync = $inputsync;
			$inputphoto = $_GET["syncphoto"];
			if($inputphoto!=null){
				$syncphoto=$inputphoto;
			}
		}else{
			echo "No sync argument provided";
			die;
		}
	}else{
		$productcatsIDtosync=$_SERVER['argv'][1];//0=php script 1=first param ....
		$syncphoto=$_SERVER['argv'][2]; // sync photo true or false
	}
	/*
	 * Load timestamp last sync for this product cat id in medipimsync_productcatstosync*********************************
	 */
	$updatedSince_handle = fopen("../config/medipimsync_updatedSince.csv","r");//keep unix timestamp of last sync per type
	$updatedSince="";
	$buffer;
	$productcatsIDStosync = array();
	$temp;
	while (($buffer = fgets($updatedSince_handle)) !== FALSE ) {
		$temp = explode(";",$buffer);
		$productcatsIDStosync[$temp[0]]=$temp[1];
		if($temp[0]==$productcatsIDtosync){
			$updatedSince = $temp[1];
		}
	}
	fclose($updatedSince_handle);
	
	$updatedSince = str_replace(array("\n", "\r"), ' ', $updatedSince);
	
	/*
	 * read categories **************************************************************************************************
	 * Get all categories from Medipim
	 * Add them in an array with key CAT id
	 */
	$r = $client->get("/v3/public-categories/all");
	
	$catList = $r['results'];//json_decode($r);
	$meta = $r['meta'];
	$catArray = array();
	
	$parent_cats=0;
	foreach($catList as $key=>$categorie){
		$catArray[$categorie['id']]=$categorie;
	}
	
	unset($r);
	
	/*
	 * read products *****************************************************************************************************
	 */ 
	$productOnline = "";
	/*
	 * load categories to sync*********************************************************
	 */
	$productcatstosync_handle = fopen("../config/medipimsync_productcatstosync.csv","r");
	$allcategories;
	
	$buffer;
	$rowcounter=0;
	while (($data = fgetcsv($productcatstosync_handle, 0, ",")) !== FALSE ) {
		if($rowcounter == $productcatsIDtosync){
			$allcategories=$data;
	        echo ' '.$productcatsIDtosync.' ';
			break;
		}
		$rowcounter++;
	}
	fclose($productcatstosync_handle);
	unset($data);
	if(empty($allcategories)){
		echo "No Cats found in config file".PHP_EOL; 
		die;
	}
	/*
	 * read product cnks to sync *********************************************"**********
	 * from files per cat (dieet.csv, baby.csv ....)
	 * group per 1024 to avoid throthle issues
	 */
	//prepare filter
	$cnks_groups = array(); // group cnks to limit the array to 1024 values
	$cnks = array();
	$cnks_details_groups = array();
	$cnks_details = array();
	$teller_cnks=0;
	$newgroup=true;
	$previous_group=array();
	//$max=0;
	//Group cnks to sync into small groups of 1024
	foreach($allcategories as $key => $maincategory){
		if (($handle = fopen("../config/".$maincategory.".csv", "r")) !== FALSE) {
			echo "-adding cnks for ".$maincategory;
			while (($data = fgetcsv($handle, 0, ";")) !== FALSE ) {
				if(!$newgroup && $teller_cnks%1024==0){
					$newgroup=true;
					$cnks_groups[] = $cnks;
					$cnks=array();
					$cnks_details_groups[] = $cnks_details;
					$previous_group = $cnks_details;
					$cnks_details=array();
				}
				if($data[0]=='CNK'){
					continue;
				}
				if(array_key_exists($data[0],$cnks_details) || array_key_exists($data[0],$previous_group)){
					continue;
				}
				$teller_cnks++;
				$num = count($data);
				$cnks[]=$data[0];
				$newgroup=false;
				//main cat
				$cnks_details[$data[0]]['cat'] = $maincategory;
				$cnks_details[$data[0]]['price'] = $data[3];
				$cnks_details[$data[0]]['discount'] = $data[5];
			}		
			fclose($handle);		
		}
	}
	$cnks_groups[] = $cnks;
	$cnks_details_groups[] = $cnks_details;
	unset($cnks);
	unset($cnks_details);
	
	echo 'will process '.$teller_cnks.' records -- ';
	
	//cat translations
	$allcategories_nl = array("dieet"=>"Dieet","baby"=>"Baby","haarhuid"=>"Haar en huid","homeo"=>"Homeo","kruiden"=>"Kruiden",
			"mond"=>"Mond","reis"=>"Reis","sport"=>"Sport","voedingssupplementen"=>"Voedingssupplementen");
	$allcategories_fr = array("dieet"=>"Diète","baby"=>"Bébé","haarhuid"=>"Cheveux et peau","homeo"=>"Homéo","kruiden"=>"Herbe",
			"mond"=>"Bouche","reis"=>"Voyage","sport"=>"Sport","voedingssupplementen"=>"Aliments et supplément");
	$allcategories_en = array("dieet"=>"Diet","baby"=>"Baby","haarhuid"=>"Hair and skin","homeo"=>"Homeo","kruiden"=>"Herb",
			"mond"=>"Mouth","reis"=>"Travel","sport"=>"Sport","voedingssupplementen"=>"Dietary supplements");
	
	$category_used = array();
	//get products
	$total_products=0;
	$size = 250;
	
	$newProdData="";
	$newProdData_nl="";
	$newProdData_fr="";
	$newProdData_en="";
	
	//keep track of sync time
	$now=time();
	/*
	 * Prepare the file to store the output from Medipim
	 */
	//strore previous synced products
	$loc="../import/products".$productcatsIDtosync.".csv";
	if(file_exists($loc)){
		rename($loc,"../import/products".$productcatsIDtosync."_".date("Y_m_d")."_".$now.".csv");
	}
	$prodfile = fopen($loc,"w");
	fputs( $prodfile, "\xEF\xBB\xBF" );
	$titles = "sku,store_view_code,attribute_set_code,product_type,categories,product_websites,name,description,short_description,weight,product_online,tax_class_name,visibility,price,special_price,special_price_from_date,special_price_to_date,url_key,meta_title,meta_keywords,meta_description,base_image,base_image_label,small_image,small_image_label,thumbnail_image,thumbnail_image_label,created_at,updated_at,new_from_date,new_to_date,display_product_options_in,map_price,msrp_price,map_enabled,gift_message_available,custom_design,custom_design_from,custom_design_to,custom_layout_update,page_layout,product_options_container,msrp_display_actual_price_type,country_of_manufacture,additional_attributes,qty,out_of_stock_qty,use_config_min_qty,is_qty_decimal,allow_backorders,use_config_backorders,min_cart_qty,use_config_min_sale_qty,max_cart_qty,use_config_max_sale_qty,is_in_stock,notify_on_stock_below,use_config_notify_stock_qty,manage_stock,use_config_manage_stock,use_config_qty_increments,qty_increments,use_config_enable_qty_inc,enable_qty_increments,is_decimal_divided,website_id,related_skus,crosssell_skus,upsell_skus,additional_images,additional_image_labels,custom_options,configurable_variations,configurable_variation_prices,configurable_variation_labels,bundle_price_type,bundle_sku_type,bundle_price_view,bundle_weight_type,bundle_values";
	fwrite($prodfile,$titles.PHP_EOL);
	
	/*
	 * Load product data from Medipim ******************************************
	 * Get data from medipim per group of 1024 cnks
	 * remove new line chars from output as html contains them
	 */
	foreach ($cnks_groups as $key => $cnks){
		$quantity_medipim = 0;
		$processed = 0;
		$pagecounter = 0;
		do{
			$cnks=[ "3367596", "2881886"];
			$subfilter=[array("cnk"=>$cnks), array("updatedSince"=>$updatedSince)];
			$filter = array("filter"=>array("and"=>$subfilter),"page"=>array("no"=>$pagecounter,"size"=>$size));
			//$filter = array("filter"=>array("cnk"=>$cnks),"page"=>array("no"=>$pagecounter,"size"=>$size));
			
			$r = $client->post("/v3/products/search", $filter);
			
			$prodList = $r['results'];//json_decode($r);
			$meta = $r['meta'];
			$quantity_medipim=$meta['total'];
			
			$newProdData="";
			$newProdData_nl="";
			$newProdData_fr="";
			$newProdData_en="";
			
			$offset=$meta['page']['offset'];//no -> offset			
			
			foreach($prodList as $product){
				$newProdData .= $product['cnk'];//sku;
				$newProdData_nl .= $product['cnk'];//sku;
				$newProdData_fr .= $product['cnk'];//sku;
				$newProdData_en .= $product['cnk'];//sku;
				$newProdData .=","."";//store_view_code;
				$newProdData_nl .=","."nl";//store_view_code;					
				$newProdData_fr .=","."fr";//store_view_code;
				$newProdData_en .=","."en";//store_view_code;
				$newProdData .=","."Default";//.attribute_set_code; --?
				$newProdData_nl .=","."Default";//.attribute_set_code; --?
				$newProdData_fr .=","."Default";//.attribute_set_code; --?
				$newProdData_en .=","."Default";//.attribute_set_code; --?
				$newProdData .=","."simple";//product_type;
				$newProdData_nl .=","."simple";//product_type;
				$newProdData_fr .=","."simple";//product_type;
				$newProdData_en .=","."simple";//product_type;
				$categories = "";
				$categories_fr = "";
				$categories_en = "";
	            $inputcatnl="";
	            $inputcatfr="";
	            $inputcaten="";
				$meta_keywords=$product['name']['nl'];
				$meta_keywords_fr=$product['name']['fr'];
				$meta_keywords_en=$product['name']['en'];
				$previous="";
				foreach($product['publicCategories'] as $cat){ //eventueel apbCategory checken als deze null, opgelet is ook soms onbekend
					$_categorie = "";
					$_categorie_fr = "";
					$_categorie_en = "";
					if(empty($cat )){
						continue;
					}
					$previous=$cat['name']['nl'];
					$cat_id = $cat['id'];
					while(!empty($cat_id)){
						if(!empty($_categorie)){
							$_categorie = "/".$_categorie;
							$_categorie_fr = "";
							$_categorie_en = "";
						}
						$inputcatnl=preg_replace('/\|/','-',$catArray[$cat_id]['name']['nl']);
						$_categorie = $inputcatnl.$_categorie;
						$meta_keywords .= ','.$inputcatnl;
						if(!empty($catArray[$cat_id]['name']['fr'])){
	                        $inputcatfr  = preg_replace('/\|/','-',$catArray[$cat_id]['name']['fr']);
							$_categorie_fr = $inputcatfr;
							$meta_keywords_fr .= ','.$inputcatfr;
						}else{
							$_categorie_fr = $inputcatnl.$_categorie;
						}
						if(!empty($catArray[$cat_id]['name']['en'])){
	                        $inputcaten  = preg_replace('/\|/','-',$catArray[$cat_id]['name']['en']);
							$_categorie_en = $inputcaten;
							$meta_keywords_en .= ','.$inputcaten;
						}else{
							$_categorie_en = $inputcatnl.$_categorie;
						}
						if(!array_key_exists($cat_id,$category_used)){
							$category_used[$cat_id]=$cat_id.';"'.$inputcatnl.'";"'.$inputcatfr.'";"'.$inputcaten.'"';
						}
						$cat_id = $catArray[$cat_id]['parent'];
					}
					if(!empty($_categorie)){
						$parts = explode("/",$_categorie);
						$partcnt = count($parts);
						while($partcnt>1){
							if(!empty($categories)){
								$categories .= "|";
							}
							$categories.="Default Category/";
							for($j=0;$j<$partcnt-1;$j++){
								$categories.=$parts[$j];
								if($j<$partcnt-2){
									$categories.="/";
								}
							}
							$partcnt--;
						}
						if(!empty($categories)){
							$categories .= "|";
						}
						$categories .= "Default Category/".$_categorie;
					}
				}
				//fallback in case medipim has no cat
				if(empty($categories)){
					$categories="Default Category/Andere/".$allcategories_nl[$cnks_details_groups[$key][$product['cnk']+0]['cat']];
					$category_used[0]="0".";".$allcategories_nl[$cnks_details_groups[$key][$product['cnk']+0]['cat']].";".$allcategories_fr[$cnks_details_groups[$key][$product['cnk']+0]['cat']].";".$allcategories_en[$cnks_details_groups[$key][$product['cnk']+0]['cat']];
					
				}
				$newProdData .=",".'"Default Category|'.$categories.'"';
				$newProdData_nl .=",";
				$newProdData_fr .=",";//.'"'.$categories_fr.'"';
				$newProdData_en .=",";//.'"'.$categories_en.'"';
				$newProdData .=","."base";//product_websites;
				$newProdData_nl .=",";//."base";
				$newProdData_fr .=",";//."base";
				$newProdData_en .=",";//."base";
				$newProdData .=",".'"'.$product["name"]["nl"].'"'; 
				$newProdData_nl .=",";
				if(!empty($product["name"]["fr"])){
					$newProdData_fr .=",".'"'.$product["name"]["fr"].'"';
				}else{
					$newProdData_fr .=",";//.'"'.$product["name"]["nl"].'"';
				}
				if(!empty($product["name"]["en"])){
					$newProdData_en .=",".'"'.$product["name"]["en"].'"';
				}else{
					$newProdData_en .=",";//.'"'.$product["name"]["nl"].'"';
				}
				$full_description ="";
				$full_description_fr ="";
				$full_description_en ="";
				foreach($product['descriptions'] as $description){
					if(!empty($description)){
						if(in_array("nl",$description['locales']) && in_array("public",$description['targetGroups'])){
							if($description['type']=="full_description"){
								$full_description .= $description['content']['nl']['html'];
							}
							if($description['type']=="composition"){
								$full_description .= '<h3>Samenstelling</h3>'.$description['content']['nl']['html'];
							}
							if($description['type']=="properties"){
								$full_description .= '<h3>Eigenschappen</h3>'.$description['content']['nl']['html'];
							}
							if($description['type']=="indication"){
								$full_description .= '<h3>Indicatie</h3>'.$description['content']['nl']['html'];
							}
							if($description['type']=="contra_indication"){
								$full_description .= '<h3>Contra-Indicatie</h3>'.$description['content']['nl']['html'];
							}
							if($description['type']=="usage"){
								$full_description .= '<h3>Gebruik</h3>'.$description['content']['nl']['html'];
							}
						}
						if(in_array("fr",$description['locales']) && in_array("public",$description['targetGroups'])){
							if($description['type']=="full_description"){
								$full_description_fr .= $description['content']['fr']['html'];
							}
							if($description['type']=="composition"){
								$full_description_fr .= '<h3>Composition</h3>'.$description['content']['fr']['html'];
							}
							if($description['type']=="properties"){
								$full_description_fr .= '<h3>Propriétés</h3>'.$description['content']['fr']['html'];
							}
							if($description['type']=="contra_indication"){
								$full_description_fr .= '<h3>Contra-Indication</h3>'.$description['content']['fr']['html'];
							}
							if($description['type']=="usage"){
								$full_description_fr .= '<h3>Usage</h3>'.$description['content']['fr']['html'];
							}
						}
						if(in_array("en",$description['locales']) && in_array("public",$description['targetGroups'])){
							if($description['type']=="full_description"){
								$full_description_en .= $description['content']['en']['html'];
							}
							if($description['type']=="composition"){
								$full_description_en .= '<h3>Composition</h3>'.$description['content']['en']['html'];
							}
							if($description['type']=="properties"){
								$full_description_en .= '<h3>Properties</h3>'.$description['content']['en']['html'];
							}
							if($description['type']=="contra_indication"){
								$full_description_en .= '<h3>Contra-Indication</h3>'.$description['content']['en']['html'];
							}
							if($description['type']=="usage"){
								$full_description_en .= '<h3>Usage</h3>'.$description['content']['en']['html'];
							}
						}
					}
				}	
				$newProdData .=",".'"'.preg_replace(array('/\n/','/"/','/\|/'),array('',"'","-"),$full_description).'"';
				$newProdData_nl .=",";
				$newProdData_fr .=",".'"'.preg_replace(array('/\n/','/"/','/\|/'),array('',"'","-"),$full_description_fr).'"';			
				$newProdData_en .=",".'"'.preg_replace(array('/\n/','/"/','/\|/'),array('',"'","-"),$full_description_en).'"';
				$newProdData .=",".'"'.preg_replace(array('/\n/','/"/','/\|/'),array('',"","-"),$product['short_description']['nl'].'"').'"';
				$newProdData_nl .=",";
				$newProdData_fr .=",".'"'.preg_replace(array('/\n/','/"/','/\|/'),array('',"","-"),$product['short_description']['fr'].'"').'"';
				$newProdData_en .=",".'"'.preg_replace(array('/\n/','/"/','/\|/'),array('',"","-"),$product['short_description']['en'].'"').'"';
				$newProdData .=",".$product['weight'];
				$newProdData_nl .=",";
				$newProdData_fr .=",";//.$product['weight'];
				$newProdData_en .=",";//.$product['weight'];
				if($product['status']=='active' && !(empty($product['publicPrice'])||$product['publicPrice']==0)){
					$productOnline="1";
					$newProdData .=","."1";//product_online; product status. 1 value means Enabled and 2 - Disabled.
					$newProdData_nl .=",";
					$newProdData_fr .=",";//."1";//product_online;
					$newProdData_en .=",";//."1";//product_online;
				}else{
					$productOnline="0";
					$newProdData .=","."2";//product_online; product status. 1 value means Enabled and 2 - Disabled.
					$newProdData_nl .=","."2";
					$newProdData_fr .=","."2";//product_online;
					$newProdData_en .=","."2";//product_online;
				}
				$vat = $product['vat'];
				if(empty($vat)){
					echo '/---Error: VAT empty---/';
					$vat=21;
					$newProdData .=","."tax-21";//tax_class_name; 
					$newProdData_nl .=","."tax-21";//tax_class_name; 
					$newProdData_fr .=","."tax-21";//tax_class_name; 
					$newProdData_en .=","."tax-21";//tax_class_name; //."tax-21";
				}else{
					$newProdData .=","."tax-".$product['vat'];//tax_class_name; 
					$newProdData_nl .=","."tax-".$product['vat'];
					$newProdData_fr .=","."tax-".$product['vat'];//."tax-".$product['vat'];
					$newProdData_en .=","."tax-".$product['vat'];//."tax-".$product['vat'];
				}
				$newProdData .=",".'"Catalogus, Zoeken"';//visibility;
				$newProdData_nl .=",";
				$newProdData_fr .=",";//.'"Catalog, Search"';
				$newProdData_en .=",";//.'"Catalog, Search"';
				
				//calculate price based on provided discount
				$price = round(($product['publicPrice']+($product['publicPrice']*$cnks_details_groups[$key][$product['cnk']+0]['discount']))/100,2);
				
				//given public price is including vat, like magento expects
				//$price = round($price/(1+$vat/100),2);
				$newProdData .=",".$product['publicPrice']/100;//price; 
				$newProdData_nl .=",";
				$newProdData_fr .=",";//.$price;//price; 
				$newProdData_en .=",";//.$price;//price; 
				$newProdData .=",".$price;//.special_price;
				$newProdData_nl .=",";
				$newProdData_fr .=",";//.special_price;
				$newProdData_en .=",";//.special_price;
				$newProdData .=",";//.special_price_from_date;	
				$newProdData_nl .=",";
				$newProdData_fr .=",";//.special_price_from_date;
				$newProdData_en .=",";//.special_price_from_date;
				$newProdData .=",";//.special_price_to_date;
				$newProdData_nl .=",";
				$newProdData_fr .=",";//.special_price_to_date;
				$newProdData_en .=",";//.special_price_to_date;
				$newProdData .=",".strtolower(preg_replace(array('/[^a-zA-Z0-9 -]/', '/[ -]+/', '/^-|-$/'),   array('', '-', ''), ($product['name']['nl']))); //url-key
				$newProdData .=",".'"'.$product['name']['nl'].'"';//meta_title;
				$newProdData_nl .=",";
				$newProdData_nl .=",";
				//using 1 url-key for all languages -- better to set <link rel="alternate" href="example.com/it/about-us" hreflang="it-it" />  in header 
				//TODO https://support.google.com/webmasters/answer/189077?hl=en
				//https://moz.com/learn/seo/hreflang-tag
				$newProdData_fr .=",";//.strtolower(preg_replace(array('/[^a-zA-Z0-9 -]/', '/[ -]+/', '/^-|-$/'),   array('', '-', ''), ($product['name']['nl'])));
				if(!empty($product['name']['fr'])){
					$newProdData_fr .=",".'"'.$product['name']['fr'].'"';//meta_title;
				}else{
					$newProdData_fr .=",";//.'"'.$product['name']['nl'].'"';//meta_title;
				}
				$newProdData_en .=",";//.strtolower(preg_replace(array('/[^a-zA-Z0-9 -]/', '/[ -]+/', '/^-|-$/'),   array('', '-', ''), ($product['name']['nl'])));
				if(!empty($product['name']['en'])){
					$newProdData_en .=",".'"'.$product['name']['en'].'"';//meta_title;
				}else{
					$newProdData_en .=",";//.'"'.$product['name']['nl'].'"';//meta_title;	
				}
				//$newProdData .=",".strtolower(preg_replace(array('/[^a-zA-Z0-9 -]/', '/[ -]+/', '/^-|-$/'),   array('', '-', ''), remove_accent($product['name']['nl'])));
				//preg_replace('/[^A-Za-z0-9]/', "", str_replace(' ', '-', $product['name']['nl']));//url_key;
			
				$newProdData .=",".'"'.preg_replace('/"/','',$meta_keywords).'"';//meta_keywords;
				$newProdData_nl .=",";
				$newProdData_fr .=",".'"'.preg_replace('/"/','',$meta_keywords_fr).'"';//meta_keywords;
				$newProdData_en .=",".'"'.preg_replace('/"/','',$meta_keywords_en).'"';//meta_keywords;
				$newProdData .=",";//.meta_description;
				$newProdData_nl .=",";
				$newProdData_fr .=",";//.meta_description;
				$newProdData_en .=",";//.meta_description;
				
				$base_image = "";
				$base_image_label  ="";
				if($syncphoto){
					foreach ($product['photos'] as $photo){
						if(in_array("public",$photo['targetGroups'])){
							$base_image = $photo['formats']['medium'];
							$base_image_label = $photo['type'];
						}
					}
				}
				$newProdData .=",".$base_image;
				$newProdData .=",".$base_image_label;
				$newProdData_nl .=",";
				$newProdData_nl .=",";
				$newProdData_fr .=",";//.$base_image;
				$newProdData_fr .=",";//.$base_image_label;
				$newProdData_en .=",";//.$base_image;
				$newProdData_en .=",";//.$base_image_label;
				
				$newProdData .=",".$base_image;//.small_image;
				$newProdData .=",";//.small_image_label;
				$newProdData .=",".$base_image;//.thumbnail_image;
				$newProdData .=",";//.thumbnail_image_label;
				$newProdData .=",".date("d.m.Y H:m",$product['meta']['createdAt']);
				$newProdData .=",".date("d.m.Y H:m",$product['meta']['updatedAt']);
				$newProdData .=",";//.new_from_date;
				$newProdData .=",";//.new_to_date;
				$newProdData .=",";//.display_product_options_in;
				$newProdData .=",";//.map_price;
				$newProdData .=",";//.msrp_price;
				$newProdData .=",";//.map_enabled;
				$newProdData .=",";//.gift_message_available;
				$newProdData .=",";//.custom_design;
				$newProdData .=",";//.custom_design_from;
				$newProdData .=",";//.custom_design_to;
				$newProdData .=",";//.custom_layout_update;
				$newProdData .=",";//page_layout;
				$newProdData .=",";//product_options_container;
				$newProdData .=",";//msrp_display_actual_price_type;
				$newProdData .=",";//.country_of_manufacture;
				$newProdData .=",";//.additional_attributes;
				$newProdData .=","."100";//qty;
				$newProdData .=","."0";//out_of_stock_qty;
				$newProdData .=","."0";//use_config_min_qty;
				$newProdData .=","."0";//is_qty_decimal;
				$newProdData .=","."0";//allow_backorders;
				$newProdData .=","."0";//use_config_backorders;
				$newProdData .=","."1";//min_cart_qty;
				$newProdData .=","."0";//use_config_min_sale_qty;
				$newProdData .=","."50";//max_cart_qty; TODO: ask input
				$newProdData .=","."0";//use_config_max_sale_qty;
				$newProdData .=",".$productOnline;//s_in_stock;
				$newProdData .=","."0";//notify_on_stock_below;
				$newProdData .=","."0";//use_config_notify_stock_qty;
				$newProdData .=","."0";//manage_stock;
				$newProdData .=","."0";//use_config_manage_stock;
				$newProdData .=","."0";//use_config_qty_increments;
				$newProdData .=","."0";//qty_increments;
				$newProdData .=","."0";//use_config_enable_qty_inc;
				$newProdData .=","."0";//enable_qty_increments;
				$newProdData .=","."0";//is_decimal_divided;
				$newProdData .=","."1";//website_id; TODO check
				$newProdData .=",";//.related_skus;
				$newProdData .=",";//.crosssell_skus;
				$newProdData .=",";//.upsell_skus;
				$newProdData .=",";//.additional_images;
				$newProdData .=",";//.additional_image_labels;
				$newProdData .=",";//.custom_options;
				$newProdData .=",";//.configurable_variations;
				$newProdData .=",";//.configurable_variation_prices;
				$newProdData .=",";//.configurable_variation_labels;
				$newProdData .=",";//.bundle_price_type;
				$newProdData .=",";//.bundle_sku_type;
				$newProdData .=",";//.bundle_price_view;
				$newProdData .=",";//.bundle_weight_type;
				$newProdData .=",";//.bundle_values;
				$newProdData .=PHP_EOL;
				
				$newProdData_nl .=",";//.small_image;
				$newProdData_nl .=",";//.small_image_label;
				$newProdData_nl .=",";//.thumbnail_image;
				$newProdData_nl .=",";//.thumbnail_image_label;
				$newProdData_nl .=",";//.date("d.m.Y H:m",$product['meta'].['createdAt']);
				$newProdData_nl .=",";//.date("d.m.Y H:m",$product['meta'].['updatedAt']);
				$newProdData_nl .=",";//.new_from_date;
				$newProdData_nl .=",";//.new_to_date;
				$newProdData_nl .=",";//.display_product_options_in;
				$newProdData_nl .=",";//.map_price;
				$newProdData_nl .=",";//.msrp_price;
				$newProdData_nl .=",";//.map_enabled;
				$newProdData_nl .=",";//.gift_message_available;
				$newProdData_nl .=",";//.custom_design;
				$newProdData_nl .=",";//.custom_design_from;
				$newProdData_nl .=",";//.custom_design_to;
				$newProdData_nl .=",";//.custom_layout_update;
				$newProdData_nl .=",";//page_layout;
				$newProdData_nl .=",";//product_options_container;
				$newProdData_nl .=",";//msrp_display_actual_price_type;
				$newProdData_nl .=",";//.country_of_manufacture;
				$newProdData_nl .=",";//.additional_attributes;
				$newProdData_nl .=",";//."100";//qty;
				$newProdData_nl .=",";//."0";//out_of_stock_qty;
				$newProdData_nl .=",";//."0";//use_config_min_qty;
				$newProdData_nl .=",";//."0";//is_qty_decimal;
				$newProdData_nl .=",";//."0";//allow_backorders;
				$newProdData_nl .=",";//."0";//use_config_backorders;
				$newProdData_nl .=",";//."1";//min_cart_qty;
				$newProdData_nl .=",";//."0";//use_config_min_sale_qty;
				$newProdData_nl .=",";//."50";//max_cart_qty; TODO: ask input
				$newProdData_nl .=",";//."0";//use_config_max_sale_qty;
				if($productOnline==0){
					$newProdData_nl .=",".$productOnline;//s_in_stock;		
				}else{
					$newProdData_nl .=",";
				}
				$newProdData_nl .=",";//."0";//notify_on_stock_below;
				$newProdData_nl .=",";//."0";//use_config_notify_stock_qty;
				$newProdData_nl .=",";//."0";//manage_stock;
				$newProdData_nl .=",";//."0";//use_config_manage_stock;
				$newProdData_nl .=",";//."0";//use_config_qty_increments;
				$newProdData_nl .=",";//."0";//qty_increments;
				$newProdData_nl .=",";//."0";//use_config_enable_qty_inc;
				$newProdData_nl .=",";//."0";//enable_qty_increments;
				$newProdData_nl .=",";//."0";//is_decimal_divided;
				$newProdData_nl .=",";//."1";//website_id; TODO check
				$newProdData_nl .=",";//.related_skus;
				$newProdData_nl .=",";//.crosssell_skus;
				$newProdData_nl .=",";//.upsell_skus;
				$newProdData_nl .=",";//.additional_images;
				$newProdData_nl .=",";//.additional_image_labels;
				$newProdData_nl .=",";//.custom_options;
				$newProdData_nl .=",";//.configurable_variations;
				$newProdData_nl .=",";//.configurable_variation_prices;
				$newProdData_nl .=",";//.configurable_variation_labels;
				$newProdData_nl .=",";//.bundle_price_type;
				$newProdData_nl .=",";//.bundle_sku_type;
				$newProdData_nl .=",";//.bundle_price_view;
				$newProdData_nl .=",";//.bundle_weight_type;
				$newProdData_nl .=",";//.bundle_values;
				$newProdData_nl .=PHP_EOL;
				
				$newProdData_fr .=",";//.small_image;
				$newProdData_fr .=",";//.small_image_label;
				$newProdData_fr .=",";//.thumbnail_image;
				$newProdData_fr .=",";//.thumbnail_image_label;
				$newProdData_fr .=",";//.date("d.m.Y H:m",$product['meta'].['createdAt']);
				$newProdData_fr .=",";//.date("d.m.Y H:m",$product['meta'].['updatedAt']);
				$newProdData_fr .=",";//.new_from_date;
				$newProdData_fr .=",";//.new_to_date;
				$newProdData_fr .=",";//.display_product_options_in;
				$newProdData_fr .=",";//.map_price;
				$newProdData_fr .=",";//.msrp_price;
				$newProdData_fr .=",";//.map_enabled;
				$newProdData_fr .=",";//.gift_message_available;
				$newProdData_fr .=",";//.custom_design;
				$newProdData_fr .=",";//.custom_design_from;
				$newProdData_fr .=",";//.custom_design_to;
				$newProdData_fr .=",";//.custom_layout_update;
				$newProdData_fr .=",";//page_layout;
				$newProdData_fr .=",";//product_options_container;
				$newProdData_fr .=",";//msrp_display_actual_price_type;
				$newProdData_fr .=",";//.country_of_manufacture;
				$newProdData_fr .=",";//.additional_attributes;
				$newProdData_fr .=",";//."100";//qty;
				$newProdData_fr .=",";//."0";//out_of_stock_qty;
				$newProdData_fr .=",";//."0";//use_config_min_qty;
				$newProdData_fr .=",";//."0";//is_qty_decimal;
				$newProdData_fr .=",";//."0";//allow_backorders;
				$newProdData_fr .=",";//."0";//use_config_backorders;
				$newProdData_fr .=",";//."1";//min_cart_qty;
				$newProdData_fr .=",";//."0";//use_config_min_sale_qty;
				$newProdData_fr .=",";//."50";//max_cart_qty; TODO: ask input
				$newProdData_fr .=",";//."0";//use_config_max_sale_qty;
				if($productOnline==0){
					$newProdData_fr .=",".$productOnline;//s_in_stock;
				}else{
					$newProdData_fr .=",";
				}
				$newProdData_fr .=",";//."0";//notify_on_stock_below;
				$newProdData_fr .=",";//."0";//use_config_notify_stock_qty;
				$newProdData_fr .=",";//."0";//manage_stock;
				$newProdData_fr .=",";//."0";//use_config_manage_stock;
				$newProdData_fr .=",";//."0";//use_config_qty_increments;
				$newProdData_fr .=",";//."0";//qty_increments;
				$newProdData_fr .=",";//."0";//use_config_enable_qty_inc;
				$newProdData_fr .=",";//."0";//enable_qty_increments;
				$newProdData_fr .=",";//."0";//is_decimal_divided;
				$newProdData_fr .=",";//."1";//website_id; TODO check
				$newProdData_fr .=",";//.related_skus;
				$newProdData_fr .=",";//.crosssell_skus;
				$newProdData_fr .=",";//.upsell_skus;
				$newProdData_fr .=",";//.additional_images;
				$newProdData_fr .=",";//.additional_image_labels;
				$newProdData_fr .=",";//.custom_options;
				$newProdData_fr .=",";//.configurable_variations;
				$newProdData_fr .=",";//.configurable_variation_prices;
				$newProdData_fr .=",";//.configurable_variation_labels;
				$newProdData_fr .=",";//.bundle_price_type;
				$newProdData_fr .=",";//.bundle_sku_type;
				$newProdData_fr .=",";//.bundle_price_view;
				$newProdData_fr .=",";//.bundle_weight_type;
				$newProdData_fr .=",";//.bundle_values;
				$newProdData_fr .=PHP_EOL;
				
				$newProdData_en .=",";//.small_image;
				$newProdData_en .=",";//.small_image_label;
				$newProdData_en .=",";//.thumbnail_image;
				$newProdData_en .=",";//.thumbnail_image_label;
				$newProdData_en .=",";//.date("d.m.Y H:m",$product['meta'].['created_at']);
				$newProdData_en .=",";//.date("d.m.Y H:m",$product['meta'].['updated_at']);
				$newProdData_en .=",";//.new_from_date;
				$newProdData_en .=",";//.new_to_date;
				$newProdData_en .=",";//.display_product_options_in;
				$newProdData_en .=",";//.map_price;
				$newProdData_en .=",";//.msrp_price;
				$newProdData_en .=",";//.map_enabled;
				$newProdData_en .=",";//.gift_message_available;
				$newProdData_en .=",";//.custom_design;
				$newProdData_en .=",";//.custom_design_from;
				$newProdData_en .=",";//.custom_design_to;
				$newProdData_en .=",";//.custom_layout_update;
				$newProdData_en .=",";//page_layout;
				$newProdData_en .=",";//product_options_container;
				$newProdData_en .=",";//msrp_display_actual_price_type;
				$newProdData_en .=",";//.country_of_manufacture;
				$newProdData_en .=",";//.additional_attributes;
				$newProdData_en .=",";//."100";//qty;
				$newProdData_en .=",";//."0";//out_of_stock_qty;
				$newProdData_en .=",";//."0";//use_config_min_qty;
				$newProdData_en .=",";//."0";//is_qty_decimal;
				$newProdData_en .=",";//."0";//allow_backorders;
				$newProdData_en .=",";//."0";//use_config_backorders;
				$newProdData_en .=",";//."1";//min_cart_qty;
				$newProdData_en .=",";//."0";//use_config_min_sale_qty;
				$newProdData_en .=",";//."50";//max_cart_qty; TODO: ask input
				$newProdData_en .=",";//."0";//use_config_max_sale_qty;
				if($productOnline==0){
					$newProdData_en .=",".$productOnline;//s_in_stock;
				}else{
					$newProdData_en .=",";
				}
				$newProdData_en .=",";//."0";//notify_on_stock_below;
				$newProdData_en .=",";//."0";//use_config_notify_stock_qty;
				$newProdData_en .=",";//."0";//manage_stock;
				$newProdData_en .=",";//."0";//use_config_manage_stock;
				$newProdData_en .=",";//."0";//use_config_qty_increments;
				$newProdData_en .=",";//."0";//qty_increments;
				$newProdData_en .=",";//."0";//use_config_enable_qty_inc;
				$newProdData_en .=",";//."0";//enable_qty_increments;
				$newProdData_en .=",";//."0";//is_decimal_divided;
				$newProdData_en .=",";//."1";//website_id; TODO check
				$newProdData_en .=",";//.related_skus;
				$newProdData_en .=",";//.crosssell_skus;
				$newProdData_en .=",";//.upsell_skus;
				$newProdData_en .=",";//.additional_images;
				$newProdData_en .=",";//.additional_image_labels;
				$newProdData_en .=",";//.custom_options;
				$newProdData_en .=",";//.configurable_variations;
				$newProdData_en .=",";//.configurable_variation_prices;
				$newProdData_en .=",";//.configurable_variation_labels;
				$newProdData_en .=",";//.bundle_price_type;
				$newProdData_en .=",";//.bundle_sku_type;
				$newProdData_en .=",";//.bundle_price_view;
				$newProdData_en .=",";//.bundle_weight_type;
				$newProdData_en .=",";//.bundle_values;
				$newProdData_en .=PHP_EOL;
				
				/*
				 * Write the product 
				 */
				fwrite($prodfile, $newProdData);
				fwrite($prodfile, $newProdData_nl);
				fwrite($prodfile, $newProdData_fr);
				fwrite($prodfile, $newProdData_en);
				$processed++;
				//fputcsv($prodfile,explode(",",$newProdData));
				unset($newProdData);
				unset($newProdData_nl);
				unset($newProdData_fr);
				unset($newProdData_en);
			}
			$pagecounter++;
			echo "/ done ".$processed." of ".$quantity_medipim. ' / ';
		}while($processed < $quantity_medipim);
		$total_products+=$processed;
	}
	/*
	 * Store categories as received form Medipim and identified as used by the synced products
	 */
	$catfile = fopen("../import/categories".$productcatsIDtosync.".csv","w");
	fputs( $catfile, "\xEF\xBB\xBF" );
	fwrite($catfile,"id;nl;fr;en".PHP_EOL);
	foreach($category_used as $cat){
		fwrite($catfile,$cat.PHP_EOL);
	}
	
	echo "End processing ".$total_products.PHP_EOL;
	fclose($prodfile);
	fclose($catfile);
	echo "-closed prod/cat files";
	/*
	 * Store the updated since timestamp********************************************************
	 */
	$updatedSince_handle = fopen("../config/medipimsync_updatedSince.csv","w");//keep unix timestamp of last sync per type
	fputs( $updatedSince_handle, "\xEF\xBB\xBF" );
	//first time sync
	if(empty($productcatsIDStosync) || !array_key_exists($productcatsIDtosync,$productcatsIDStosync)){
		fwrite($updatedSince_handle, $productcatsIDtosync.";".$now.PHP_EOL);
	}
	//synced earlier
	foreach ($productcatsIDStosync as $catid => $synctime){
		if($catid == $productcatsIDtosync){
			fwrite($updatedSince_handle, $productcatsIDtosync.";".$now.PHP_EOL);
		}else{
			fwrite($updatedSince_handle, $catid.";".$synctime.PHP_EOL);
		}
	}
	fclose($updatedSince_handle);
	echo "-updated timestamp ".$now;

} catch (Exception $e) {
    echo 'Caught exception in MedipimSync: ',  $e->getMessage(), "\n";
}
	
//var_dump($r);