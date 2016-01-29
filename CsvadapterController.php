<?php

/**
 * csv adapter controller.
 *
 * @category OntoWiki
 * @package Extensions_csvadapter
 * @author Olga Nudel <olga.nudel@gmail.com>
 */


/**
 * csv adapter actions controller.
 **/
class CsvadapterController extends OntoWiki_Controller_Component
{
	
	private $_model = null;
    private $_post = null;
    private $_parser = null;
    public $_firstline = array();
    private $_isbnInfo = array();
    private $_csvf = '';
    
    public function init()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('Initializing MapPlugin Controller');

        parent::init();
                     
        OntoWiki::getInstance()->getNavigation()->disableNavigation(); 
        $action = $this->_request->getActionName();
        $this->view->formActionUrl    = $this->_config->urlBase . 'csvadapter/' . $action;
        
        if (!$this->isSelectedModelEditable()) {
        	return;
        } else {
            $this->_model = $this->_owApp->selectedModel;
        }
    }

/**
 * User Interface
 * Action-Method for handling with upload view template
 **/
    public function indexAction()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('indexAction');
        
        //initialisation of view parameter
        $this->view->placeholder('main.window.title')->set('Upload CSV File');
        $this->view->formEncoding     = 'multipart/form-data';
        $this->view->formClass        = 'simple-input input-justify-left';
        $this->view->formMethod       = 'post';
        $this->view->formName         = 'upload';
        $this->view->supportedFormats = array(
            'xml'   => 'XML',
            'csv'   => 'CSV'
        );
		//toolbar for upload of file
        $toolbar = $this->_owApp->toolbar;
        $toolbar->appendButton(
            OntoWiki_Toolbar::SUBMIT,
            array('name' => 'Select', 'id' => 'upload')
        )->appendButton(
            OntoWiki_Toolbar::RESET,
            array('name' => 'Cancel', 'id' => 'upload')
        );
        $this->view->placeholder('main.window.toolbar')->set($toolbar);
        
        //request handling
        if ($this->_request->isPost()) {
         
         	$upload = new Zend_File_Transfer();
         	$filesArray = $upload->getFileInfo();
         	$this->_csvf=$filesArray['source']['tmp_name'];
         	//error messages         
            $message = '';
            switch (true) {
                case empty($filesArray):
                    $message = 'upload went wrong. check post_max_size in your php.ini.';
                    break;
                case ($filesArray['source']['error'] == UPLOAD_ERR_INI_SIZE):
                    $message = 'The uploaded files\'s size exceeds the upload_max_filesize directive in php.ini.';
                    break;
                case ($filesArray['source']['error'] == UPLOAD_ERR_PARTIAL):
                    $message = 'The file was only partially uploaded.';
                    break;
                case ($filesArray['source']['error'] >= UPLOAD_ERR_NO_FILE):
                    $message = 'Please select a file to upload';
                    break;
            }
            if ($message != '') {
                $this->_owApp->appendErrorMessage($message);
                return;
            }
        
        //call parser
        $this->_parser = new Parser();
        $dt=$this->_parser->_parseFile($this->_csvf);
        
        $in = tempnam(sys_get_temp_dir(), 'ow');
        copy ($this->_csvf, $in);
        
        $this->_firstline =$dt[0];        
        $isbnArr = array();
        $isbnArr = $this->_extractIsbn($dt);
        $this->_validateIsbn($isbnArr);
        for ($i=0; $i<count($this->_isbnInfo);$i++){
        	if (stripos($this->_isbnInfo[$i],'not valid')!= Null){
        	    $message = $this->_isbnInfo[$i].PHP_EOL;
                $this->_owApp->appendErrorMessage($message);
        	}
        }
        $_SESSION['fname'] = $in;
        if ((count($this->_firstline))>0){
        	$_SESSION['flinecount'] = count($this->_firstline);
        	$_SESSION['fline']=implode(';',$this->_firstline);
        	}
        	//redirect to import action
        $this->_redirect('csvadapter/import');
        }
    }

 /**
 * Action-Method for handling with import view template
 **/
    public function importAction()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('importAction');

        //initialisation of view parameter
        $this->view->placeholder('main.window.title')->set('Select mapping parameter');  
        $this->view->formEncoding     = 'multipart/form-data';
        $this->view->formClass        = 'simple-input input-justify-left';
        $this->view->formMethod       = 'post';
        $this->view->formName         = 'selection';
        $this->view->filename		  = htmlspecialchars($_SESSION['fname']);
        $this->view->restype   		  = '';
        $this->view->baseuri		  = '';
        $this->view->header		  	  = '';
        $this->view->flinecount		  =  htmlspecialchars($_SESSION['flinecount']);
        $this->view->fline			  =  htmlspecialchars($_SESSION['fline']);
        $this->view->line		  	  =  explode(';',$this->view->fline);

        //toolbar for import of data
        $toolbar = $this->_owApp->toolbar;
        $toolbar->appendButton(OntoWiki_Toolbar::SUBMIT, array('name' => 'Submit', 'id' => 'selection'))
                ->appendButton(OntoWiki_Toolbar::RESET, array('name' => 'Cancel', 'id' => 'selection'));
        $this->view->placeholder('main.window.toolbar')->set($toolbar);
        
        //handling of request
        if ($this->_request->isPost()) {
        $postData = $this->_request->getPost();
        
        if (!empty($postData)) {      
        
        $baseuri = $postData['b_uri'];
        $this->view->baseuri    = $baseuri;
        $restype = $postData['r_type'];
        $this->view->restype    = $restype;
        $header = $postData['rad'];
        $this->view->header    = $header;
        
        if (trim($restype) == '') {
                $message = 'Ressource type must not be empty!';
                $this->_owApp->appendMessage(new OntoWiki_Message($message, OntoWiki_Message::ERROR));
        }
        if ((trim($baseuri) == '')||(trim($baseuri) == 'http://')) {
                $message = 'Base Uri must not be empty!';
                $this->_owApp->appendMessage(new OntoWiki_Message($message, OntoWiki_Message::ERROR));
        }
        if (trim($header) == '') {
        	$message = 'You must select whether you have a header!';
        	$this->_owApp->appendMessage(new OntoWiki_Message($message, OntoWiki_Message::ERROR));
        }
        
        //create mapping
        if (	   (trim($restype) != '') 
        		&& ((trim($baseuri) != '') || (trim($baseuri) != 'http://'))
        		&& (trim($header) != ''))
        {
        	$paramArray = array('firstline' => $this->view->line,
        						'header' 	=> $header,
        						'baseuri'	=> $baseuri,
        						'restype' 	=> $restype,
        						'filename' 	=> $this->view->filename
        	);
        	
        	$maippng = $this->_createMapping($paramArray);
        	$maprpl  = str_replace('"', "'", $maippng);
        	//save mapping into file
        	$mapfile = tempnam(sys_get_temp_dir(), 'ow');
        	
        	$fp = fopen($mapfile,"wb");
        	fwrite($fp,$maprpl);
        	fclose($fp);
        	
        	$ttl = array();
        	
        	//call convert for ttl
        	$ttl = $this->_convert($mapfile, $this->view->filename);
        	
        	//save ttl data into file
        	$ttlfile = tempnam(sys_get_temp_dir(), 'ow');
        	$temp = fopen($ttlfile, 'wb');
        	foreach ($ttl as $line) {
        		fwrite($temp, $line . PHP_EOL);
        		}
        	fclose($temp);
        	$filetype = 'ttl';
        	
        	$locator  = Erfurt_Syntax_RdfParser::LOCATOR_FILE;
        	
        	// import call
        	
        	try {
        		$this->_import($ttlfile, $filetype, $locator);
        		} catch (Exception $e) {
        			$message = $e->getMessage();
        			$this->_owApp->appendErrorMessage($message);
        			return;
        			}
        		}
        		
        //after success redirect to index site 
        $this->_redirect('');
        }
    }
    }

