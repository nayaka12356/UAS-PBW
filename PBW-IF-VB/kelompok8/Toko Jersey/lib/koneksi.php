<?php

try {
  $conn = new PDO("mysql:host=localhost;dbname=tokojersey","root","");
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (Exception $e) {
  print "Koneksi atau query bermasalah" . $e->getMessage() . "</br>";
  die();
}

 ?>
