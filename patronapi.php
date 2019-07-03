<?php
// adjusted code to validate patrons name and card number

$valBarcode ='';
$barcode='';
$valUserTest='';
$user='';
if (isset($_POST['user'])&& isset($_POST['barcode'])) {
// start of login check
// format the name input to play nice no matter what
$user = ucwords(strtolower($_POST['user']));

$barcode = $_POST['barcode'];
  $s = new Sierra(array(
    'endpoint' => 'https://your end point',
    'key' => 'key',
    'secret' => 'secret',
    'tokenFile' => '/tmp'
   ));
 
$customer = $s->query('patrons/find', array(
     'barcode' => "$barcode",
     'fields' => 'names,barcodes'
  ));

// Grab name from array return values and format the string to work- hash the string
$id = $customer['id'];
$salt='your salt';
$key = md5($salt.$id);

$valUser = $customer['names'][0];
$valUser = ucwords(strtolower($valUser));

//Get the last name string which is delimited by a comma -III has last name, first in one field :-(
$valUserTest = substr($valUser, 0, strpos($valUser, ','));
$valBarcode = $customer['barcodes'][0];


if (($valBarcode == $barcode)&& ($valUserTest == $user))
{echo "Success"; // could use header('Location:http://#');
}
else
{$errorMessage = "<span style = 'color:red;'>Incorrect name or card number.</span>";}
}
 /*
 *
 * @author Sean Watkins <slwatkins@uh.edu>
 * @copyright 2014 Sean Watkins
 * @license   MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

class Sierra {
	
/**
 * The Authorization Token array returned when accessing a token request.
 * @var array
 */
    public $token = array();
	
/**
 * The Sierra configuration
 * @var array
 */
   
    public $config = array(
        'tokenFile' => '/tmp'
    );
 /**
 * Constructor
 * @param array $config Array of configuration information for Sierra
 */   
    public function __construct($config) {
        $this->config = array_merge($this->config, $config);
    }
 
/**
 * Makes the resource request
 *
 * @param string $resource The resource being requested
 * @param array $params Array of paramaters
 * @param boolean $marc True to have the response include MARC data
 * @return array Array of data
 */  
 
    public function query($resource, $params = array(), $marc = false) {
    if (!$this->_checkToken()) return null;
        
      $headers = array('Authorization: ' . $this->token['token_type'] . ' ' . $this->token['access_token']);
        if ($marc) {
            $headers[] = 'Accept: application/marc-in-json';
        }
        $response = $this->_request($this->config['endpoint'] . $resource, $params, $headers);
        if ($response['status'] != 200) return null;
        return json_decode($response['body'], true);
    }
 /**
 * Checks if Authentication Token exists or has expired. A new Authentication Token will 
 * be created if one does not exist.
 *
 * @return boolean True if token is valid
 */  
    private function _checkToken() {
        if (file_exists($this->config['tokenFile'])) {
            $this->token = json_decode(file_get_contents($this->config['tokenFile']), true);
        }
        
        if (!$this->token || (time() >= $this->token['expires_at'])) {
            return $this->_accessToken();
        }
        return true;
    }

 /**
 * Requests a Authentication Token from Sierra
 *
 * @return boolean True if a token is created
 */
	private function _accessToken() {
        $auth = base64_encode($this->config['key'] . ':' . $this->config['secret']);
    
        $response = $this->_request($this->config['endpoint'] . 'token', array('grant_type', 'client_credentials'), array('Authorization: Basic ' . $auth), 'post');
        $token = json_decode($response['body'], true);
        if (!$token) return false;
        if (!isset($token['error'])) {
            $token['expires_at'] = time() + $token['expires_in'];
        
            $this->token = $token;
            file_put_contents($this->config['tokenFile'], json_encode($token));
            return true;
        }
        
        return false;
    }