/**
 * Method for extraction of ISBN / URN
 * @param file data array
 * @return array of isbns
 **/
    protected function _extractIsbn($csvArray){
    	
    	 $isbnArray = Array();
    	 $c = 0;
    	 foreach ($csvArray as $rows){
    	 	foreach ($rows as $cell){
    	 		$val=str_replace('-','',trim($cell));
    	 			if ((is_numeric($val)&&(strlen($val) == 13))||
    	 				(strlen($val) == 10)&&(is_numeric(substr($val, 0, 9)))){
    	 					$isbnArray[$c]=$val;
    	 					$c++;
    	 		}
    	 }
      }
      return $isbnArray;
    }

/**
 * Method for validate ISBN / URN
 * @param array with isbns
 **/
    protected function _validateIsbn($isbnArray){
    	
    	$j = 0;
    	$mess1 = ' is valid!';
    	$mess2 = ' is not valid or not ISBN!';
    	foreach ($isbnArray as $isbn){
    		//13 digits isbsn
    		if (strlen($isbn) == 13){
    			$sum = 0;
    			$res = 0;
    			for ($i = 0; $i < 12; $i++){
    				if ($i%2 == 0){
    					$sum += $isbn[$i]*1;
    				} else {
    					$sum += $isbn[$i]*3;
    				}
    			$res = (10 - $sum%10)%10;
    			}
    			if ($res == $isbn[12]){
    				$this->_isbnInfo[$j]=$isbn.$mess1;
    				$j++;
    			} else {
    				$this->_isbnInfo[$j]=$isbn.$mess2;
    				$j++;
    				}
    		}
    		// 10 digits isbns
    		if (strlen($isbn) == 10){
    		    $sum = 0;
    		    $res = 0;
    			for ($k = 0; $k < 9; $k++){
    				$sum += $isbn[$k]*($k+1);
    			}
    		$res = $sum%11;
    		if ((($res < 10)&&($res == $isbn[9])) ||
    			(($res == 10) &&($isbn[9]=='X')))
    			{
    				$this->_isbnInfo[$j]=$isbn.$mess1;
    				$j++;
    			}
    			else {
    				$this->_isbnInfo[$j]=$isbn.$mess2;
    				$j++;
    			}
    		}
    	}
    }
    
