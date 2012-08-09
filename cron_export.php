<?php
//uncomment when moved to server - to ensure this page is not accessed from anywhere else
//if ($_SERVER['REMOTE_ADDR'] !== '<your server ip address') {
//  die("You are not a cron job!");
//}

require_once 'app/Mage.php';
// wget -O - http://<www.example.com>/Cron_Import.php/?files=3XSEEEE.csv
  umask(0);

  //$_SERVER['SERVER_PORT']='443';
  Mage::app();

  $profileId = 11; //put your profile id here
  //$filename = Mage::app()->getRequest()->getParam('files'); // set the filename that is to be imported - file needs to be present in var/import directory  
  //if (!isset($filename))  {
  //die("No file has been set!");
  //}
  $logFileName= 'export.log';  
  $recordCount = 0;

  Mage::log("Export Started",null,$logFileName);  
 
  $profile = Mage::getModel('dataflow/profile');
  
  $userModel = Mage::getModel('admin/user');
  $userModel->setUserId(0);
  Mage::getSingleton('admin/session')->setUser($userModel);
  
  if ($profileId) {
    $profile->load($profileId);
    if (!$profile->getId()) {
      Mage::getSingleton('adminhtml/session')->addError('The profile you are trying to save no longer exists');
    }
  }

  Mage::register('current_convert_profile', $profile);

  $profile->run();
  
  echo 'Export Completed';
  Mage::log("Export Completed",null,$logFileName);
 ?> 