/**
 * Requests data from Sierra
 *
 * @param string $url The full URL to the REST API call
 * @param array $params The query paramaters to pass to the call
 * @param array $header Additional header information to include
 * @param string $type The request type 'GET' or 'POST'
 * @return array Result array
 * 
 * ### Result keys returned
 * - 'status': The return status from the server
 * - 'header': The header information from the server
 * - 'body': The body of the message
 */
    private function _request($url, $params = array(), $header = array(), $type = 'get') {
        $type = strtolower($type);
        $s = curl_init();
        
        if ($type == 'post') {
            $header[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($s, CURLOPT_POST, true);
            curl_setopt($s, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        else {
            $url .= ($params ? '?' . http_build_query($params) : '');
        }
        
        curl_setopt($s, CURLOPT_URL, $url);
        curl_setopt($s, CURLOPT_TIMEOUT, 60);
        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($s, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($s, CURLOPT_USERAGENT, 'Sierra PHP Test/0.1');
        curl_setopt($s, CURLOPT_HEADER, true);
    
        if ($header) {
            curl_setopt($s, CURLOPT_HTTPHEADER, $header);
        }
        $result = curl_exec($s);
        $status = curl_getinfo($s, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($s, CURLINFO_HEADER_SIZE);
        $header = $this->_parseResponseHeaders(substr($result, 0, $headerSize));
        $body = substr($result, $headerSize);
    
        $response = array(
            'status' => $status,
            'header' => $header,
            'body' => $body
        );	
		echo $result; //for debug
		echo $status; //for debug
        curl_close($s);
    
        return $response;
    }
/**
 * Parse response headers into a array
 *
 * @param string $header The header information as a string
 * @return array
 */
    private function _parseResponseHeaders($header) 
	{
       $headers = array();
        $h = explode("\r\n", $header);
        foreach ($h as $header) 
		   {
            if (strpos($header, ':') !== false) 
			    {
                list($type, $value) = explode(":", $header, 2);
                if (isset($headers[$type])) 
				    {
                    if (is_array($headers[$type])) 
					    {
                        $headers[$type][] = trim($value);
                        }
                    else 
					    {
                        $headers[$type] = array($headers[$type], trim($value));
                        }
				     }
                    else 
				       {
                    $headers[$type] = trim($value);
                       }
                 }
             }
                  return $headers;
      }
	  
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title>User Login</title>

<script src="/SpryAssets/SpryValidationTextField.js" type="text/javascript"></script>
<link href="/SpryAssets/SpryValidationTextField.css" rel="stylesheet" type="text/css">
<style>
/* Basics */
html, body {
    width: 100%;
    height: 100%;
    font-family: "Helvetica Neue", Helvetica, sans-serif;
    color: #444;
    -webkit-font-smoothing: antialiased;
    background: #f0f0f0;
}
#container {
    position: fixed;
    width: 340px;
    height: 295px;
    top: 50%;
    left: 50%;
    margin-top: -140px;
    margin-left: -170px;
	background: #fff;
    border-radius: 3px;
    border: 1px solid #ccc;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .1);
	
}
form {
    margin: 0 auto;
    margin-top: 20px;
}
label {
    color: #555;
    display: inline-block;
    margin-left: 18px;
    padding-top: 10px;
    font-size: 14px;
}
p a {
    font-size: 11px;
    color: #aaa;
    float: right;
    margin-top: -13px;
    margin-right: 20px;
 -webkit-transition: all .4s ease;
    -moz-transition: all .4s ease;
    transition: all .4s ease;
}
p a:hover {
    color: #555;
}
input {
    font-family: "Helvetica Neue", Helvetica, sans-serif;
    font-size: 12px;
    outline: none;
}
input[type=text],
input[type=password] {
    color: #777;
    padding-left: 10px;
    margin: 10px;
    margin-top: 12px;
    margin-left: 18px;
    width: 290px;
    height: 35px;
	border: 1px solid #c7d0d2;
    border-radius: 2px;
    box-shadow: inset 0 1.5px 3px rgba(190, 190, 190, .4), 0 0 0 5px #f5f7f8;
-webkit-transition: all .4s ease;
    -moz-transition: all .4s ease;
    transition: all .4s ease;
	}
input[type=text]:hover,
input[type=password]:hover {
    border: 1px solid #b6bfc0;
    box-shadow: inset 0 1.5px 3px rgba(190, 190, 190, .7), 0 0 0 5px #f5f7f8;
}
input[type=text]:focus,
input[type=password]:focus {
    border: 1px solid #a8c9e4;
    box-shadow: inset 0 1.5px 3px rgba(190, 190, 190, .4), 0 0 0 5px #e6f2f9;
}
#lower {
    background: #ecf2f5;
    width: 100%;
    height: 69px;
    margin-top: 10px;
	  box-shadow: inset 0 1px 1px #fff;
    border-top: 1px solid #ccc;
    border-bottom-right-radius: 3px;
    border-bottom-left-radius: 3px;
}
input[type=checkbox] {
    margin-left: 20px;
    margin-top: 30px;
}
.check {
    margin-left: 3px;
	font-size: 11px;
    color: #444;
    text-shadow: 0 1px 0 #fff;
}
input[type=submit] {
    float: right;
    margin-right: 20px;
    margin-top: 20px;
    width: 80px;
    height: 30px;
font-size: 14px;
    font-weight: bold;
    color: #fff;
    background-color: #acd6ef; /*IE fallback*/
    background-image: -webkit-gradient(linear, left top, left bottom, from(#acd6ef), to(#6ec2e8));
    background-image: -moz-linear-gradient(top left 90deg, #acd6ef 0%, #6ec2e8 100%);
    background-image: linear-gradient(top left 90deg, #acd6ef 0%, #6ec2e8 100%);
    border-radius: 30px;
    border: 1px solid #66add6;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .3), inset 0 1px 0 rgba(255, 255, 255, .5);
    cursor: pointer;
}
input[type=submit]:hover {
    background-image: -webkit-gradient(linear, left top, left bottom, from(#b6e2ff), to(#6ec2e8));
    background-image: -moz-linear-gradient(top left 90deg, #b6e2ff 0%, #6ec2e8 100%);
    background-image: linear-gradient(top left 90deg, #b6e2ff 0%, #6ec2e8 100%);
}
input[type=submit]:active {
    background-image: -webkit-gradient(linear, left top, left bottom, from(#6ec2e8), to(#b6e2ff));
    background-image: -moz-linear-gradient(top left 90deg, #6ec2e8 0%, #b6e2ff 100%);
    background-image: linear-gradient(top left 90deg, #6ec2e8 0%, #b6e2ff 100%);
}
</style>

</head>
<body onLoad="document.forms[0].elements[0].focus();">
 <div id="container">
 
   <?php if (isset($_POST)&&($valBarcode != $barcode)||($valUserTest != $user)) {
	echo $errorMessage;
  }?>
 <form id="form" name="form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
       <label>Last Name:</label>
       <span id="checkUser">
       <span class="textfieldRequiredMsg">Required.</span>
       <input name="user" type="text" tabindex="1" value="" size="10"/>
       </span>
       <label>Library Card Number:</label>
       <span id="checkBar">
       <span class="textfieldRequiredMsg">Required.</span>
       <input name="barcode" type="password" tabindex="2" value="" size="15" maxlength="15"/><br />
       </span>
       <div id="lower">
       <label></label><input class="button-primary" type="submit" value="Submit" tabindex="3" />
       </div>
    </form>
   </div>
</div>
<script type="text/javascript">
var sprytextfield1 = new Spry.Widget.ValidationTextField("checkUser");
var sprytextfield2 = new Spry.Widget.ValidationTextField("checkBar");
</script>
</body>
</html>
 
 