/**
 * Method for mapping generation
 * @param array of data
 * @return sparql data
 **/
    protected function _createMapping($paramArray){
    	
    	$firstline 	= array();
    	$firstline 	= $paramArray['firstline'];
    	$header		= $paramArray['header'];
    	$baseuri	= $paramArray['baseuri'];
    	$restype	= $paramArray['restype'];
    	$csvf		= $paramArray['filename'];
    	$pref 		= 'ab';
 
    	$len = strlen($baseuri);
    	if ($baseuri[$len-1]!='/') $baseuri .= '/';
    	
    	if (substr_count($baseuri, 'www') > 0){
    		$pref 	= substr($baseuri, stripos($baseuri, '.' )+1, 2);
    	} 
    	if ((substr_count($baseuri, 'www') == 0)&&(substr_count($baseuri, '//') > 0)){
    		$pref 	= substr($baseuri, stripos($baseuri, '//' )+2, 2);
    	}
    	    	
    	$abc = 'abcdefghijklmnopqrstuvwxyz';
    	$map1 = '';
    	$map2 = '';
    	if ($header == 'yes')
    	{
    		$map1 = '?URI a '.$pref.':'.$restype.';'.PHP_EOL.'';
    		
    		foreach ($firstline as $cell){
    			$map1 .= $pref.':'.$cell.' ?'.$cell.';'.PHP_EOL;
    		}
    		$map1 .= '}'.PHP_EOL;
    		$map2 = 
PHP_EOL.'WHERE {'.PHP_EOL.
		'BIND(REPLACE(?'.$firstline[0].', ",", "-") AS ?URI1)'.PHP_EOL.
  		'BIND(REPLACE(?URI1, " ", "-") AS ?URI2)'.PHP_EOL.
        'BIND(REPLACE(?URI2, ":", "-") AS ?URI3)'.PHP_EOL.
  		'BIND (URI(CONCAT("'.$restype.'s/", REPLACE(?URI3, "--", "-"))) AS ?URI)'.PHP_EOL.
		'BIND (CONCAT("URN:ISBN:",?ISBN) AS ?UISBN)'.PHP_EOL.
		'}'.PHP_EOL.
'OFFSET 1'.PHP_EOL;	
    	} 
    	//if ($header == 'no')
    		else
    	{
    		$map1 = '?URI a '.$pref.':'.$restype.';'.PHP_EOL.'';

    		if (count($firstline) < strlen($abc)){
	    		for ($i =0; $i < count($firstline); $i++){
	    			$map1 .= $pref.':'.$abc[$i].' ?'.$abc[$i].';'.PHP_EOL;
	    		}
	    	} else {
	    		$j = 0;
	    		for ($i =0; $i < strlen($abc); $i++){
	    			$map1 .= $pref.':'.$abc[$i].' ?'.$abc[$i].';'.PHP_EOL;
	    			$j++;
	    		}
	    		if ($j < count($firstline))
	    		{
		    		for ($i =0; $i < (strlen($abc)-1); $i++){
		    			if ($j == (count($firstline)-1)) break;
		    			$data =$abc[$i].$abc[$i+1];
		    			$map1 .= $pref.':'.$data.' ?'.$data.';'.PHP_EOL;
				    	$j++;
		    		}
	    		}
	    		if ($j < count($firstline))
	    		{
		    		for ($i =0; $i < (strlen($abc)-2); $i++){
		    			if ($j == (count($firstline)-1)) break;
		    			$data =$abc[$i].$abc[$i+1].$abc[$i+2];
		    			$map1 .= $pref.':'.$data.' ?'.$data.';'.PHP_EOL;
		    			$j++;
		    		}
	    		}
	    		if ($j < count($firstline))
	    		{
		    		for ($i =0; $i < (strlen($abc)-2); $i++){
		    			if ($j == (count($firstline)-1)) break;
		    			$data =$abc[$i].$abc[$i+1].$abc[$i+2].$abc[$i+3];
		    			$map1 .= $pref.':'.$data.' ?'.$data.';'.PHP_EOL;
		    			$j++;
		    			}
	    			}
	    		}
	    	
    	$map1 .= '}'.PHP_EOL;
    	$map2 = 
PHP_EOL.'WHERE {'.PHP_EOL.
		'BIND(REPLACE(?'.$abc[0].', ",", "-") AS ?URI1)'.PHP_EOL.
  		'BIND(REPLACE(?URI1, " ", "-") AS ?URI2)'.PHP_EOL.
        'BIND(REPLACE(?URI2, ":", "-") AS ?URI3)'.PHP_EOL.
  		'BIND (URI(CONCAT("'.$restype.'s/", REPLACE(?URI3, "--", "-"))) AS ?URI)'.PHP_EOL.
		'BIND (CONCAT("URN:ISBN:",?ISBN) AS ?UISBN)'.PHP_EOL.
		'}'.PHP_EOL;
#  BIND (CONCAT('URN:ISBN:',?g) AS ?UISBN)
    	}
    	$str1 =  'BASE <'.$baseuri.'>'.PHP_EOL;
    	$str2 = 'PREFIX '.$pref.':<'.$baseuri.'>'.PHP_EOL.
    			'PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>'.PHP_EOL;
    	$str3 =  'CONSTRUCT {'.PHP_EOL;
    	$str4 = 'FROM <file:'.$csvf.'>'.PHP_EOL;
    	   	
    	$mapping = $str1.$str2.$str3.$map1.PHP_EOL.''.$str4.''.$map2.'';
    	
    	return $mapping;
    }
    
 /**
 * Method for converting of table into turtle
 * uses call of extern programm
 * @return turtle data
 */
    protected function _convert($mapfile, $csvf){
    	
    	$output = array();
    	$map = $mapfile;
    	$csv = $csvf;
    	$root = $_SERVER['DOCUMENT_ROOT'];
    	if (substr($root, -1) != '/')
    			{
    				$root .= '/';
    			}
    	$bin = $root.'OntoWiki/extensions/csvadapter/tarql/bin/tarql';

		 //Command for tarql call & tarql execute
    	$command = $bin . ' ' . $map . ' ' . $csv;
        exec ($command, $output, $return);
        if ($return == 0) {
        	$this->_owApp->appendSuccessMessage('Data successfully converted!'. PHP_EOL);
        	}	
    	return $output;
    }

