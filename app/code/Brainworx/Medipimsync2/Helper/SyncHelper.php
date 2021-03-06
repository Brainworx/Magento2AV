<?php
namespace Brainworx\Medipimsync2\Helper;


use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Zend\Validator\Explode;
use Magento\Tax\Model\ClassModel;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Type;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Tax\Api\TaxClassManagementInterface;
use Magento\Variable\Model\Variable;
use Magento\Framework\App\Helper\Context;
use Medipim\Api\V3\MedipimApiV3Client;

class SyncHelper extends AbstractHelper{

	//test
	public function isNumber($x){
		return is_numeric($x) ? "yes" : "no";
	}
	
	protected $_resource;
	protected $_storemanager;
	protected $_logger;
	//required to query tax class
	protected $_filterBuilder;
	protected $_taxClassRepository;
	protected $_searchCriteriaBuilder;
	
	protected $_objectManager;
	protected $_eventManager;
	
	// 	protected $_productRepo;
	// 	protected $_catsRepo;
	
	private $_variableMdl;
	private $_id; // your api user id
	private $_key; // your secret api key
	private $_mediaUrl;
	private $_catUrl;
	private $_prodUrl;
	private $_configdir;

	public function __construct(Context $context,
			ResourceConnection $resource,
			StoreManagerInterface $storemanager, LoggerInterface $logger,
			FilterBuilder $filterBuilder,
			TaxClassRepositoryInterface $taxClassRepository,
			SearchCriteriaBuilder $searchCriteriaBuilder,
			\Magento\Framework\ObjectManagerInterface $objectmanager,
			\Magento\Framework\Event\ManagerInterface $eventmanager,
			//ProductRepositoryInterface $productrepo,
			//CategoryRepositoryInterface $catsrepo,
			Variable $variableMdl)
	{
		$this->_resource = $resource;
		$this->_storemanager = $storemanager;
		$this->_logger = $logger;
		$this->_filterBuilder = $filterBuilder;
		$this->_searchCriteriaBuilder = $searchCriteriaBuilder;
		$this->_taxClassRepository = $taxClassRepository;
		
		$this->_objectManager = $objectmanager;
		$this->_eventManager = $eventmanager;
		
		$this->_variableMdl = $variableMdl;
	
		$this->_id = $this->_variableMdl->loadByCode('medipim_api_userid')->getPlainValue();
		$this->_key = $this->_variableMdl->loadByCode('medipim_api_key')->getPlainValue();
		$this->_mediaUrl = $this->_variableMdl->loadByCode('medipim_api_mediaurl')->getPlainValue();
		$this->_catUrl = $this->_variableMdl->loadByCode('medipim_url_cat')->getPlainValue();
		$this->_prodUrl = $this->_variableMdl->loadByCode('medipim_url_prod')->getPlainValue();
		$this->_configdir = $this->_variableMdl->loadByCode('medipim_sync_config_dir')->getPlainValue();
	

		$this->_logger->debug('Helper constucted.');
	
		parent::__construct($context);
	}
	/**
	 * main action
	 * $entitydata - ['entity'] -- entity to sync
	 */
	public function sync($entitydata,$model = null)
	{
		$this->_logger->debug("Starting sync");
		
		/** @var \brainworx\medipimsync\Model\Sync $model */
		if(!$model)
			$model = $this->_objectManager->create('Brainworx\Medipimsync2\Model\Sync');

		$latestsync = $this->_objectManager->create('Brainworx\Medipimsync2\Model\Sync');
		$syncquery = $latestsync->loadLastSyncByEntity($entitydata);
		$lastsync = '200100101000000';
		if($syncquery['sync_dttm'] != null){
			$dt = $syncquery['sync_dttm'];
			$lastsync = date("YmdHis", strtotime($dt)) ;
		}
		$this->_logger->debug($entitydata." - Last sync ".$lastsync);

		$model->save();
		$file = self::getCategoriesMedipim($lastsync);
		self::loadCategories(BP . $file,$model);
		$this->_logger->debug("Sync of categories completed");

// 		if($entitydata=="CAT"){
// 			$file = self::getCategoriesMedipim($lastsync);
// 			self::loadCategories(BP . $file,$model);
// 			$this->_logger->debug("Sync of categories completed");
// 		}else{
// 			$model->setQtyUpdt(0);
// 			$model->setQtyInsrt(0);
// 			$model->setQtyTot(0);
// 			$all_cnks = self::loadProductsToSync();
// 			$files = array();
// 			foreach($all_cnks as $cnks_group){
// 				$file = self::getProductDataMedipim($cnks_group, $lastsync);
// 				$files[]=$file;
// 				$this->_logger->debug("CNKS retrieved from Medipim for: ".$file);
// 			}
// 			foreach($files as $file){
// 				$this->_logger->debug("Going to load: ".$file);
// 				self::loadProducts($file, $model);
// 				$this->_logger->debug("CNKS synced with Medipim for: ".$file);
// 			}
// 		}
		$model->setIsEnded(true);
		$model->setUpdateDttm(time());
		$model->save();

		$this->_logger->debug("Sync completed");
		return true;
	}
	function loadCategoryIDByMedipimId($consumer_category_id) {
		$connection = $this->_resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
		$sql = "Select entity_id from catalog_category_entity where consumer_category_id = " . $consumer_category_id . ' ORDER BY entity_id DESC';
		$row = $connection->fetchOne ( $sql ); // fetchAll , fetchRow($sql), fetchOne($sql),.
		return $row;
	}
	/**
	 *
	 * @param unknown $file example test.xml or ./var/test.xml
	 */
	function loadCategories($file,$syncmodel){
		if (file_exists($file)) {
			$xml = simplexml_load_file($file);
			if($xml === false){
				$this->messageManager->addError(
						$this->_objectManager->get('Magento\Framework\Escaper')->escapeHtml('Failed to parse xml file.')
				);
				$this->_redirect ( '*/*/' );
			}
			$parent = 0;
			$total = count($xml->categories->categorie);
			$processed = 0;
			$inserted = 0;
			$updated = 0;
			while($processed < $total){
				foreach($xml->categories->categorie as $categorie){
					if($categorie->parent == $parent){
						$feedback = self::insertCategorie($categorie);
						$processed +=1;
						if($feedback == "UPDATE"){
							$updated +=1;
						}else if($feedback == "INSERTED"){
							$inserted +=1;
						}
					}
	
				}
				$parent += 1;
			}
			$syncmodel->setQtyUpdt($updated);
			$syncmodel->setQtyInsrt($inserted);
			$syncmodel->setQtyTot($total);
		} else {
			throw new \Magento\Framework\Validator\Exception(__('Failed to open configuration.'));
		}
	}
	//get cats from Medipim
	function insertCategorie($incat){
		//insert cats in magento
		try{
			$update = false;
			$insert = false;
			$id = self::loadCategoryIDByMedipimId((integer)$incat->id);
			if($id!=false){
				$update = true;
				$category = $this->_objectManager->get('Magento\Catalog\Model\Category')->load($id);
				$lastupdated = strtotime($category->getLastUpdatedAt());
				$inputlastupdated = strtotime($incat->last_updated_at);
 				if(empty($lastupdated) || $lastupdated != $inputlastupdated){
 					$update = true;
 				}else{
 					return "NOACTION";
 				}
				$category->setName((string)$incat->name_nl);
				$category->setLastUpdatedAt(date("Y-m-d H:i:s", strtotime((string)$incat->last_updated_at)));
				if($category->getConsumerCatParentId() != $incat->parent
						|| ($category->getConsumerCatParentId()>0 && $category->getParentId() == 0) ){
					if(!empty($incat->parent)){
						$category->setConsumerCatParentId($incat->parent);
						$category->setIsAnchor(1); //for active achor to display filters instead of cats only
						$row = self::loadCategoryIDByMedipimId((integer)$incat->parent);
						if($row!=false){
							$pcategory = $row;
						}else{
							$this->_logger->info("Category parent not found at update of ".$category->getName());
						}
					}
						
					if(!empty($pcategory)){
						$category->setParentId($pcategory);
						$category->setPath($this->_objectManager->get('Magento\Catalog\Model\Category')->load($pcategory)->getPath().(!empty($id)?'/'.$id:''));
					}else{
						$category->setParentId(/*Mage::app()->getStore()->getRootCategoryId()*/2);
						$category->setPath('1/2'.(!empty($id)?'/'.$id:''));
					}
				}
				$this->_logger->info("Category updated ".$category->getName());
			}else{
				$insert = true;
				$category = $this->_objectManager->create('Magento\Catalog\Model\Category');
				$category->setName((string)$incat->name_nl);
				$category->setLastUpdatedAt(date("Y-m-d H:i:s", strtotime((string)$incat->last_updated_at)));
	
				$category->setIsActive(0);
				$category->setDisplayMode('PRODUCTS');
				$category->setStoreId($this->_storemanager->getStore('nl')->getId());
				$category->setAttributeSetId($this->_objectManager->get('Magento\Catalog\Model\Category')->getResource()->getEntityType()->getDefaultAttributeSetId());
				$category->setIncludeInMenu(true);
					
				$pcategory;
				$category->setPath('1');
				if((integer)$incat->parent > 0){
					$category->setIsAnchor(1); //for active achor to display filters instead of cats only
					$row = self::loadCategoryIDByMedipimId((integer)$incat->parent);
					if($row!=false){
						$pcategory = $row;
					}
				}
	
				if(!empty($pcategory)){
					$category->setParentId($pcategory);
					$category->setPath($this->_objectManager->get('Magento\Catalog\Model\Category')->load($pcategory)->getPath().(!empty($id)?'/'.$id:''));
				}else{
					$category->setParentId(/*Mage::app()->getStore()->getRootCategoryId()*/2);
					$category->setPath('1/2'.(!empty($id)?'/'.$id:''));
					$this->_logger->info("Category parent not found at insert of ".$category->getName());
				}
	
			}
				
			if($update || $insert){
				$category->save();
	
				//insert custom column values
				$connection = $this->_resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
				$connection->update(
						$this->_resource->getTableName('catalog_category_entity'),
						array('consumer_category_id' => (integer)$incat->id,
								'consumer_cat_parent_id' => (integer)$incat->parent,
								'last_updated_at' => (string)$incat->last_updated_at ),
						array('entity_id = ?' => $category->getEntityId() ));
	
				//setup all languages
				//$cat = $this->_objectManager->get('Magento\Catalog\Model\Category')->load($category->getEntityId());
				$cat = $category;
				//$cat->setIsActive(1)->save();
				$cat->setStoreId($this->_storemanager->getStore('nl')->getId())->setName((string)$incat->name_nl)->setUrlKey(preg_replace('/\s+/', '_', (string)$incat->translation_nl))->save();
	
				$cat->setStoreId($this->_storemanager->getStore('fr')->getId())->setName((string)$incat->name_fr)->setUrlKey(preg_replace('/\s+/', '_', (string)$incat->translation_fr))->save();
	
				$cat->setStoreId($this->_storemanager->getStore('en')->getId())->setName((string)$incat->name_en)->setUrlKey(preg_replace('/\s+/', '_', (string)$incat->translation_en))->save();
	
				$this->_logger->info("Category created ".$category->getName());
				if($insert)
					return "INSERTED";
				else
					return "UPDATE";
			}
				
			return "NOACTION";
				
		} catch(Exception $e) {
			$this->_logger->error("Error medipimsync save ".$e->getMessage());
			throw $e;
		}
	}
	function getCategoriesMedipim($lastmodified,$cat_id=null){
		try {
			$r = $client->get("/v3/public-categories/all");
			$catList = $r['results'];
			$meta = $r['meta'];			
			$catfile = fopen(BP . $filename,"w");
	
			$newCatData="";
	
			fwrite($catfile, "<total>".$meta['total']."</total>");

			$parent_cats=0;
			foreach($catList as $categorie){
				$newCatData = "<categorie>";
				$newCatData .="<id>".$categorie['id']."</id>";
				
				$newCatData .="<name_nl>".$categorie['name']['nl']."</name_nl>";
				$newCatData .="<name_fr>".$categorie['name']['fr']."</name_fr>";
				$newCatData .="<name_en>".$categorie['name']['en']."</name_en>";
				
				$newCatData .="<parent_id>".$categorie['parent']."</parent_id>";
				if(empty($categorie['parent'])){
					$parent_cats +=1;
					echo $categorie['name']['nl'] ." ". $categorie['id']."<br>";
				}
				
				$newCatData .="<created_at>".$categorie['meta']['createdAt']."</created_at>";
				$newCatData .="<last_updated_at>".$categorie['meta']['updatedAt']."</last_updated_at>";
				
			
				$newCatData .= "</categorie>";
				fwrite($catfile, $newCatData);
				unset($newCatData);
			}
			fwrite($catfile, "</categories></catdoc>");
				
			fclose($catfile);
				
			return $filename;
	
		}catch (Exception $e){
			$this->_logger->error("Error medipimsync save ".$e->getMessage());
			throw $e;
		}
	}
	/**
	 * get product data for param cnks + last modified date yyyymmddhhMMss
	 *
	 * gets product info from Medipim and writes them to var/medipimsync/products_yyyymmdd.xml
	 * writes all unique category_ids to var/medipimsync/categories_yyyymmdd.csv
	 *
	 * @param array(cnks), date lastmodified, date current run
	 */
	function getProductDataMedipim($cnks,$lastmodified) {
		try{
			//setup sync with Medipim
			//$purl = "https://api.medipim.be/v2/rest/products";
			$datalabel=explode(",","id,cnk,language,last_updated_at,status,name,febelco_name,ean,atc,apb_category,weight,prescription,tax,public_price_apb,public_price_febelco,public_price_manufacturer,fagg_publ_notice,fagg_spc_notice,pharma_publ_notice,pharma_spc_notice,indication,contra_indication,usage,public_name,description,full_description,composition,properties,width,height,depth,diameter,udi,nut,supplier_reference,labo_publ_notice,labo_spc_notice");
			//brands, consumer_categories, appstores, leaflets,videos,websites, media<450x450,900x900>
				
			$data=array("cnks"=>$cnks['cnks'],"modified_since"=>$lastmodified);
			$data_string = json_encode($data);
			$prodfilename = BP."/var/medipimsync/products".time().".xml";
				
			$prodfile = fopen($prodfilename,"w");
	
			//returns unique cnks numbers
			$response = file_get_contents($this->_prodUrl, false, stream_context_create(array(
					'http' => array(
							'method' => 'POST',
							'header' => array('Content-Type: application/json'."\r\n"
									. "Authorization: Basic " . base64_encode("$this->_id:$this->_key")."\r\n"
									. 'Content-Length: ' . strlen($data_string) . "\r\n"),
							'content' => $data_string)
			)
			));
	
			$productList = json_decode($response);
			$mgt_label;
			$categories=array();
	
			fwrite($prodfile, "<prod_doc><products>");
				
			foreach($productList->products as $product){
				$newProductData = "<product>";
				$categories=array();
				foreach($datalabel as $label){
					if(property_exists($product,$label)){
						$mgt_label = $label;
						//skip indication tags
						if($label == "indication" || $label == "composition"){
							continue;
						}
						if($label == "cnk"){
							$mgt_label = "sku";
							$newProductData .="<discount>".$cnks['discounts'][(int)$product->$label]."</discount>";
						}elseif ($label =="status"){
							$mgt_label = "medipim_status";
							if($product->$label == "active"){
								$newProductData .="<status>1</status>";
							}else{
								$newProductData .="<status>0</status>";
							}
						}elseif($label == "tax"){
							$newProductData .="<tax_class_id>tax-".$product->$label."</tax_class_id>";//$newProductData["tax_class_id"]="tax-".$product->$label;
						}elseif($label == "public_price_manufacturer"){
							$mgt_label = "msrp";
						}elseif ($label == "public_name"){
							$mgt_label = "name";
						}elseif ($label == "description"){
							$mgt_label = "short_description";
						}elseif ($label == "full_description"){
							$mgt_label = "description";
						}
						$newProductData .="<".$mgt_label.">".htmlspecialchars(strip_tags($product->$label))."</".$mgt_label.">";
					}
				}
				//set price
				if(property_exists($product,"public_price_apb")){
					$newProductData .="<price>".$product->public_price_apb."</price>";
				}else{
					$this->_logger->info("No APB price found in Medipim for ".$product->cnk);
					if(property_exists($product,"public_price_febelco")&&$product->public_price_febelco!=0){
						$newProductData .="<price>".$product->public_price_febelco."</price>";
					}else{
						$newProductData .="<price>0</price>";
						$this->_logger->error("No price found in Medipim for ".$product->cnk);
					}
				}
				//check stock Febelco
				if(!isset($product->public_price_febelco)||$product->public_price_febelco==0){
					$this->_logger->info("No febelco price/stock for ".$product->cnk." ".$product->name);
				}
	
				//set the last synced date
				$newProductData.="<last_sync>".date("Y-m-d")."</last_sync>";
				//store unique values only
				foreach($product->consumer_categories as $cat){
					if(! in_array($cat->consumer_category_id,$categories)){
						$categories[]=$cat->consumer_category_id;
					}
				}
	
				$newProductData.="<mp_categories>".implode(",", $categories)."</mp_categories>"; //test
	
				if(property_exists($product,'media')){
					if(property_exists($product->media,'900x900')){
						$newProductData .= "<images>";
						foreach ($product->media->{'900x900'} as $image){
							// + show picture, - dont show picture
							// 							$newProductData['image']='+'.(string)$image->file_path;
							$newProductData.="<image>".$image->file_path."</image>";  //test
							//get image from relative path https://media.medipim.be/
							//copy($this->mediaUrl.$image->file_path,BP."/var/medipimsync/".$image->file_path);
							//pub/media/
							// 							copy($this->_mediaUrl.$image->file_path,BP."/pub/".$image->file_path);
						}
						$newProductData .= "</images>";
					}
					if(property_exists($product->media,'450x450')){
						$newProductData .= "<small_images>";
						foreach ($product->media->{'450x450'} as $image){
							// + show picture, - dont show picture
							// 							$newProductData['small_image']='+'.(string)$image->file_path;
							$newProductData.="<small_image>".$image->file_path."</small_image>"; //test
							// 							copy($this->_mediaUrl.$image->file_path , BP."/pub/".$image->file_path);
								
						}
						$newProductData .= "</small_images>";
					}
				}
	
				$newProductData .= "</product>";
				fwrite($prodfile, $newProductData);
				$newProductData=null;    //clear memory
				unset($newProductData); //clear memory
				unset($categories);
	
			}
			fwrite($prodfile, "</products></prod_doc>");
				
			fclose($prodfile);
				
			return $prodfilename;
	
		}catch(Exception $e){
			$this->_logger->error("Error medipimsync save ".$e->getMessage());
			throw $e;
		}
	}
	/**
	 * Loads the cnks to sync with Medipim from config file
	 * /var/medipimsync/config/productcnks.csv - csv with 1 cnk per line
	 * returns array of array with 1000 cnks per entry
	 * default config= productcnks.csv
	 */
	function loadProductsToSync($configfile='productcnks.csv'){
		try{
			$cnks = array();
			$cnks_discounts = array();
			$result = array();
			$row = 1;
			$counter = 0;
			if (($handle = fopen(BP."/".$this->_configdir."/".$configfile, "r")) !== FALSE) {
				while (($idata = fgetcsv($handle, 500, ";")) !== FALSE ) {
					$counter +=1;
					//$num = count($idata);
					$row++;
					//for ($c=0; $c < $num; $c++) {
					$cnks[] = $idata[0];
					$cnks_discounts[$idata[0]] = $idata[3];
					//}
					if($counter == 500){
						$subresult = array();
						$subresult['cnks']=$cnks;
						$cnks = array();
						$subresult['discounts']=$cnks_discounts;
						$cnks_discounts = array();
						$result[]=$subresult;
						$counter=0;
					}
				}
				if($counter>0){
					$subresult = array();
					$subresult['cnks']=$cnks;
					$cnks = array();
					$subresult['discounts']=$cnks_discounts;
					$cnks_discounts = array();
					$result[]=$subresult;
				}
				fclose($handle);
			}
				
			return $result;
				
		}catch(Exception $e){
			$this->_logger->error("Error medipimsync save ".$e->getMessage());
			throw $e;
		}
	}
	function loadProducts($file,$syncmodel){
		if (file_exists($file)) {
			$xml = simplexml_load_file($file);
			if($xml === false){
				$this->messageManager->addError(
						$this->_objectManager->get('Magento\Framework\Escaper')->escapeHtml('Failed to parse xml file for Products.')
				);
				$this->_redirect ( '*/*/' );
			}
			$parent = 0;
			$total = count($xml->products->product);
			$processed = 0;
			$inserted = 0;
			$updated = 0;
			$this->_logger->debug("need to sync: "+$total);			
			while($processed < $total){
				foreach($xml->products->product as $product){
					if($product->parent_id == $parent){
						$feedback = self::insertProduct($product);
						$processed +=1;
						if($feedback == "UPDATE"){
							$updated +=1;
						}else if($feedback == "INSERTED"){
							$inserted +=1;
						}
					}
	
				}
				$parent += 1;
			}
			$syncmodel->setQtyUpdt($updated+$syncmodel->getQtyUpdt());
			$syncmodel->setQtyInsrt($inserted+$syncmodel->getQtyInsrt());
			$syncmodel->setQtyTot($total+$syncmodel->getQtyTot());
			$this->_logger->debug("synced: "+$total);
		} else {
			$this->_logger->error("Error medipimsync sync loadproducts - config open failed.");
			throw new \Magento\Framework\Validator\Exception(__('Failed to open configuration for products.'));
		}
	}
	/**
	 * insert or updates the product
	 * reads images from the folder: /var/medipimsync/media/
	 * @param unknown $inprod
	 * @return string
	 */
	function insertProduct($inprod){
		//insert products in magento
		try{
			$update = false;
			$insert = false;
			//check existing product
			$id = self::loadProductIDBySKU((string)$inprod->sku);
			if($id!=false){
				$this->_logger->info("Product found id ".$id);
				$update = true;
				$product = $this->_objectManager->get('Magento\Catalog\Model\Product')->load($id);
				//$product = $repo->getById($id);
				$lastupdated = strtotime($product->getLastUpdatedAt());//returns false if null
				$inputlastupdated = strtotime($inprod->last_updated_at);
				//TODO to be uncommented when check is clear
				// 				if($lastupdated < $inputlastupdated){
				// 					$update = true;
				// 				}else{
				// 					$this->_logger->info("Product found but no update required ".$product->getSku());
				// 					return "NOACTION";
				// 				}
				//set the language to update
				$store = $this->_storemanager->getStore((string)$inprod->language);
				$storeid = $store->getId();
				$product->setStoreId($storeid);
				// 				$product->setWebsiteIds(array($store->getWebsiteId()));
	
				$product->setLastUpdatedAt(date("Y-m-d H:i:s", strtotime((string)$inprod->last_updated_at)));
	
				$product->setName((string)$inprod->name);
				$product->setDescription((string)$inprod->description);
				$product->setShortDescription(strip_tags((string)$inprod->short_description));
				$product->setPrice((float)$inprod->price);
				$product->setSpecialPrice($product->getPrice()-($product->getPrice()*(float)$inprod->discount));
				if($product->getPrice()==0){
					$product->setStatus(0);
					$this->_logger->info("Disabled product as price is 0 for sku ".$product->getSku());
				}else{
					$product->setStatus(1); // Status on product enabled/ disabled 1/0
				}
				// 				$product->setTaxClassId(self::loadTaxClassId((integer)$inprod->tax)); // Tax class id
				$product->setWeight((integer)$inprod->weight); // weight of product
				//todo check image require update or are image equal for all store id's
	
				//add categories and activate them if required
				//  				$mpcats = explode(";",$inprod->mp_categories);
				//  				$cats = array();
				//  				foreach($mpcats as $mpcat){
				//  					$cat = self::loadCategoryIDByMedipimId((integer)$mpcat);
				//  					if(!empty($cat)){
				//  						//TODO check issue with acitvating cat url key already exists error
				// //  						$categorie = $this->_objectManager->get('Magento\Catalog\Model\Category')->load($cat);
				// //  						$categorie->setStoreId($storeid)->setIsActive(1)->save();
				// //  						while($parentCat = $categorie->getParentCategory()){
				// // 							if($parentCat->getId()==1){
				// // 								break;
				// // 							}
				// //  							$parentCat->setStoreId($storeid)->setIsActive(1)->save();
				// //  							$categorie = $parentCat;
				// //  							//$cats[]=$parentCat->getId();
	
				// //  						}
				//  						$cats[]=$cat;
				//  					}
				//  				}
				//  				$product->setCategoryIds($cats);
	
	
			}else{
				$insert = true;
				$this->_logger->info("Product to insert ".(string)$inprod->sku);
				$product = $this->_objectManager->create('Magento\Catalog\Model\Product');
				//TODO check set website
				$store = $this->_storemanager->getStore((string)$inprod->language);
				$storeid = $store->getId();
				$product->setStoreId($storeid);
				$product->setWebsiteIds(array($store->getWebsiteId()));
	
				$product->setLastUpdatedAt(date("Y-m-d H:i:s", strtotime((string)$inprod->last_updated_at)));
	
				$product->setSku((string)$inprod->sku);
				$product->setName((string)$inprod->name);
				$desciption = ((string)$inprod->description);
				$product->setDescription((string)$inprod->description);
				$product->setPrice((float)$inprod->price);
				$product->setSpecialPrice($product->getPrice()-($product->getPrice()*(float)$inprod->discount));
				if($product->getPrice()==0){
					$product->setStatus(0);
					$this->_logger->info("Disabled product as price is 0 for sku ".$product->getSku());
				}else{
					$product->setStatus(1); // Status on product enabled/ disabled 1/0
				}
				$product->setTaxClassId(self::loadTaxClassId((integer)$inprod->tax)); // Tax class id
				$product->setWeight((integer)$inprod->weight); // weight of product
				$product->setVisibility(Visibility::VISIBILITY_BOTH); // visibilty of product (catalog / search / catalog, search / Not visible individually)
				$product->setTypeId(Type::TYPE_SIMPLE); // type of product (simple/virtual/downloadable/configurable)
				$product->setAttributeSetId($this->_objectManager->get('Magento\Catalog\Model\Product')->getDefaultAttributeSetId()); // Attribute set id 4
				$product->setStockData(
						array(
								'use_config_manage_stock' => 0,
								'manage_stock' => 0,
								'is_in_stock' => 1,
								'qty' => 999
						)
				);
				/*
				 * addImageToMediaGallery($file, $mediaAttribute=null, $move=false, $exclude=true)
				 * @param string $file  file path of image in file system
				 * @param string|array  $mediaAttribute    code of attribute with type 'media_image',
				 *                  leave blank if image should be only in gallery
				 * @param boolean $move  if true, it will move source file
				 * @param boolean $exclude  mark image as disabled in product page view
				*/
				foreach($inprod->images as $image){
					$imagePath = BP.'/pub/'.$image->image; // path of the image
					copy($this->_mediaUrl.$image->image,$imagePath);
					$product->addImageToMediaGallery($imagePath, array('image'), false, false);
				}
				foreach($inprod->small_images as $image){
					$imagePath = BP.'/pub/'.$image->small_image; // path of the image
					copy($this->_mediaUrl.$image->small_image,$imagePath);
					$product->addImageToMediaGallery($imagePath, array('small_image', 'thumbnail'), false, false);
				}
	
				//add categories and activate them if required
				$mpcats = explode(";",$inprod->mp_categories);
				$cats = array();
				foreach($mpcats as $mpcat){
					$cat = self::loadCategoryIDByMedipimId((integer)$mpcat);
					if(!empty($cat)){
						// 						$categorie = $this->_objectManager->get('Magento\Catalog\Model\Category')->load($cat);
						// 						$categorie->setStoreId($storeid)->setIsActive(1)->save();
						// 						while($parentCat = $categorie->getParentCategory()){
						// 							if($parentCat->getId()==1){
						// 								break;
						// 							}
						//  							$parentCat->setStoreId($storeid)->setIsActive(1)->save();
						//  							$categorie = $parentCat;
						//  							//$cats[]=$parentCat->getId();
						//  						}
						$cats[]=$cat;
					}
				}
				$product->setCategoryIds($cats);
	
			}
	
			if($update || $insert){
				//$this->_productRepo->save($product);
				$product->save();
				if($insert){
					$this->_logger->info("Product created".$product->getSku());
					return "INSERTED";
				}
				else{
					$this->_logger->info("Product updated".$product->getSku());
					return "UPDATE";
				}
					
			}
	
			$this->_logger->info("Product processed but no update required ".$product->getSku());
			return "NOACTION";
	
		} catch(Exception $e) {
			$this->_logger->error("Error medipimsync save ".$e->getMessage());
			throw $e;
		}
	}
	private function loadProductIDBySKU($sku) {
		$connection = $this->_resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
		$sql = "Select entity_id from catalog_product_entity where sku like '" . $sku . "' ORDER BY entity_id DESC";
		// 		$this->_logger->info("Executing sql ".$sql);
		$row = $connection->fetchOne ( $sql ); // fetchAll , fetchRow($sql), fetchOne($sql),.
		return $row;
	}
	
	/**
	 * Load tax class id based on tax %
	 * reading tax_class table -- all tax classes have % in name
	 * default value is tax class for 21%
	 * @param int $tax
	 * @return int
	 */
	private function loadTaxClassId($tax){
		$filter = $this->_filterBuilder
		->setField(ClassModel::KEY_TYPE)
		->setValue(TaxClassManagementInterface::TYPE_PRODUCT)
		->create();
		$searchCriteria = $this->_searchCriteriaBuilder->addFilters([$filter])->create();
		$searchResults = $this->_taxClassRepository->getList($searchCriteria);
		foreach ($searchResults->getItems() as $taxClass) {
			$test = strpos($taxClass->getClassName(), (string)$tax);
			if ($test !== false){
				return $taxClass->getClassId();
			}
		}
		$this->_logger->debug("Tax class id couldn't be found for tax ".$tax." Returned default 21%");
		return 2;
	
	}
}
	