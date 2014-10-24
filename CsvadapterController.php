<?php

/**
 * csv adapter controller.
 *
 * @category OntoWiki
 * @package Extensions_csvadapter
 * @author Olga Nudel <olga.nudel@gmail.com>
 * TODO comments
 */



class CsvadapterController extends OntoWiki_Controller_Component
{
	
	private $_model = null;
    private $_post = null;
    private $_parser = null;
    public $_firstline = array();
    private $_csvf = '';
    private $_isbnInfo = array();
    
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

        /*if ($this->_request->isPost()) {
            $this->_post = $this->_request->getPost();
        }*/
    }

         /*
         * User Interface
         */
    public function indexAction()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('indexAction');
        
        $this->view->placeholder('main.window.title')->set('Upload CSV File');
        
        
        $this->view->formEncoding     = 'multipart/form-data';
        $this->view->formClass        = 'simple-input input-justify-left';
        $this->view->formMethod       = 'post';
        $this->view->formName         = 'upload';
        $this->view->supportedFormats = array(
            'xml'   => 'XML',
            'csv'   => 'CSV'
        );

        $toolbar = $this->_owApp->toolbar;
        $toolbar->appendButton(
            OntoWiki_Toolbar::SUBMIT,
            array('name' => 'Select', 'id' => 'upload')
        )->appendButton(
            OntoWiki_Toolbar::RESET,
            array('name' => 'Cancel', 'id' => 'upload')
        );
        $this->view->placeholder('main.window.toolbar')->set($toolbar);
        
        
        if ($this->_request->isPost()) {
         
         	$upload = new Zend_File_Transfer();
         	$filesArray = $upload->getFileInfo();
         	$this->_csvf=$filesArray['source']['tmp_name'];
         
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
         
         
        chmod($this->_csvf, 0644);

        $this->_parser = new Parser();
        $dt=$this->_parser->_parseFile($this->_csvf);
        
        $in = $_SERVER['DOCUMENT_ROOT'].'file.csv';
        chmod($in, 0644);
        copy ($this->_csvf, $in);
        
        /*$temp = fopen($in, 'wb');
        $result = '';
        $csvi = '';
        foreach ($dt as $cells){
    		foreach ($cells as $cell){
    			$result = str_replace('"', '""', $cell);
    			$cell = $result;
    		}
    		$csvi = implode(',',$cells).PHP_EOL;
    		fwrite($temp, $csvi);
    	}
    	fclose($temp);*/
        
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
                     
        $_SESSION['params'] = $this->_csvf;
        if ((count($this->_firstline))>0){
        	$_SESSION['flinecount'] = count($this->_firstline);
        	$_SESSION['fline']=implode(';',$this->_firstline);
        	$result = '';
        	$res = array();
        	foreach ($dt as $cells){
        		$res = $cells;
        		$result .= implode(';;;',$res).';;;;';
        	}
        	$_SESSION['data']=$result;
        	}
        $this->_redirect('csvadapter/import');
        }
    }

    public function importAction()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('importAction');
               
        $this->view->placeholder('main.window.title')->set('Select mapping parameter');  
        $this->view->formEncoding     = 'multipart/form-data';
        $this->view->formClass        = 'simple-input input-justify-left';
        $this->view->formMethod       = 'post';
        $this->view->formName         = 'selection';
        $this->view->filename		  = htmlspecialchars($_SESSION['params']);
        $this->view->restype   		  = '';
        $this->view->baseuri		  = '';
        $this->view->header		  	  = '';
        $this->view->flinecount		  =  htmlspecialchars($_SESSION['flinecount']);
        $this->view->fline			  =  htmlspecialchars($_SESSION['fline']);
        $this->view->line		  	  =  explode(';',$this->view->fline);
        $this->view->dt		  	  	  =  htmlspecialchars($_SESSION['data']);
        
        chmod($this->view->filename, 0644);
	        

        $toolbar = $this->_owApp->toolbar;
        $toolbar->appendButton(OntoWiki_Toolbar::SUBMIT, array('name' => 'Submit', 'id' => 'selection'))
                ->appendButton(OntoWiki_Toolbar::RESET, array('name' => 'Cancel', 'id' => 'selection'));
        $this->view->placeholder('main.window.toolbar')->set($toolbar);
        
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
                $message = 'Ressource type must not be empty.';
                $this->_owApp->appendMessage(new OntoWiki_Message($message, OntoWiki_Message::ERROR));
        }
        if ((trim($baseuri) == '')||(trim($baseuri) == 'http://')) {
                $message = 'Base Uri must not be empty.';
                $this->_owApp->appendMessage(new OntoWiki_Message($message, OntoWiki_Message::ERROR));
        }
        $paramArray = array('firstline' => $this->view->line,
        					'header' 	=> $header,
        					'baseuri'	=> $baseuri,
        					'restype' 	=> $restype,
        					'filename' 	=> $this->view->filename
        );
        
        
        $maippng = $this->_createMapping($paramArray);
        $maprpl1  = str_replace('"', "'", $maippng);
        $mapfile = $_SERVER['DOCUMENT_ROOT'].'mapping.sparql';
        chmod($mapfile, 0644);
        $fp = fopen($mapfile,"wb");
        fwrite($fp,$maprpl1);
        fclose($fp);
        
        $ttl = array();
        $ttl = $this->_convert($mapfile, $this->view->dt);
        
            	$file1 = tempnam(sys_get_temp_dir(), 'ow');
            	$temp = fopen($file1, 'wb');
    	foreach ($ttl as $line) {
    		fwrite($temp, $line . PHP_EOL);
    		}
    		fclose($temp);
    		$filetype = 'ttl';
    		
    		$locator  = Erfurt_Syntax_RdfParser::LOCATOR_FILE;

        try {
                $this->_import($file1, $filetype, $locator);
            } catch (Exception $e) {
                $message = $e->getMessage();
                $this->_owApp->appendErrorMessage($message);
                return;
            }

         $this->_owApp->appendSuccessMessage('Data successfully imported.');
        
        $this->_redirect('index');
        }
    }
    }

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
    
   protected function _extractFirstrow($csvArray){
    	
    	 $firstLine = Array();
    	 $c = 0;
    	 $i = 0;
    	 foreach ($csvArray as $rows){
    	 	foreach ($rows as $cell){
    	 		if ($c == 0){
    	 			$firstLine[$i]=$cell;
    	 		}
    	 		$i++;
    	 		if ($c > 0) return;
    	 }
		$c++;
      }
      return $firstLine; 
    }
    
    protected function _validateIsbn($isbnArray){
    	
    	$j = 0;
    	$mess1 = ' is valid!';
    	$mess2 = ' is not valid or not ISBN!';
    	foreach ($isbnArray as $isbn){
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
    
    protected function _createMapping($paramArray){
    	$firstline 	= array();
    	$firstline 	= $paramArray['firstline'];
    	$header		= $paramArray['header'];
    	$baseuri	= $paramArray['baseuri'];
    	$restype	= $paramArray['restype'];
    	$filename	= $paramArray['filename'];
    	$pref 		= 'ab';
    	chmod($filename, 0644);

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
    	} else {
    		$map1 = '?URI a '.$pref.':'.$restype.';'.PHP_EOL.'';
    		for ($i =0; $i < count($firstline); $i++){
    			$map1 .= $pref.':'.$abc[$i].' ?'.$abc[$i].';'.PHP_EOL;
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
    	$str4 = 'FROM <file:file.csv>'.PHP_EOL;
    	   	
    	$mapping = $str1.$str2.$str3.$map1.PHP_EOL.''.$str4.''.$map2.'';
    	
    	return $mapping;
    }
    
    protected function _convert($mapfile){
    	
    	$map = $mapfile;
    	$csvdt = $csv;
    	chmod($map, 0644);
    	chmod($csv, 0644);
    	$output = array();
    	$prgpath ='/OntoWiki/extensions/csvadapter/tarql/bin/tarql';
    	
    	chmod ($_SERVER['DOCUMENT_ROOT'], 0777);
    	$bin = $_SERVER['DOCUMENT_ROOT'].'OntoWiki/extensions/csvadapter/tarql/bin/tarql';
    	$sparqlFile = $_SERVER['DOCUMENT_ROOT'].'mapping.sparql';
    	$in = $_SERVER['DOCUMENT_ROOT'].'file.csv';
    	chmod($sparqlFile, 0644);
    	chmod($in, 0644);
		 //.'2>&1'
        $command = $bin . ' ' . $sparqlFile . ' ' . $in;
        exec ($command, $output, $return);
        if ($return == 0) {
        	$this->_owApp->appendSuccessMessage('Data successfully converted!'. PHP_EOL);
        	}
        	
    	return $output;
    }
    
private function _import($fileOrUrl, $filetype, $locator)
    {
        $modelIri = (string)$this->_model;

        try {
            $this->_erfurt->getStore()->importRdf($modelIri, $fileOrUrl, $filetype, $locator);
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


class Parser 
{
    
	protected $_file = '';
	protected $_resultArr = Array();
	protected $_line = '';
    
	
    public function _parseFile($filename)
    {

    	$this->_file = $filename;
    	chmod($this->_file, 0644);
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