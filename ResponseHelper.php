<?php

class ResponseHelper
{
  public function construct_response($status_code, $sub_status_code, $message, $data)
  {
    $response = new stdClass();

    if($status_code)
    {
      $response->status_code = $status_code;
    }

    if($sub_status_code)
    {
      $response->sub_status_code = $sub_status_code;
    }

    if($message)
    {
      $response->message = $message;
    }

    if($data)
    {
      $response->data = $data;
    }

    header('Content-Type: text/plain');

    return json_encode($response);
  }
}

?>

