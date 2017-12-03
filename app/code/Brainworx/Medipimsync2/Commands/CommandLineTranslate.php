<?php
 
namespace Brainworx\Medipimsync2\Commands;
 
use Symfony\Component\Console\Command\Command;
use Magento\Framework\App\State;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\ImportExport\Model\Import\Source\CsvFactory;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Variable\Model\Variable;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
 
/**
 * Command to import products.
 */
class CommandLineTranslate extends Command
{
    /**
     * @var State $state
     */
    private $state;
 
    /**
     * @var CsvFactory
     */
    private $csvSourceFactory;
 
    /**
     * @var ReadFactory
     */
    private $readFactory;
    
    private $cat_nm_attribute_id;
    private $_variableMdl;
    protected $_objectManager;
    protected $_logger;
    protected $_resource;
    protected $_storemanager;
    /**
     * Constructor
     *
     * @param State $state  A Magento app State instance
     * @param ImportFactory $importFactory Factory to create entiry importer
     * @param CsvFactory $csvSourceFactory Factory to read CSV files
     * @param ReadFactory $readFactory Factory to read files from filesystem
     *
     * @return void
     */
    public function __construct(
      State $state,
      CsvFactory $csvSourceFactory,
      ReadFactory $readFactory,
      Variable $variableMdl,
      ObjectManagerInterface $objectmanager,
      LoggerInterface $logger,
      ResourceConnection $resource,
      StoreManagerInterface $storemanager
    ) {
        $this->state = $state;
        $this->csvSourceFactory = $csvSourceFactory;
        $this->readFactory = $readFactory;
        $this->_variableMdl = $variableMdl;
        $this->_objectManager = $objectmanager;
        $this->_logger = $logger;
        $this->_resource = $resource;
        $this->_storemanager = $storemanager;
        
        $this->cat_nm_attribute_id = $this->_variableMdl->loadByCode('cat_nm_attribute_id')->getPlainValue();
        parent::__construct();
    }
 
    /**
     * Configures arguments and display options for this command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('Medipimsync2:translate-categories');
        $this->setDescription('Translate Categories in Magento based on a CSV');
        $this->addArgument('filename', InputArgument::REQUIRED, 'The name of the import file (ie. file for file.csv) located in toot//Medipim//Api//import');
        parent::configure();
    }
 
    /**
     * Executes the command to add products to the database.
     *
     * @param InputInterface  $input  An input instance
     * @param OutputInterface $output An output instance
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	try{
	    	/** @var \brainworx\medipimsync\Model\Sync $model */
	    	$model = $this->_objectManager->create('Brainworx\Medipimsync2\Model\Sync');
	        $updated = 0;
	        $processed = 0;
	    	
	        // We cannot use core functions (like saving a product) unless the area
	        // code is explicitly set.
	        try {
	            $this->state->setAreaCode('adminhtml');
	        } catch (\Magento\Framework\Exception\LocalizedException $e) {
	            // Intentionally left empty.
	        }
	 
	        $translations=array();
	        $input_filename = $input->getArgument('filename');
	        $temp = array();
	        //read synced categories
	        if (($handle = fopen(BP."/Medipim/Api/import/".$input_filename.".csv", "r")) !== FALSE) {
	        	while (($data = fgetcsv($handle, 0, ";")) !== FALSE ) {
	        		
	        		if($data[1]=='nl'){
	        			continue;
	        		}
	        		$temp['id']=$data[0];
	        		$temp['nl']=$data[1];
	        		$temp['fr']=$data[2];
	        		$temp['en']=$data[3];
	        		$translations[]=$temp;

	        		$this->_logger->debug("Category to translate ".$temp['nl'].' - '.$temp['fr']);
	        		
	        		$temp=array();
	        	}
	        	fclose($handle);
	        }
	 		
