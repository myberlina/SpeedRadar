<?php

  // Set PHP headers for YAML output.
  header('Content-Type: application/yaml; charset=utf-8');
  header('Content-Disposition: attachment; filename="radar.conf"');

  readfile("/etc/radar/radar.conf");
?>
