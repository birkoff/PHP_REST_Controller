<?php

class RequestHelper
{
  public function parse_request($data)
  {
    return json_decode($data);
  }
}

?>