	        foreach($translations as $translation){
	        	if(self::translateCategorie($translation)=="UPDATE"){
	        		$updated++;
	        	}
	        	$processed++;
	        }
	        $model->setQtyUpdt($processed);
	        $model->setQtyUpdt($updated);
	        $model->setUser('Robot');
	        switch($input_filename){
	        	case "categories0":
	        		$model->setEntity("cat:dieet,baby");
	        		break;
	        	case "categories1":
	        		$model->setEntity("cat:haarhuid,homeo");
	        		break;
	        	case "categories2":
	        		$model->setEntity("cat:kruiden,mond");
	        		break;
	        	case "categories3":
	        		$model->setEntity("cat:reis,sport");
	        		break;
	        	case "categories4":
	        		$model->setEntity("cat:voedingssupplementen");
	        		break;
	        	default:
	        		$model->setEntity($input_filename);
	        }
	        $model->save();
	 
	        $output->writeln("<info>Finished translating ".$input_filename." - processed ".$processed." updated ".$updated."</info>");
	        
    	} catch(Exception $e) {
        	$output->writeln('<error>Unable to translate the categories.</error>');
        }
    }
    /*
     * Translate categories based on input retrieved from Medipim
     * -nl translation added as default during product creation
     * Usage: will translate all categories in catalog_category_entity where consumer_category_id = 0 and skip others
     */
    function translateCategorie($incat){
    	
    	try{
    		$update = false;
    		$id = self::loadCategoryIDByName($incat['nl']);//0=id,1=nl,2=fr,3=en -- $catfile = fopen("../import/categories".$productcatsIDtosync.".csv","w");
    		if($id!=false){
    			$category = $this->_objectManager->get('Magento\Catalog\Model\Category')->load($id);
    			if(!($incat['nl']!=$incat['fr'] && $incat['nl']!=$incat['en'])){
    				$this->_logger->debug("Cat nl not different from en or fr: :".$incat['nl']."-".$incat['fr']."-".$incat['en']);
    			}
    			 
    			if($category->getConsumerCategoryId()==0 ){
    				//only set consumer cat id when translation is ok from Medipim
    				//TODO
    				if($incat['nl']!=$incat['fr'] && $incat['nl']!=$incat['en']){
    					$category->setConsumerCategoryId($incat['id']);
    				}
    				$update = true;
    				$category->setLastUpdatedAt(date("Y-m-d H:i:s", strtotime(time())));
    				
    				$category->save();
    				 
    				//insert custom column values
    				//     			$connection = $this->_resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
    				//     			$connection->update(
    				//     					$this->_resource->getTableName('catalog_category_entity'),
    				//     					array('consumer_category_id' => (integer)$incat->id,
    				//     							'consumer_cat_parent_id' => (integer)$incat->parent,
    				//     							'last_updated_at' => (string)$incat->last_updated_at ),
    				//     					array('entity_id = ?' => $category->getEntityId() ));
    				
    				//setup all languages
    				$cat = $category;
    				$cat->setStoreId($this->_storemanager->getStore('fr')->getId())->setName((string)$incat['fr'])->save();
    				//->setUrlKey(preg_replace('/\s+/', '_', (string)$incat->translation_fr))
    				
    				$cat->setStoreId($this->_storemanager->getStore('en')->getId())->setName((string)$incat['en'])->save();
    				//->setUrlKey(preg_replace('/\s+/', '_', (string)$incat->translation_en))
    				
    				$this->_logger->debug("Category translated ".$category->getName());
    				 
    				return "UPDATE";
    				 
    			}
    		}else{
    			$this->_logger->debug("Category to translate not found ".$incat['id']. "-".$incat['nl']);
    		}
    
    		return "NOACTION";
    
    	} catch(Exception $e) {
    		$this->_logger->error("Error medipimsync save ".$e->getMessage());
    		throw $e;
    	}
    }
    function loadCategoryIDByName($name) {
    	$connection = $this->_resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
    	$sql = "Select entity_id from catalog_category_entity_varchar where attribute_id = ".$this->cat_nm_attribute_id." and store_id = 0 and value like '" . $name ."'";
    	$this->_logger->debug($sql);
    	$row = $connection->fetchOne ( $sql ); // fetchAll , fetchRow($sql), fetchOne($sql),.
    	return $row;
    }
}