/**
 * Method for importing and saving of data in triple store
 * @param filename, filetype(extansion), file-locator
 */
private function _import($file, $filetype, $locator)
    
{
        $modelIri = (string)$this->_model;

        try {
            if($this->_erfurt->getStore()->importRdf($modelIri, $file, $filetype, $locator)){
            	$this->_owApp->appendSuccessMessage('Data successfully imported!');
            }
        } catch (Erfurt_Exception $e) {
            // re-throw
            throw new OntoWiki_Controller_Exception(
                'Could not import given model: ' . $e->getMessage(),
                0,
                $e
            );
        }
        
    }
}

/**
 * Help-Class for Parsing for  CSV which Content must be used
 *
 * @author Olga Nudel <olga.nudel@gmail.com>
 */
class Parser 
{
    
	protected $_file = '';
	protected $_resultArr = Array();
	protected $_line = '';
    
/**
 * Help-Method for Parsing
 * @param filename
 * @return resultArray
 **/
    public function _parseFile($filename)
    {

    	$this->_file = $filename;
    	
    	$fileHandle = fopen($this->_file, 'r');
        
        $message = '';
     
        if ($fileHandle === false) {
            $message = 'Could not open file '.$this->_file;
			echo $message;
			return;
        }

    	else {
    	$c=0;
        while (!feof($fileHandle)) {
				$this->_resultArr[$c]=fgetcsv($fileHandle);
				$c++;
        	}
    	}
       
        fclose($fileHandle);  
        return $this->_resultArr;
    }
}
?>
