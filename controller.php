<?php

require_once 'ResourceLoader.php';
require_once 'RequestHelper.php';
require_once 'ResponseHelper.php';


$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];

$matches = null;

preg_match('!api/([^/]+)/([^/]+)(/([\w-]+)(/([^/]+))?)?(/?\?(.*))?!', $request_uri, $matches);


if(!$matches)
{
  echo ResponseHelper::construct_response(400, null, 'Bad Request', null);
  exit();
}

list($all_matches, $resource_version, $resource_name, $garbage_1, $resource_id, $garbage_2, $sub_resource_name, $garbage_3, $query_string) = $matches;

$resource = null;

list($resource_path, $resource_name) = ($resource_name && $resource_version) ? ResourceLoader::get_resource_info($resource_name, $resource_version) : array(null, null);


if($resource_path)
{
  require_once $resource_path;
  if ($resource_name) $resource = new $resource_name;
}

if(!$resource)
{
  echo ResponseHelper::construct_response(404, null, "Resource Not Found", null);
  exit();
}

try
{
  $request_data = file_get_contents('php://input') or $request_data = $HTTP_RAW_POST_DATA;

  $parsed_request_data = RequestHelper::parse_request($request_data);
  
  validate_request_token($method, $resource_name, $resource_id, $sub_resource_name, $query_string, $parsed_request_data);
  
  $response_data = handle_function_call($method, $resource, $resource_id, $sub_resource_name, $query_string, $parsed_request_data);

  echo ResponseHelper::construct_response(200, null, 'Success', $response_data);

  exit();
}
catch (Exception $exception)
{
  echo ResponseHelper::construct_response(500, null, 'Internal Server Error', null);
  
  exit();
}

function handle_function_call($method, $resource, $resource_id, $sub_resource_name, $query_string, $data)
{  
  $result = null;

  if ($method && $resource)
  {
    $data or $data = new stdClass();
    
    $data->resource_id = $resource_id;

    $data->query_string = $query_string;

    $function_name = strtolower($method.(($sub_resource_name) ? '_'.$sub_resource_name : ''));

    if (method_exists($resource, $function_name))
    {
      try
      {
        $result = $resource->$function_name($data);
      }
      catch (Exception $e)
      {
        echo ResponseHelper::construct_response(500, $e->getCode(), $e->getMessage(), null);

        exit();
      }
    }
    else
    {
      echo ResponseHelper::construct_response(404, null, "Method Not Found $function_name", null);

      exit();
    }
  }
 
  return $result;
}

function validate_request_token($method, $resource, $resource_id, $sub_resource_name, $query_string, $data)
{
  try
  {
    // The intention here is that the Class User will be doing the Auth of the Signature and Partner ID
    // This is to create a User
    // We don't want DB access in the Controller
    if(strtolower($method) == 'post' && $resource == 'User')
    { 
      if(empty($data->signature)) { throw new Exception('Missing signature'); }
      if(empty($data->partner_id)) { throw new Exception('Missing partner_id'); }  
      return true;
    }
    
    if(strtolower($method) == 'post' || strtolower($method) == 'put')
    {
      $client_token = $data->token;
      $account_id = $data->account_id;
      $timestamp = $data->timestamp;
      $partner_id = $data->partner_id; 
    }
    elseif (strtolower($method) == 'get' || strtolower($method) == 'delete') 
    {
      parse_str($query_string, $partner_parameters);
      $client_token = $partner_parameters['token'];
      $account_id = $partner_parameters['account_id'];
      $timestamp = $partner_parameters['timestamp'];
      $partner_id = $partner_parameters['partner_id']; 
    }
    
    if((strtolower($method) == 'get' || strtolower($method) == 'put') && $resource == 'User')
    {
      $account_id = $resource_id;
    } 

    if(empty($client_token)) { throw new Exception('Missing token'); }
    if(empty($account_id)) { throw new Exception('Missing account_id'); }
    if(empty($timestamp)) { throw new Exception('Missing timestamp'); }
    if(empty($partner_id)) { throw new Exception('Missing partner_id'); }
    
    $system_secret = 'SystemSecret'; 
    $user_token_timeout = '+1 hours'; 
    
    $concat_string = $account_id .  $timestamp . $partner_id . $system_secret;
    $my_token =  sha1($concat_string); 
    
    if($client_token == $my_token)
    {
      $now = strtotime(date("Y-m-d\TG:i:s"));
      $limit =  strtotime($user_token_timeout) - $now;
      $token_valid_date = strtotime($timestamp) + $limit;
      
      if($now > $token_valid_date)
      {
        throw new Exception('Token Expired');
      }
      else
      {
        return true;
      } 
    }
    else 
    {
      throw new Exception('Invalid Token');
    }
  }
  catch(Exception $e)
  {
    $message = $e->getMessage(); 
    echo ResponseHelper::construct_response(403, null, $message, null);

    exit();
  }
}
?>

