<?php

/**
 * @copyright 2015 webbilling.com 
 * @package Webbilling
 * @version 1.0
 * @todo add curl
 */

namespace Webbilling;
	
	/**
	 * @property-read array $arrSupportedParams
	 * @property-write array $arrSupportedParams  
	*/
	class Properties
	{
		
		protected $arrSupportedParams;	
		protected $arrSettedParams = array();
	
		public function __get ( $strKey )
		{
			if (in_array($strKey, $this->arrSupportedParams))
				return $this->arrSettedParams[$strKey];
			else
				throw new \Exception("Parameter $strKey not supported");
		}
		
		public function __set ( $strKey, $mixedValue )
		{
			if (in_array($strKey, $this->arrSupportedParams))
				$this->arrSettedParams[$strKey] = $mixedValue;
			else
				throw new \Exception("Parameter $strKey not supported");
			
		}
		
		/**
		 * 
		 * @return Ambigous <array:, unknown>
		 */
		protected function get()
		{
			return $this->arrSettedParams;
		}
	}
	
	class Joinpage extends Properties
	{
		
		const SECURITY_LVL_LOW 	= 1;
		const SECURITY_LVL_HIGH	= 2;
		const MODE_TEST			= 0;
		const MODE_LIVE			= 1;
		const URLCLIENT_FOPEN	= "fopen";
		const URLCLIENT_CURL	= "curl";
		
		private $objSecurity;
		private $objCustomer;
		private $objPayment;
		
		private $arrMerge,$arrDomain = array();
		private $strSalt;
		private $intSecLvl;
		private $intMode = 0; 
		/**
		 * utf8 is standard encoding; set to false 4 ISO
		 * @param boolean $boolUTF8
		 */
		public function __construct( )
		{
			$this->arrSupportedParams = array ( "merchantid",
												"merchantpass",
												"utf8", 
												"lgid", 
												"countryid",
												"transactionid", 	
												"def_param_set",
												"webmaster",
												"processor_id",
												"pt1",
												"pt2", 
												"pt3",
												"tpl", 
												"callback_url", 
												"post_back_url",
												"return_url",
												"post_back_url2",
												"alternative_billing_url",
												"testpage",
												"outputmedia",
												"bgcolor",
												"acolor",
												"color"
					
			);	
			
			$this->arrDomain = array ( "https://testjoinpage.webbilling.com", "https://joinpage.webbilling.com");
			
			$this->utf8 = 1; // use utf8 encoding as default; otherwise iso
			$this->intSecLvl = Joinpage::SECURITY_LVL_LOW;
			$this->objSecurity = new Security();
			
		}
		
		/**
		 * setup your merchant salt to encrypt / decrypt 
		 * @api
		 * @return void
		 */
		public function setSalt ($strSalt)
		{
			$this->strSalt = $strSalt;
		}
		
		/**
		 * setup the mode (test or live operating)
		 * @api
		 * @return void
		 */
		public function setMode( $intMode = Joinpage::MODE_TEST )
		{
			$this->intMode = $intMode;
		}
		
		/**
		 * setup the level of security (Joinpage::SECURITY_LVL_LOW / SECURITY_LVL_HIGH)
		 * HIGH recommended for initial joinpage call - we post another parameter to check the integrity   
		 * @api
		 * @return void
		 */
		public function setSecurityLvl ( $intSecLvl )
		{
			$this->intSecLvl = $intSecLvl;
		}
		
		/**
		 * setup the customer object if you have any (which holds customer data)
		 * @api
		 * @return void
		 */
		public function setCustomer ( Customer $objCustomer )
		{
			$this->objCustomer = $objCustomer;
		}

		/**
		 * setup the payment object if you have any (which holds payment data)
		 * @api
		 * @return void
		 */
		
		public function setPayment ( Payment $objPayment)
		{
			$this->objPayment = $objPayment;
		}
		
		public function getDomain()
		{
			return $this->arrDomain[$this->intMode];
		}
		
		/**
		 * getter for URL-encoded query string
		 * @api
		 * @return string
		 */
		
		public function getBuildQuery()
		{
			$this->mergeProperties();
			$this->doSecurity();
			return http_build_query( $this->arrMerge );
		}
		
		/**
		 * getter for html - which holds all params as 'input type hidden' 
		 * @api
		 * @return string
		 */
		public function getHiddenFields()
		{
			$strReturn = "";
			$this->mergeProperties();
			$this->doSecurity();
			foreach ($this->arrMerge as $key => $value)
				$strReturn .= '<input type="hidden" name="'.$key.'" value="'.$value.'">';
			return $strReturn;
		}
		
		public function executeFollow($strClient=self::URLCLIENT_FOPEN)
		{
			$strClient = "URLCLIENT_".$strClient;
			//$objClient = new $strClient(self::getBuildQuery(), self::getDomain());
			$objClient = new URLClient_fopen(self::getBuildQuery(),self::getDomain()."/settransaction.php");
			return $objClient->post();
		}
		
		protected function mergeProperties($strClassName=null)
		{
			if (is_null($strClassName))
			{
				$this->arrMerge = $this->get(); // inititalize with vars setuped with magic setter
				$arrReflection = (new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PRIVATE);
				foreach ($arrReflection as $objProperty)
				{
					$strClassName = $objProperty->getName(); 
					if (is_object($this->$strClassName))
					{
						$this->arrMerge = $this->arrMerge + $this->$strClassName->get(); //array_merge($this->arrMerge, $this->$strClassName->get() );
						
					}
				}	
			}
			elseif( is_object($this->$strClassName) )
			{
				$this->arrMerge = array_merge($this->arrMerge, $this->$strClassName->get() );
			}
			return $this->arrMerge;
		}
		
		protected function doSecurity()
		{
			if (!is_null($this->strSalt))
			{
				$this->arrMerge['merchantpass'] 	= Security::encrypt($this->arrMerge['merchantpass'], $this->strSalt);
				$this->arrMerge['product_code'] 	= Security::encrypt($this->arrMerge['product_code'], $this->strSalt); 
				if ($this->intSecLvl == self::SECURITY_LVL_HIGH)
				{
					$this->objSecurity->execute( $this->arrMerge, $this->strSalt);
					$this->mergeProperties("objSecurity"); // merge security vars to array 
					
					
				}
			}
			
		}
		
		
		
	}
	
	abstract class URLClient
	{
		protected $strQuery;
		protected $strURL;
		
		public function __construct( $strQuery, $strURL )
		{
			$this->strQuery = $strQuery;
			$this->strURL = $strURL;
		}
		
		
		abstract protected function post();
		
	}
	
	class URLClient_fopen extends URLClient
	{
		public function post()
		{
			$arrOptions = array (
									'http' => array (
									'method' => 'POST',
									'header'=> "Content-type: application/x-www-form-urlencoded\r\n"
									. "Content-Length: " . strlen($this->strQuery) . "\r\n",
									'content' => $this->strQuery
									)
								);
		
		
			$resource = stream_context_create($arrOptions);
			$handle = fopen($this->strURL, 'r', false, $resource);
			$return = fread($handle,8192);
			fclose ($handle);
			return $return;	
		}
	}
	
	class Payment extends Properties
	{
		
		const TRIAL 			= 1;
		const FULL 				= 3;

		

		const SINGLE_PAYMENT	= 0;
		
		const CURRENCY_EURO		= "EUR";
		const CURRENCY_POUND	= "GBP";
		const CURRENCY_DOLLAR	= "USD";
		
		private $arrPayTypes;
		private $arrProductCode = array();
		
		
		public function __construct()
		{
			$this->arrSupportedParams = array (	"product_code",
												"product_currency",
												"product_code_gbp",
												"product_code_eur",
												"package_group",
												"package_selectable",
												"selected_package",
												"domain",
												"max_rebills",
												"product_desc",
												"product_type",
												"xselldata",
												"product_name",
												"bank_stmt_descr",
												"paytype",
					
			);
		
			$this->arrPayTypes = array ( "dd","adp","sepa","sb","cc","wccc","cab","p24","bz","psc",	);
		}
		
		/**
		 * setup your type of currency
		 * @param string $strCurrency (CURRENCY_EURO / CURRENCY_POUND / CURRENCY_DOLLAR...)
		 */
		public function setCurrency( $strCurrency ) 
		{
			$this->product_currency = $strCurrency;
		}
		
		/*
		 * @param integer $intPeriod (set period of time for next rebill; set SINGLE_PAYMENT for onetime payment)
		 * @param string $intWhich (use constant TRIAL / FULL)
		 */
		public function setPeriod( $intPeriod = self::SINGLE_PAYMENT, $intWhich = self::FULL)
		{
			$this->arrProductCode[$intWhich+1] = $intPeriod;
		}
		
		
		/**
		 * setup your type of amount and automatically setup EUR as currency if it is not setup
		 * @param double $dAmount
		 * @param string $intWhich (use constant TRIAL / FULL)
		 */
		public function setAmount( $dAmount, $intWhich = self::FULL ) 
		{ 
			$this->arrProductCode[$intWhich] = $dAmount;
			// if (!isset($this->product_currency))
			// 	$this->product_currency = Payment::CURRENCY_EURO;
		}
		
		public function setPackage() 
		{ 
			
		}
		
		/**
		 *
		 * @return Ambigous <array:, unknown>
		 */
		public function get()
		{
			if (isset($this->arrProductCode[1]) && !isset($this->arrProductCode[3]) )
					throw new \Exception("Wrong product_code setup - trial without full amount given.");
			elseif (count($this->arrProductCode) != 2 && count($this->arrProductCode) != 4) 
					throw new \Exception("Wrong product_code setup - empty period?");
			
			
			$this->product_code = implode ("|",$this->arrProductCode);
			
			return parent::get();
		}
		
	}
	
	class Customer extends Properties
	{
		public function __construct()
		{
			$this->arrSupportedParams = array (	"email",
												"fname",
												"lname",
												"street",
												"zip",
												"city",
												"countryid"
												
			);
		}
		
	}
	
	class Security extends Properties
	{
		private $arrKeys;
		private static $arrKnownParams = array(	"desc"						=> 0x01,
												"email"						=> 0x02,
												"fname"						=> 0x03,
												"lname"						=> 0x04,
												"street"					=> 0x05,
												"zip"						=> 0x06,
												"city"						=> 0x07,
												"product_code"				=> 0x08,
												"product_currency"			=> 0x09,
												"domain"					=> 0x0A,
												"screen_desc"				=> 0x0B,
												"bank_stmt_descr"			=> 0x0C,
												"def_param_set"				=> 0x0D,
												"transactionid"				=> 0x0E,
												"jumpback_url"				=> 0x0F,
												"jumpback_select"			=> 0x10,
												"cpid"						=> 0x11,
												"username"					=> 0x12,
												"password"					=> 0x13,
												"webmaster"					=> 0x14,
												"processor_id"				=> 0x15,
												"pt1"						=> 0x16,
												"pt2"						=> 0x17,
												"pt3"						=> 0x18,
												"callback_url"				=> 0x1F,
												"post_back_url"				=> 0x20,
												"return_url"				=> 0x21,
												"post_back_url2"			=> 0x22,
												"product_name"				=> 0x23,
												"partner"					=> 0x24,
												"secpin"					=> 0x25,
												"alternative_billing_url"	=> 0x26,
												"xsell"						=> 0x27,
												"package_group"				=> 0x28,
												"selected_package"			=> 0x2c,
												"package_selectable"		=> 0x29,
												"product_code_eur"			=> 0x2a,
												"product_code_gbp"			=> 0x2b,
												"recurring_by_merchant"		=> 0x2d,
												"xselldata"					=> 0x2e,
												"use_package_group_url"		=> 0x2f,
												"package_group_param"		=> 0x30,
												"max_rebills"				=> 0x31,
												"product_desc"				=> 0x32,
												"paytype"					=> 0x33,
												"additionaldatasecure"		=> 0x34,
												"profile"					=> 0x35,
												"cumulation_desc"			=> 0x36,
												"customerip"				=> 0x37,
												"ContentPriceID"			=> 0x38,
												"creditid"					=> 0x39,
										);
		
		public function __construct()
		{
				$this->arrSupportedParams = array ( "sectokenkeys", "sectoken" );
		}
		
		
		public function execute($arrKeyValues, $strSalt)
		{
			
			list( $this->sectokenkeys, $this->sectoken) = self::generateToken($arrKeyValues, array_flip( array_intersect_key($arrKeyValues, self::$arrKnownParams)) , $strSalt);
		}
		
		
		public static function encrypt( $strUncrypted , $strSalt )
		{
			while ( strlen( $strSalt ) < strlen( $strUncrypted ) )
			{
				$strSalt .= $strSalt;
			}
		
			return urlencode( base64_encode( $strUncrypted ^ $strSalt ) );
		}
		
		public static function decrypt( $strCrypted , $strSalt )
		{
			$strBase64Decode = base64_decode( urldecode( $strCrypted ));
		
			if( $strBase64Decode != '' )
			{
				while ( strlen( $strSalt ) < strlen( $strBase64Decode ) )
				{
					$strSalt .= $strSalt;
				}
		
				return $strBase64Decode ^ $strSalt ;
			}
			else
			{
				throw new Exception( 'base64_decode returns empty string - can´t decrypt' );
			}
		}
		
		public static function getToken( $arr, $strSalt, &$buffer=null)
		{
			$strBuffer = "";
			if( is_array( $arr))
			{
				foreach( $arr as $strKey => $strValue)
				{
					$strBuffer .= $strKey.$strValue;
				}
			}
			if( strlen( $strBuffer))
			{
				$buffer = $strBuffer.$strSalt;
				return md5( $strBuffer.$strSalt);
			}
			return null;
		}
		
		public static function generateToken( $arrKeyValues, $arrKeys, $strSalt)
		{
			$strSecTokenKeys = null;
			$strSecToken = null;
			if( is_array( $arrKeyValues) && is_array( $arrKeys))
			{
				$arr = array();
				foreach( $arrKeys as $strKey)
				{
					if( array_key_exists( $strKey, self::$arrKnownParams) && !is_null( self::$arrKnownParams[ $strKey]) && array_key_exists( $strKey, $arrKeyValues))
					{
						$strSecTokenKeys .= sprintf( "%02x", self::$arrKnownParams[ $strKey]);
						$arr[ $strKey] = $arrKeyValues[ $strKey];
					}
				}
				$strSecToken = self::getToken( $arr, $strSalt);
			}
			return array( $strSecTokenKeys, $strSecToken);
		}
		
		public static function getTokenKeys( $strSecTokenKeys)
		{
			$arrKnownParameter = array_flip( array_diff(self::$arrKnownParams,array(null)));
			$arr = array();
			for( $i=0; $i<strlen( $strSecTokenKeys); $i+=2)
			{
				$strKey = hexdec(substr( $strSecTokenKeys, $i, 2));
				if( array_key_exists( $strKey, $arrKnownParameter))
				{
					$arr[] = $arrKnownParameter[$strKey];
				}
			}
			return $arr;
		}
		
		public static function checkToken( $arrKeyValues, $strSecTokenKeys, $strSecToken, $strSalt)
		{
			$arrKnownParameter = array_flip( array_diff(self::$arrKnownParams,array(null)));
			$arr = array();
			
			for( $i=0; $i<strlen( $strSecTokenKeys); $i+=2)
			{
				$strKey = hexdec(substr( $strSecTokenKeys, $i, 2));
				echo "Checking ".$strKey." => ". $arrKnownParameter[ $strKey]." with value ".$arrKeyValues[ $arrKnownParameter[ $strKey]];
				if( array_key_exists( $strKey, $arrKnownParameter) && array_key_exists( $arrKnownParameter[ $strKey], $arrKeyValues))
				{
					$arr[ $arrKnownParameter[ $strKey]] = $arrKeyValues[ $arrKnownParameter[ $strKey]];
				}
			}
			
			return self::getToken( $arr, $strSalt) == $strSecToken;
		}
		
		
		

	}

?>