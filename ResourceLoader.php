<?php

class ResourceLoader
{
  public function get_resource_info($name, $version)
  {
    $resource_info = array(null, null);
    
    if ($name && $version)
    {
      $resource_name = ucfirst(substr($name, 0, strlen($name) - 1));
	
      $resource_file_name = $resource_name.'.php';

      $resource_path = app()->path->api($version, $resource_file_name);

      if (file_exists($resource_path))
      {
        $resource_info = array($resource_path, $resource_name);
      }
    }
    return $resource_info;
  }
}

?>

